/**
 * Защита форм заявок на стороне браузера.
 *
 * Каждая форма получает от сервера одноразовый подписанный токен и, если
 * SmartCaptcha включена в настройках, — токен капчи. Прямой POST на lead.php
 * мимо страницы ни того, ни другого не имеет.
 *
 * Используется и короткими формами главной, и общей LeadForm.
 */

type CaptchaConfig = { enabled: boolean; client_key?: string };
type TokenState = {
  token: string;
  receivedAt: number;
  matureAfter: number;
  captcha: CaptchaConfig;
};

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));
const tokenState = new WeakMap<HTMLFormElement, TokenState>();

async function fetchToken(form: HTMLFormElement): Promise<TokenState | null> {
  const url = form.dataset.tokenUrl;
  if (!url) return null;
  const res = await fetch(url, { headers: { Accept: 'application/json' } });
  const json = await res.json();
  if (!json || !json.token) throw new Error('no token');

  const state: TokenState = {
    token: json.token,
    receivedAt: Date.now(),
    // Ждём чуть дольше серверного порога, чтобы разбег часов не мешал.
    matureAfter: ((json.min_age || 2) + 0.6) * 1000,
    captcha: json.captcha || { enabled: false },
  };
  tokenState.set(form, state);
  const field = form.querySelector<HTMLInputElement>('[data-form-token]');
  if (field) field.value = json.token;
  return state;
}

async function ensureToken(form: HTMLFormElement): Promise<TokenState | null> {
  const current = tokenState.get(form);
  if (current) return current;
  try {
    return await fetchToken(form);
  } catch {
    return null;
  }
}

// Токен одноразовый — после любой отправки нужен свежий.
function invalidateToken(form: HTMLFormElement): void {
  tokenState.delete(form);
  const field = form.querySelector<HTMLInputElement>('[data-form-token]');
  if (field) field.value = '';
}

let captchaScript: Promise<void> | null = null;
function loadCaptchaScript(): Promise<void> {
  if (captchaScript) return captchaScript;
  captchaScript = new Promise<void>((resolve, reject) => {
    // render=onload: скрипт сам дёрнет колбэк, когда window.smartCaptcha готов.
    (window as any).__evriumCaptchaReady = () => resolve();
    const s = document.createElement('script');
    s.src = 'https://smartcaptcha.yandexcloud.net/captcha.js?render=onload&onload=__evriumCaptchaReady';
    s.async = true;
    s.onerror = () => reject(new Error('captcha script failed'));
    document.head.appendChild(s);
  });
  return captchaScript;
}

/** Невидимая капча: виджет молчит, пока форму не отправят. */
async function captchaToken(form: HTMLFormElement, clientKey: string): Promise<string> {
  await loadCaptchaScript();
  const container = form.querySelector<HTMLElement>('[data-captcha-container]');
  const api = (window as any).smartCaptcha;
  if (!container || !api) return '';

  return new Promise<string>((resolve, reject) => {
    const existing = container.dataset.widgetId;
    if (existing === undefined) {
      const id = api.render(container, {
        sitekey: clientKey,
        invisible: true,
        hideShield: true,
        callback: (t: string) => {
          const pending = (container as any).__resolve;
          (container as any).__resolve = null;
          if (pending) pending(t);
        },
      });
      container.dataset.widgetId = String(id);
    } else {
      // Повторная отправка: без сброса виджет отдаст уже сгоревший токен.
      api.reset(Number(existing));
    }

    (container as any).__resolve = resolve;
    setTimeout(() => {
      if ((container as any).__resolve === resolve) {
        (container as any).__resolve = null;
        reject(new Error('captcha timeout'));
      }
    }, 30000);
    api.execute(Number(container.dataset.widgetId));
  });
}

/**
 * ClientID Метрики в заявку.
 *
 * Основной путь — колбэк getClientID из Layout.astro: он дописывает в форму
 * скрытое поле, когда догрузится tag.js. Но колбэк может не успеть (медленная
 * сеть, мобильный браузер) — тогда заявка приходит без ID и её нельзя
 * сопоставить с визитом в Метрике. Подстраховка: тот же ID лежит в куке
 * _ym_uid, её видно синхронно в момент отправки.
 *
 * Пусто в обоих местах — значит счётчик у посетителя действительно не
 * загрузился (блокировщик, обрыв). Это уже сигнал, а не потеря данных.
 */
