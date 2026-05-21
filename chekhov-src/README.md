# chekhov-src — Astro-исходники сайта репетитора

Статический сайт под `palomatika.ru/chekhov/`. Собирается Astro, кладёт результат в `../public/chekhov/` (он коммитится в репо и попадает в прод вместе с Palomatika).

## Запуск

```bash
cd chekhov-src
npm install
npm run dev      # dev-сервер на http://localhost:4321/chekhov/
npm run build    # билд в ../public/chekhov/
```

## Где править контент

| Что | Где |
|-----|-----|
| Имя, телефон, WhatsApp/Telegram, цены, ID Метрики | `src/data/site.ts` |
| Тексты страниц | `src/pages/**/*.astro` |
| Статьи блога | `src/content/blog/*.md` |
| Layout, шапка, футер, формы | `src/layouts/`, `src/components/` |
| CSS-переменные и базовые стили | `src/styles/global.css` |
| Фото и статика | `public/` |

## Найти все плейсхолдеры

```bash
grep -rn 'TODO\|class="todo"' src/
```

## PHP-эндпоинт формы

`public/api/lead.php` → после билда оказывается в `../public/chekhov/api/lead.php`.
Для уведомлений в Telegram скопируйте `api/config.example.php` → `api/config.php` (на проде) и впишите токен/chat_id.

## Деплой

`public/chekhov/` коммитится в репо Palomatika и катится автодеплоем `claude/*` → `main`. После любой правки исходников:

```bash
npm run build
git add chekhov-src ../public/chekhov
git commit -m "feat(chekhov): ..."
```
