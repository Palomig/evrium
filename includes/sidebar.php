<?php
// Подключаем конфигурацию, если она еще не подключена
if (!isset($chapters)) {
    require_once __DIR__ . '/../config.php';
}

// Определяем текущую страницу
$current_class = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$current_chapter = isset($_GET['chapter']) ? (int)$_GET['chapter'] : 0;
?>

<!-- Левое боковое меню -->
<aside class="sidebar">
    <ul class="sidebar-menu">
        <li>
            <a href="index.php" class="<?php echo ($current_page == 'index' && !$current_class) ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Главная
            </a>
        </li>

        <?php foreach ($chapters as $menu_class_num => $menu_class_data): ?>
        <li>
            <div class="menu-item-label collapsed" onclick="toggleSubmenu('class<?php echo $menu_class_num; ?>')">
                <i class="fas fa-graduation-cap"></i> <?php echo $menu_class_data['name']; ?>
            </div>
            <ul class="submenu <?php echo ($current_class == $menu_class_num) ? 'open' : ''; ?>" id="class<?php echo $menu_class_num; ?>">
                <?php foreach ($menu_class_data['chapters'] as $menu_chapter_num => $menu_chapter_data): ?>
                <li>
                    <a href="chapter.php?class=<?php echo $menu_class_num; ?>&chapter=<?php echo $menu_chapter_num; ?>"
                       class="<?php echo ($current_class == $menu_class_num && $current_chapter == $menu_chapter_num) ? 'active' : ''; ?>">
                        <i class="fas fa-book-reader"></i> <?php echo $menu_chapter_data['title']; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </li>
        <?php endforeach; ?>

        <li>
            <a href="examples.php" class="<?php echo ($current_page == 'examples') ? 'active' : ''; ?>">
                <i class="fas fa-puzzle-piece"></i> Примеры упражнений
            </a>
        </li>
        <li>
            <a href="editor.php" class="<?php echo ($current_page == 'editor') ? 'active' : ''; ?>">
                <i class="fas fa-pencil-alt"></i> Редактор
            </a>
        </li>
        <li>
            <a href="docs.php" class="<?php echo ($current_page == 'docs') ? 'active' : ''; ?>">
                <i class="fas fa-info-circle"></i> Документация
            </a>
        </li>
    </ul>
</aside>

<script>
    // Раскрываем активный класс при загрузке страницы
    document.addEventListener('DOMContentLoaded', function() {
        // Сворачиваем все неактивные меню
        document.querySelectorAll('.menu-item-label:not(.open)').forEach(function(label) {
            if (!label.nextElementSibling.classList.contains('open')) {
                label.classList.add('collapsed');
            }
        });

        // Раскрываем активное меню
        <?php if ($current_class > 0): ?>
        var activeLabel = document.querySelector('#class<?php echo $current_class; ?>').previousElementSibling;
        if (activeLabel) {
            activeLabel.classList.remove('collapsed');
        }
        <?php endif; ?>
    });
</script>