function attachClientId(fd: FormData): void {
  if (fd.get('ym_client_id')) return;
  const m = document.cookie.match(/(?:^|;\s*)_ym_uid=([^;]+)/);
  if (m) fd.set('ym_client_id', decodeURIComponent(m[1]));
}

/** Дополнить FormData токеном формы и, при необходимости, токеном капчи. */
export async function attachGuards(form: HTMLFormElement, fd: FormData): Promise<void> {
  attachClientId(fd);
  const state = await ensureToken(form);
  if (!state) throw new Error('no token');
  fd.set('form_token', state.token);

  // Если токен только что выдан — выдерживаем его минимальный возраст.
  const age = Date.now() - state.receivedAt;
  if (age < state.matureAfter) await sleep(state.matureAfter - age);

  if (state.captcha?.enabled && state.captcha.client_key) {
    fd.set('smart-token', await captchaToken(form, state.captcha.client_key));
  }
}

/** Запросить токен заранее, чтобы к моменту отправки он успел «созреть». */
export function primeLeadForm(form: HTMLFormElement): void {
  ensureToken(form);
  form.addEventListener('focusin', () => { ensureToken(form); }, { once: true });
}

/** Сбросить токен после отправки и подготовить следующий. */
export function refreshLeadForm(form: HTMLFormElement): void {
  invalidateToken(form);
  ensureToken(form);
}

/* --- Валидация на стороне браузера ---------------------------------------
 *
 * У форм стоит novalidate: браузерные «пузыри» мешают своей вёрстке ошибок,
 * а отправка идёт через fetch. Значит проверять состав полей должны мы сами —
 * иначе пустая форма улетает на сервер и человек видит общую ошибку.
 *
 * Ошибку показываем только после того, как поле «потрогали» или нажали
 * «Отправить»: подсвечивать пустое поле, к которому ещё не притрагивались, —
 * плохая практика. Состояние держим в aria-invalid: его же читают скринридеры,
 * по нему же работает CSS (см. [aria-invalid="true"] в стилях форм).
 */

type LeadControl = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

function leadControls(form: HTMLFormElement): LeadControl[] {
  return Array.from(
    form.querySelectorAll<LeadControl>('input, select, textarea')
  ).filter((el) => el.name !== 'website' && el.type !== 'hidden' && !el.disabled);
}

function syncControl(el: LeadControl): void {
  const shown = el.dataset.touched === '1' || el.form?.dataset.submitAttempted === '1';
  el.setAttribute('aria-invalid', shown && !el.validity.valid ? 'true' : 'false');
}

/**
 * Проверить форму перед отправкой. false — отправлять нельзя: подсветили поля
 * и перевели фокус на первое проблемное, чтобы человек сразу видел, куда идти.
 */
export function validateLeadForm(form: HTMLFormElement): boolean {
  form.dataset.submitAttempted = '1';
  const controls = leadControls(form);
  controls.forEach(syncControl);

  const firstInvalid = controls.find((el) => !el.validity.valid);
  if (!firstInvalid) return true;

  firstInvalid.focus({ preventScroll: true });
  firstInvalid.scrollIntoView({ block: 'center', behavior: 'smooth' });
  return false;
}

/** Подписать поля формы на подсветку ошибок. Вызывать один раз на форму. */
export function wireLeadValidation(form: HTMLFormElement): void {
  form.addEventListener(
    'blur',
    (e) => {
      const el = e.target as LeadControl;
      if (!el || !el.validity) return;
      el.dataset.touched = '1';
      syncControl(el);
    },
    true
  );
  form.addEventListener('input', (e) => {
    const el = e.target as LeadControl;
    if (el && el.validity) syncControl(el);
  });
  form.addEventListener('change', (e) => {
    const el = e.target as LeadControl;
    if (!el || !el.validity) return;
    el.dataset.touched = '1';
    syncControl(el);
  });
}

/** После успешной отправки — снять подсветку, иначе пустая форма горит красным. */
export function resetLeadValidation(form: HTMLFormElement): void {
  delete form.dataset.submitAttempted;
  leadControls(form).forEach((el) => {
    delete el.dataset.touched;
    el.setAttribute('aria-invalid', 'false');
  });
}
