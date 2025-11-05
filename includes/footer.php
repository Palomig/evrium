    <!-- Кнопка переключения меню для мобильных -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Футер -->
    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Интерактивная геометрия. По учебнику Л.С. Атанасяна (7-9 классы)</p>
            <p class="mt-2 small">Статический сайт с PHP. Все упражнения работают на клиенте.</p>
        </div>
    </footer>

    <!-- Bootstrap JS через CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery через CDN (если нужно) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- Sidebar JS -->
    <script>
        // Переключение подменю
        function toggleSubmenu(id) {
            const submenu = document.getElementById(id);
            const label = submenu.previousElementSibling;

            submenu.classList.toggle('open');
            label.classList.toggle('collapsed');
        }

        // Переключение бокового меню (для мобильных)
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('open');
        }

        // Закрытие меню при клике вне его (для мобильных)
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                const sidebar = document.querySelector('.sidebar');
                const toggle = document.querySelector('.sidebar-toggle');

                if (sidebar && toggle && sidebar.classList.contains('open') &&
                    !sidebar.contains(event.target) &&
                    !toggle.contains(event.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });
    </script>

    <?php if (isset($page_scripts)): ?>
        <!-- Дополнительные скрипты страницы -->
        <?php echo $page_scripts; ?>
    <?php endif; ?>

</body>
</html>
