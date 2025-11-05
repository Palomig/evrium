<?php
// Подключаем конфигурацию
require_once 'config.php';

// Настройки страницы
$current_page = 'index';
$page_title = 'Главная';
$page_description = 'Интерактивный сайт для изучения геометрии по учебнику Л.С. Атанасяна (7-9 классы)';

// Подключаем header
include 'includes/header.php';
?>

    <!-- Контейнер с боковым меню -->
    <div class="layout-container">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Основной контент -->
        <main class="main-content">
            <div class="container my-4">
                <!-- Приветственный блок -->
                <div class="row">
                    <div class="col-12">
                        <div class="jumbotron bg-gradient p-5 rounded shadow-lg">
                            <h1 class="display-4 text-white">
                                <i class="fas fa-shapes"></i> Добро пожаловать!
                            </h1>
                            <p class="lead text-white">Интерактивный сайт для изучения геометрии по учебнику Л.С. Атанасяна (7-9 классы)</p>
                            <hr class="my-4 bg-white">
                            <p class="text-white">Здесь вы найдете интерактивные упражнения, визуализации и демонстрации геометрических концепций.</p>
                            <a class="btn btn-light btn-lg" href="examples.php" role="button">
                                <i class="fas fa-rocket"></i> Перейти к упражнениям
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Классы -->
                <div class="row mt-5">
                    <div class="col-12">
                        <h2 class="mb-4">
                            <i class="fas fa-graduation-cap"></i> Выберите класс
                        </h2>
                    </div>
                </div>

                <div class="row g-4">
                    <?php foreach ($chapters as $class_num => $class_data): ?>
                    <!-- <?php echo $class_num; ?> класс -->
                    <div class="col-md-4">
                        <div class="card h-100 shadow-hover">
                            <div class="card-header text-white" style="background-color: <?php echo $class_data['color']; ?>;">
                                <h3 class="mb-0">
                                    <i class="fas fa-book"></i> <?php echo $class_data['name']; ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($class_data['chapters'] as $chapter_num => $chapter_data): ?>
                                    <li class="list-group-item">
                                        <a href="chapter.php?class=<?php echo $class_num; ?>&chapter=<?php echo $chapter_num; ?>" class="chapter-link">
                                            <i class="fas fa-arrow-right"></i> <?php echo $chapter_data['title']; ?>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Особенности -->
                <div class="row mt-5">
                    <div class="col-12">
                        <h2 class="mb-4">
                            <i class="fas fa-star"></i> Особенности сайта
                        </h2>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="feature-card text-center p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-hand-pointer fa-3x text-primary"></i>
                            </div>
                            <h4>Интерактивность</h4>
                            <p>Перетаскивайте точки, изменяйте углы и длины, наблюдайте за изменениями в реальном времени</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="feature-card text-center p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-eye fa-3x text-success"></i>
                            </div>
                            <h4>Визуализация</h4>
                            <p>Все геометрические концепции представлены в виде наглядных SVG-иллюстраций</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="feature-card text-center p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-mobile-alt fa-3x text-warning"></i>
                            </div>
                            <h4>Адаптивность</h4>
                            <p>Сайт работает на всех устройствах: компьютерах, планшетах и смартфонах</p>
                        </div>
                    </div>
                </div>

                <!-- Статистика -->
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h3 class="text-primary"><i class="fas fa-book"></i> 12</h3>
                                        <p class="text-muted">Глав</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h3 class="text-success"><i class="fas fa-graduation-cap"></i> 3</h3>
                                        <p class="text-muted">Класса</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h3 class="text-warning"><i class="fas fa-puzzle-piece"></i> 50+</h3>
                                        <p class="text-muted">Упражнений</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h3 class="text-info"><i class="fas fa-lightbulb"></i> 100+</h3>
                                        <p class="text-muted">Примеров</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

<?php include 'includes/footer.php'; ?>
