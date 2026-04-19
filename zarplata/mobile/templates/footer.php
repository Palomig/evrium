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
