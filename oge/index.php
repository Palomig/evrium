<?php
// Подключаем конфигурацию
require_once '../config.php';

// Настройки страницы
$current_page = 'oge';
$page_title = 'Подготовка к ОГЭ';
$page_description = 'Материалы для подготовки к ОГЭ по математике (геометрия)';

// Подключаем header
include '../includes/header.php';
?>

    <!-- Контейнер с боковым меню -->
    <div class="layout-container">
        <?php include '../includes/sidebar.php'; ?>

        <!-- Основной контент -->
        <main class="main-content">
            <div class="container my-4">
                <!-- Приветственный блок -->
                <div class="row">
                    <div class="col-12">
                        <div class="jumbotron bg-gradient p-5 rounded shadow-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <h1 class="display-4 text-white">
                                <i class="fas fa-graduation-cap"></i> Подготовка к ОГЭ
                            </h1>
                            <p class="lead text-white">Интерактивные материалы для подготовки к ОГЭ по математике (геометрия)</p>
                            <hr class="my-4 bg-white">
                            <p class="text-white">Здесь вы найдете задачи из банка ФИПИ с подробными решениями и интерактивными калькуляторами.</p>
                        </div>
                    </div>
                </div>

                <!-- Разделы подготовки -->
                <div class="row mt-5">
                    <div class="col-12">
                        <h2 class="mb-4">
                            <i class="fas fa-book"></i> Разделы подготовки
                        </h2>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Раздел 1-6 -->
                    <div class="col-md-4">
                        <div class="card h-100 shadow-hover">
                            <div class="card-header text-white" style="background-color: #667eea;">
                                <h3 class="mb-0">
                                    <i class="fas fa-shapes"></i> Задачи 1-6
                                </h3>
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    Геометрические задачи на вычисление. Параллелограммы, треугольники, окружности.
                                </p>
                                <div class="d-grid">
                                    <a href="1-6/index.html" class="btn btn-primary">
                                        <i class="fas fa-arrow-right"></i> Перейти к задачам
                                    </a>
                                </div>
                            </div>
                            <div class="card-footer text-muted">
                                <small><i class="fas fa-puzzle-piece"></i> Задача 1 ОГЭ 2026</small>
                            </div>
                        </div>
                    </div>

                    <!-- Раздел 7-12 -->
                    <div class="col-md-4">
                        <div class="card h-100 shadow-hover">
                            <div class="card-header text-white" style="background-color: #764ba2;">
                                <h3 class="mb-0">
                                    <i class="fas fa-gem"></i> Задачи 7-12
                                </h3>
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    Геометрические задачи на ромбы, углы и диагонали. Задачи повышенной сложности.
                                </p>
                                <div class="d-grid">
                                    <a href="7-12/index.html" class="btn btn-primary" style="background-color: #764ba2; border-color: #764ba2;">
                                        <i class="fas fa-arrow-right"></i> Перейти к задачам
                                    </a>
                                </div>
                            </div>
                            <div class="card-footer text-muted">
                                <small><i class="fas fa-puzzle-piece"></i> Задача 7 ОГЭ 2026</small>
                            </div>
                        </div>
                    </div>

                    <!-- Раздел 69-74 -->
                    <div class="col-md-4">
                        <div class="card h-100 shadow-hover">
                            <div class="card-header text-white" style="background-color: #ff9800;">
                                <h3 class="mb-0">
                                    <i class="fas fa-book-open"></i> Полный гайд 7-9 класс
                                </h3>
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    Полное руководство по геометрии для 7-9 классов. Теория, формулы, интерактивные упражнения.
                                </p>
                                <div class="d-grid">
                                    <a href="69-74/index.html" class="btn btn-warning">
                                        <i class="fas fa-arrow-right"></i> Перейти к гайду
                                    </a>
                                </div>
                            </div>
                            <div class="card-footer text-muted">
                                <small><i class="fas fa-info-circle"></i> Учебник Атанасяна</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Информация -->
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h3 class="mb-3">
                                    <i class="fas fa-info-circle"></i> Об этом разделе
                                </h3>
                                <p>
                                    Этот раздел содержит материалы для подготовки к ОГЭ по математике (геометрия).
                                    Все задачи взяты из открытого банка заданий ФИПИ и содержат подробные решения.
                                </p>
                                <ul>
                                    <li><strong>Интерактивные калькуляторы</strong> - проверьте решение с другими данными</li>
                                    <li><strong>Подробные решения</strong> - пошаговые объяснения каждой задачи</li>
                                    <li><strong>Визуализация</strong> - наглядные чертежи и схемы</li>
                                    <li><strong>Полный курс геометрии</strong> - от базовых понятий до сложных теорем</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

<?php include '../includes/footer.php'; ?>
