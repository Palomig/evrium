<?php
// Подключаем конфигурацию
require_once 'config.php';

// Получаем параметры из URL
$class = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$chapter_num = isset($_GET['chapter']) ? (int)$_GET['chapter'] : 0;

// Проверяем существование класса и главы
if (!isset($chapters[$class]) || !isset($chapters[$class]['chapters'][$chapter_num])) {
    header('Location: index.php');
    exit;
}

$class_data = $chapters[$class];
$chapter_data = $chapters[$class]['chapters'][$chapter_num];

// Настройки страницы
$current_page = 'chapter';
$page_title = $chapter_data['title'];
$page_description = $chapter_data['description'];

// Подключаем header
include 'includes/header.php';
?>

    <!-- Контейнер с боковым меню -->
    <div class="layout-container">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Основной контент -->
        <main class="main-content">
            <div class="container my-4">
                <!-- Навигация (хлебные крошки) -->
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i> Главная</a></li>
                        <li class="breadcrumb-item"><?php echo $class_data['name']; ?></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo $chapter_data['title']; ?></li>
                    </ol>
                </nav>

                <!-- Заголовок главы -->
                <div class="row">
                    <div class="col-12">
                        <div class="chapter-header mb-4">
                            <h1 class="display-5">
                                <i class="fas fa-book-open"></i> <?php echo $chapter_data['title']; ?>
                            </h1>
                            <p class="lead text-muted"><?php echo $chapter_data['description']; ?></p>
                            <span class="badge" style="background-color: <?php echo $class_data['color']; ?>;">
                                <?php echo $class_data['name']; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Темы главы -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <h4 class="mb-0">
                                    <i class="fas fa-list"></i> Темы главы
                                </h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php
                                    $topic_index = 1;
                                    foreach ($chapter_data['topics'] as $topic_num => $topic_info):
                                        // Проверяем, является ли тема массивом с детальной информацией
                                        $is_detailed = is_array($topic_info) && isset($topic_info['title']);
                                        $topic_title = $is_detailed ? $topic_info['title'] : $topic_info;
                                        $topic_desc = $is_detailed && isset($topic_info['description']) ? $topic_info['description'] : 'Изучите теорию и выполните упражнения';
                                        $topic_link = $is_detailed ? "topic.php?class={$class}&chapter={$chapter_num}&topic={$topic_num}" : "#";
                                    ?>
                                    <div class="col-md-6 mb-3">
                                        <a href="<?php echo $topic_link; ?>" class="topic-link-card" style="text-decoration: none; color: inherit;">
                                            <div class="topic-item p-3 border rounded clickable">
                                                <h5>
                                                    <span class="badge bg-secondary"><?php echo $topic_index; ?></span>
                                                    <?php echo $topic_title; ?>
                                                    <?php if ($is_detailed): ?>
                                                    <i class="fas fa-arrow-right text-primary float-end"></i>
                                                    <?php endif; ?>
                                                </h5>
                                                <p class="text-muted small mb-0">
                                                    <i class="fas fa-book-open"></i> <?php echo $topic_desc; ?>
                                                </p>
                                            </div>
                                        </a>
                                    </div>
                                    <?php
                                        $topic_index++;
                                    endforeach;
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Интерактивные упражнения -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-success text-white">
                                <h4 class="mb-0">
                                    <i class="fas fa-puzzle-piece"></i> Интерактивные упражнения
                                </h4>
                            </div>
                            <div class="card-body">
                                <p>
                                    <i class="fas fa-info-circle"></i>
                                    Упражнения по этой главе доступны в разделе
                                    <a href="examples.php?class=<?php echo $class; ?>&chapter=<?php echo $chapter_num; ?>">
                                        <i class="fas fa-external-link-alt"></i> Примеры упражнений
                                    </a>
                                </p>
                                <a href="examples.php?class=<?php echo $class; ?>&chapter=<?php echo $chapter_num; ?>" class="btn btn-success">
                                    <i class="fas fa-play"></i> Перейти к упражнениям
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Дополнительные материалы -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="fas fa-lightbulb"></i> Полезные советы
                                </h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i> Изучите теоретический материал
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i> Попробуйте решить задачи самостоятельно
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i> Используйте интерактивные упражнения
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i> Проверьте решение с помощью подсказок
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-book"></i> Рекомендации
                                </h5>
                            </div>
                            <div class="card-body">
                                <p>
                                    <i class="fas fa-graduation-cap"></i>
                                    Обратите внимание на основные определения и теоремы этой главы.
                                </p>
                                <p>
                                    <i class="fas fa-pencil-alt"></i>
                                    Решайте задачи из учебника Атанасяна для закрепления материала.
                                </p>
                                <p class="mb-0">
                                    <i class="fas fa-users"></i>
                                    Обсуждайте сложные задачи с одноклассниками и учителем.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Навигация по главам -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between">
                            <?php
                            // Получаем все главы текущего класса
                            $all_chapters = array_keys($chapters[$class]['chapters']);
                            $current_index = array_search($chapter_num, $all_chapters);

                            // Предыдущая глава
                            $prev_chapter = null;
                            $prev_class = $class;

                            if ($current_index > 0) {
                                // Есть предыдущая глава в текущем классе
                                $prev_chapter = $all_chapters[$current_index - 1];
                            } else if ($class > 7) {
                                // Переходим к последней главе предыдущего класса
                                $prev_class = $class - 1;
                                $prev_chapters = array_keys($chapters[$prev_class]['chapters']);
                                $prev_chapter = end($prev_chapters);
                            }

                            // Следующая глава
                            $next_chapter = null;
                            $next_class = $class;

                            if (isset($all_chapters[$current_index + 1])) {
                                // Есть следующая глава в текущем классе
                                $next_chapter = $all_chapters[$current_index + 1];
                            } else if ($class < 9) {
                                // Переходим к первой главе следующего класса
                                $next_class = $class + 1;
                                $next_chapters = array_keys($chapters[$next_class]['chapters']);
                                $next_chapter = reset($next_chapters);
                            }
                            ?>

                            <div>
                                <?php if ($prev_chapter): ?>
                                <a href="chapter.php?class=<?php echo $prev_class; ?>&chapter=<?php echo $prev_chapter; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left"></i> Предыдущая глава
                                </a>
                                <?php endif; ?>
                            </div>

                            <div>
                                <?php if ($next_chapter): ?>
                                <a href="chapter.php?class=<?php echo $next_class; ?>&chapter=<?php echo $next_chapter; ?>" class="btn btn-outline-primary">
                                    Следующая глава <i class="fas fa-arrow-right"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

<?php include 'includes/footer.php'; ?>
