    </main>

    <?php if (SHOW_BOTTOM_NAV): ?>
    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="index.php" class="bottom-nav-item <?= ACTIVE_PAGE === 'dashboard' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <span>Главная</span>
        </a>
        <a href="schedule.php" class="bottom-nav-item <?= ACTIVE_PAGE === 'schedule' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span>Расписание</span>
        </a>
        <a href="payments.php" class="bottom-nav-item <?= ACTIVE_PAGE === 'payments' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span>Выплаты</span>
        </a>
        <a href="students.php" class="bottom-nav-item <?= ACTIVE_PAGE === 'students' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
            </svg>
            <span>Ученики</span>
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
        e.preventDefault();
        deferredPrompt = e;

        // Показываем кнопку установки если есть
        const installBtn = document.getElementById('pwa-install-btn');
        if (installBtn) {
            installBtn.style.display = 'block';
            installBtn.addEventListener('click', () => {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choice) => {
                    if (choice.outcome === 'accepted') {
                        console.log('PWA installed');
                    }
                    deferredPrompt = null;
                    installBtn.style.display = 'none';
                });
            });
        }
    });

    window.addEventListener('appinstalled', () => {
        console.log('PWA was installed');
        deferredPrompt = null;
    });
    </script>

    <!-- Page-specific JS -->
    <?php if (defined('PAGE_JS')): ?>
    <script><?= PAGE_JS ?></script>
    <?php endif; ?>
</body>
</html>
