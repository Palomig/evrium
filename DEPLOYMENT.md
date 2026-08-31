# Деплой сайта эвриум.рф

Автодеплой по FTP из GitHub Actions **отключён** (31.08.2026): раннеры GitHub
стабильно не достукиваются до FTP Timeweb (`ETIMEDOUT 5.23.50.27:21`, похоже,
хостинг режет иностранные IP). Файлы на хостинг заливаются вручную.

## Что происходит автоматически

При push в ветку `claude/**` workflow `.github/workflows/auto-merge.yml`
мёржит её в `main`. И всё — **push в GitHub не меняет сайт**.

## Как выкатить изменения

### 1. Собрать сайт

```bash
cd chekhov-src
npm run deploy      # astro build → dist → rsync в корень репозитория
```

Это локальная операция: Astro-исходники лежат в `chekhov-src/`, готовые файлы —
в корне репозитория. FTP тут не участвует.

### 2. Закоммитить и запушить

```bash
git add -A
git commit -m "..."
git push origin claude/<ветка>
```

Ветка автоматически уедет в `main` — репозиторий остаётся источником правды.

### 3. Залить файлы на хостинг

С dev-VPS (78.17.28.40) порт 21 Timeweb доступен — проверено. Заливаем только
изменённые последним коммитом файлы:

```bash
set -a; . /home/dev/.agent-secrets/timeweb.env; set +a   # TMW_PSW
for f in $(git show --name-only --pretty=format: HEAD | grep -v '^chekhov-src/'); do
  [ -f "$f" ] || continue     # удалённые локально файлы пропускаем
  curl -sS --ftp-create-dirs -T "$f" \
    "ftp://cw95865.tmweb.ru/PALOMATIKA/public_html/$f" -u "cw95865:$TMW_PSW" \
    && echo "ok  $f" || echo "FAIL $f"
done
```

Альтернатива — FileZilla: хост `cw95865.tmweb.ru`, юзер `cw95865`,
каталог `/PALOMATIKA/public_html/`.

### 4. Проверить

```bash
curl -sS -L https://эвриум.рф/<страница>/ | grep 'что искали'
```

Если менялись стили или скрипты — у собранных ассетов новое имя с хешем
(`_assets/Layout.<hash>.css`), его тоже нужно залить, иначе страница приедет
без стилей.

## Если захочется вернуть автодеплой

Workflow `deploy-timeweb.yml` удалён, но лежит в истории git — восстанавливается
из коммита, в котором удалён. Смысл в этом появится, только если FTP Timeweb
станет доступен с раннеров GitHub (или деплой переедет на self-hosted runner
на dev-VPS). Секреты `FTP_SERVER` / `FTP_USERNAME` / `FTP_PASSWORD` в настройках
репозитория остались нетронутыми.
