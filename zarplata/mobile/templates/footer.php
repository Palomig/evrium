    </main>

    <?php if (SHOW_BOTTOM_NAV): ?>
    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="lessons.php" class="bottom-nav-item <?= ACTIVE_PAGE === 'lessons' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            <span>Уроки</span>
        </a>
        <a href="absences.php" class="bottom-nav-item <?= ACTIVE_PAGE === 'absences' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            <span>Посещаемость</span>
        </a>
        <a href="earnings.php" class="bottom-nav-item <?= ACTIVE_PAGE === 'earnings' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>Зарплата</span>
        </a>
    </nav>
    <?php endif; ?>

    <!-- Mobile JS -->
    <script src="assets/js/mobile.js"></script>

    <!-- Service Worker Registration -->
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/zarplata/mobile/service-worker.js')
                .then(function(registration) {
                    console.log('SW registered:', registration.scope);
                })
                .catch(function(error) {
                    console.log('SW registration failed:', error);
                });
        });
    }
    </script>

    <!-- PWA Install Prompt -->
    <script>
    let deferredPrompt;

    window.addEventListener('beforeinstallprompt', (e) => {
        console.log('beforeinstallprompt fired');
        e.preventDefault();
        deferredPrompt = e;

        // Показываем карточку и кнопку установки
        const installCard = document.getElementById('pwa-install-card');
        const installBtn = document.getElementById('pwa-install-btn');

        if (installCard) {
            installCard.style.display = 'block';
        }

        if (installBtn) {
            installBtn.onclick = function() {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choice) => {
                        console.log('User choice:', choice.outcome);
                        if (choice.outcome === 'accepted') {
                            console.log('PWA installed');
                            if (installCard) installCard.style.display = 'none';
                        }
                        deferredPrompt = null;
                    });
                }
            };
        }
    });

    window.addEventListener('appinstalled', () => {
        console.log('PWA was installed');
        deferredPrompt = null;
        const installCard = document.getElementById('pwa-install-card');
        if (installCard) installCard.style.display = 'none';
    });

    // Проверяем, установлено ли уже приложение
    if (window.matchMedia('(display-mode: standalone)').matches) {
        console.log('Running as installed PWA');
        const installCard = document.getElementById('pwa-install-card');
        if (installCard) installCard.style.display = 'none';
    }
    </script>

    <!-- Page-specific JS -->
    <?php if (defined('PAGE_JS')): ?>
    <script><?= PAGE_JS ?></script>
    <?php endif; ?>
</body>
</html>
