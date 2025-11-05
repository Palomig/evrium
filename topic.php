<?php
// Подключаем конфигурацию
require_once 'config.php';

// Получаем параметры из URL
$class = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$chapter_num = isset($_GET['chapter']) ? (int)$_GET['chapter'] : 0;
$topic_num = isset($_GET['topic']) ? (int)$_GET['topic'] : 0;

// Проверяем существование класса, главы и темы
$chapter_data = getChapter($class, $chapter_num);
$topic_data = getTopic($class, $chapter_num, $topic_num);

if (!$chapter_data || !$topic_data) {
    header('Location: index.php');
    exit;
}

$class_data = $chapters[$class];

// Настройки страницы
$current_page = 'topic';
$page_title = $topic_data['title'];
$page_description = $topic_data['description'];

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
                        <li class="breadcrumb-item">
                            <a href="chapter.php?class=<?php echo $class; ?>&chapter=<?php echo $chapter_num; ?>">
                                <?php echo $chapter_data['title']; ?>
                            </a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo $topic_data['title']; ?></li>
                    </ol>
                </nav>

                <!-- Заголовок темы -->
                <div class="row">
                    <div class="col-12">
                        <div class="topic-header mb-4">
                            <h1 class="display-5">
                                <i class="fas fa-book-open text-primary"></i> <?php echo $topic_data['title']; ?>
                            </h1>
                            <p class="lead text-muted"><?php echo $topic_data['description']; ?></p>
                            <div class="badges">
                                <span class="badge" style="background-color: <?php echo $class_data['color']; ?>;">
                                    <?php echo $class_data['name']; ?>
                                </span>
                                <span class="badge bg-secondary">
                                    Тема <?php echo $topic_num; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Теоретический материал -->
                <?php if (isset($topic_data['theory'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h3 class="mb-0">
                                    <i class="fas fa-book"></i> Теоретический материал
                                </h3>
                            </div>
                            <div class="card-body">
                                <ul class="theory-list">
                                    <?php foreach ($topic_data['theory'] as $theory_item): ?>
                                    <li class="mb-3">
                                        <i class="fas fa-check-circle text-success"></i>
                                        <?php echo $theory_item; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Определения -->
                <?php if (isset($topic_data['definitions'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-info text-white">
                                <h3 class="mb-0">
                                    <i class="fas fa-highlighter"></i> Определения
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php foreach ($topic_data['definitions'] as $definition): ?>
                                <div class="definition-box p-3 mb-3">
                                    <i class="fas fa-quote-left text-muted"></i>
                                    <strong><?php echo $definition; ?></strong>
                                    <i class="fas fa-quote-right text-muted"></i>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Примеры -->
                <?php if (isset($topic_data['examples'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-success text-white">
                                <h3 class="mb-0">
                                    <i class="fas fa-lightbulb"></i> Примеры
                                </h3>
                            </div>
                            <div class="card-body">
                                <ol class="examples-list">
                                    <?php foreach ($topic_data['examples'] as $example): ?>
                                    <li class="mb-3">
                                        <div class="example-box p-3">
                                            <i class="fas fa-arrow-right text-success"></i>
                                            <?php echo $example; ?>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Интерактивная визуализация (заглушка для будущего) -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-warning text-dark">
                                <h3 class="mb-0">
                                    <i class="fas fa-magic"></i> Интерактивная визуализация
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="interactive-placeholder text-center p-5">
                                    <i class="fas fa-shapes fa-4x text-muted mb-3"></i>
                                    <h4>Скоро здесь появится интерактивная визуализация!</h4>
                                    <p class="text-muted">
                                        Вы сможете взаимодействовать с геометрическими фигурами,
                                        изменять параметры и наблюдать за результатами в реальном времени.
                                    </p>
                                    <a href="examples.php?class=<?php echo $class; ?>&chapter=<?php echo $chapter_num; ?>"
                                       class="btn btn-warning">
                                        <i class="fas fa-puzzle-piece"></i> Перейти к упражнениям
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Дополнительные материалы -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-tasks"></i> Рекомендации к изучению
                                </h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i>
                                        Внимательно прочитайте теоретический материал
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i>
                                        Выучите все определения наизусть
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i>
                                        Разберите все приведённые примеры
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i>
                                        Попробуйте решить задачи из учебника
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i>
                                        Используйте интерактивные упражнения для закрепления
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-question-circle"></i> Проверьте себя
                                </h5>
                            </div>
                            <div class="card-body">
                                <p>Ответьте на вопросы для самопроверки:</p>
                                <ul class="questions-list">
                                    <li class="mb-2">
                                        <i class="fas fa-question text-primary"></i>
                                        Можете ли вы объяснить все определения своими словами?
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-question text-primary"></i>
                                        Понимаете ли вы, как применять теорию на практике?
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-question text-primary"></i>
                                        Можете ли вы привести свои примеры по этой теме?
                                    </li>
                                </ul>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Совет:</strong> Если на какой-то вопрос вы ответили "нет" - перечитайте материал ещё раз.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Навигация по темам -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <?php
                            // Получаем все темы текущей главы
                            $all_topics = array_keys($chapter_data['topics']);
                            $current_index = array_search($topic_num, $all_topics);

                            // Предыдущая тема
                            $prev_topic = null;
                            if (is_array($chapter_data['topics']) && $current_index !== false && $current_index > 0) {
                                $prev_topic = $all_topics[$current_index - 1];
                            }

                            // Следующая тема
                            $next_topic = null;
                            if (is_array($chapter_data['topics']) && $current_index !== false && isset($all_topics[$current_index + 1])) {
                                $next_topic = $all_topics[$current_index + 1];
                            }
                            ?>

                            <div>
                                <?php if ($prev_topic): ?>
                                <a href="topic.php?class=<?php echo $class; ?>&chapter=<?php echo $chapter_num; ?>&topic=<?php echo $prev_topic; ?>"
                                   class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left"></i> Предыдущая тема
                                </a>
                                <?php else: ?>
                                <a href="chapter.php?class=<?php echo $class; ?>&chapter=<?php echo $chapter_num; ?>"
                                   class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> К главе
                                </a>
                                <?php endif; ?>
                            </div>

                            <div>
                                <a href="chapter.php?class=<?php echo $class; ?>&chapter=<?php echo $chapter_num; ?>"
                                   class="btn btn-outline-secondary">
                                    <i class="fas fa-list"></i> Все темы главы
                                </a>
                            </div>

                            <div>
                                <?php if ($next_topic): ?>
                                <a href="topic.php?class=<?php echo $class; ?>&chapter=<?php echo $chapter_num; ?>&topic=<?php echo $next_topic; ?>"
                                   class="btn btn-outline-primary">
                                    Следующая тема <i class="fas fa-arrow-right"></i>
                                </a>
                                <?php else: ?>
                                <a href="examples.php?class=<?php echo $class; ?>&chapter=<?php echo $chapter_num; ?>"
                                   class="btn btn-success">
                                    К упражнениям <i class="fas fa-arrow-right"></i>
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
