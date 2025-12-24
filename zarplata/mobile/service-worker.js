/**
 * Service Worker для PWA Эвриум Зарплата
 * Версия: 1.0.0
 */

const CACHE_NAME = 'zarplata-pwa-v1';
const STATIC_CACHE = 'zarplata-static-v1';

// Статические ресурсы для кэширования
const STATIC_ASSETS = [
    '/zarplata/mobile/assets/css/mobile-theme.css',
    '/zarplata/mobile/assets/js/mobile.js',
    '/zarplata/mobile/assets/icons/icon-192x192.png',
    '/zarplata/mobile/assets/icons/icon-512x512.png',
    '/zarplata/mobile/assets/icons/icon.svg',
    'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Nunito:wght@400;500;600;700&display=swap',
    'https://fonts.googleapis.com/icon?family=Material+Icons'
];

// Установка Service Worker
self.addEventListener('install', (event) => {
    console.log('[SW] Installing Service Worker...');

    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('[SW] Static assets cached');
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('[SW] Cache failed:', error);
            })
    );
});

// Активация Service Worker
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating Service Worker...');

    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => name !== CACHE_NAME && name !== STATIC_CACHE)
                        .map((name) => {
                            console.log('[SW] Deleting old cache:', name);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => {
                console.log('[SW] Service Worker activated');
                return self.clients.claim();
            })
    );
});

// Стратегия: Network First для API, Cache First для статики
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Пропускаем non-GET запросы
    if (event.request.method !== 'GET') {
        return;
    }

    // API запросы - всегда сеть (без кэширования)
    if (url.pathname.includes('/api/')) {
        event.respondWith(
            fetch(event.request)
                .catch(() => {
                    return new Response(
                        JSON.stringify({ success: false, error: 'Нет подключения к интернету' }),
                        {
                            status: 503,
                            headers: { 'Content-Type': 'application/json' }
                        }
                    );
                })
        );
        return;
    }

    // PHP страницы - Network First (свежие данные важнее)
    if (url.pathname.endsWith('.php')) {
        event.respondWith(
            fetch(event.request)
                .catch(() => {
                    return caches.match('/zarplata/mobile/offline.html')
                        .then((response) => {
                            return response || new Response(
                                '<html><body><h1>Нет подключения</h1><p>Проверьте интернет-соединение</p></body></html>',
                                { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                            );
                        });
                })
        );
        return;
    }

    // Статические ресурсы - Cache First
    event.respondWith(
        caches.match(event.request)
            .then((cachedResponse) => {
                if (cachedResponse) {
                    // Обновляем кэш в фоне
                    fetch(event.request)
                        .then((response) => {
                            if (response.ok) {
                                caches.open(STATIC_CACHE)
                                    .then((cache) => cache.put(event.request, response));
                            }
                        })
                        .catch(() => {});

                    return cachedResponse;
                }

                return fetch(event.request)
                    .then((response) => {
                        // Кэшируем успешные ответы для статики
                        if (response.ok && isStaticAsset(url.pathname)) {
                            const responseToCache = response.clone();
                            caches.open(STATIC_CACHE)
                                .then((cache) => cache.put(event.request, responseToCache));
                        }
                        return response;
                    });
            })
    );
});

// Проверка, является ли ресурс статическим
function isStaticAsset(pathname) {
    const staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.woff', '.woff2', '.ttf'];
    return staticExtensions.some(ext => pathname.endsWith(ext));
}

// Обработка push-уведомлений (заготовка на будущее)
self.addEventListener('push', (event) => {
    if (!event.data) return;

    const data = event.data.json();

    const options = {
        body: data.body || 'Новое уведомление',
        icon: '/zarplata/mobile/assets/icons/icon-192x192.png',
        badge: '/zarplata/mobile/assets/icons/icon-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            url: data.url || '/zarplata/mobile/'
        }
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Эвриум Зарплата', options)
    );
});

// Клик по уведомлению
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data?.url || '/zarplata/mobile/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Ищем уже открытое окно
                for (const client of clientList) {
                    if (client.url.includes('/zarplata/mobile/') && 'focus' in client) {
                        client.navigate(url);
                        return client.focus();
                    }
                }
                // Открываем новое окно
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

// Синхронизация в фоне (заготовка)
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-data') {
        console.log('[SW] Background sync triggered');
        // Здесь можно добавить логику синхронизации данных
    }
});

console.log('[SW] Service Worker loaded');
