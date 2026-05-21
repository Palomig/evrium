import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';

export default defineConfig({
  // Кириллический IDN-домен. Astro оставит строку как есть в HTML/sitemap.
  site: 'https://эвриум.рф',
  base: '/',
  trailingSlash: 'always',
  // Билдим в локальный dist/. Скрипт deploy в package.json копирует в ../.
  // НИКОГДА не ставьте outDir = '../' — Astro чистит outDir перед сборкой
  // и сотрёт корень репозитория! (Проверено на болезненном опыте.)
  outDir: 'dist',
  build: {
    assets: '_assets',
    format: 'directory',
  },
  integrations: [
    sitemap({
      filter: (page) => !page.includes('/404'),
    }),
  ],
});
