<?php
// Подключаем конфигурацию
require_once 'config.php';

// Настройки страницы
$current_page = 'editor';
$page_title = 'Редактор упражнений';
$page_description = 'Создавайте и редактируйте упражнения с помощью JSON-формата';

// Подключаем header
include 'includes/header.php';
?>

    <!-- Контейнер с боковым меню -->
    <div class="layout-container">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Основной контент -->
        <main class="main-content">
            <div class="container-fluid my-4">
                <div class="row">
                    <div class="col-12">
                        <h1 class="mb-4">
                            <i class="fas fa-pencil-alt"></i> Редактор упражнений
                        </h1>
                        <p class="lead">
                            Создавайте и редактируйте упражнения с помощью JSON-формата.
                            Изменения отображаются в реальном времени.
                        </p>
                    </div>
                </div>

                <div class="row">
                    <!-- JSON редактор -->
                    <div class="col-lg-6 mb-4">
                        <div class="editor-container">
                            <div class="editor-toolbar">
                                <button id="load-btn" class="btn btn-primary btn-sm">
                                    <i class="fas fa-folder-open"></i> Загрузить JSON
                                </button>
                                <button id="save-btn" class="btn btn-success btn-sm">
                                    <i class="fas fa-save"></i> Скачать JSON
                                </button>
                                <button id="clear-btn" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i> Очистить
                                </button>
                                <button id="load-example-btn" class="btn btn-info btn-sm">
                                    <i class="fas fa-file-code"></i> Загрузить пример
                                </button>
                                <input type="file" id="file-input" accept=".json" style="display: none;">
                            </div>

                            <h4><i class="fas fa-code"></i> JSON код</h4>
                            <textarea id="json-editor" class="json-editor" placeholder='Введите JSON-код упражнения или загрузите файл...'></textarea>

                            <div class="mt-3">
                                <button id="preview-btn" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> Предпросмотр
                                </button>
                                <div id="validation-message" class="mt-2"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Предпросмотр -->
                    <div class="col-lg-6 mb-4">
                        <div class="editor-container">
                            <h4><i class="fas fa-desktop"></i> Предпросмотр</h4>
                            <div id="preview-container">
                                <div class="alert alert-info">
                                    <p class="mb-0">
                                        <i class="fas fa-info-circle"></i>
                                        Здесь будет отображаться предпросмотр вашего упражнения.
                                    </p>
                                    <p class="mb-0 mt-2">
                                        Введите JSON-код и нажмите кнопку "Предпросмотр".
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Шаблоны -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="editor-container">
                            <h4><i class="fas fa-layer-group"></i> Шаблоны упражнений</h4>
                            <p class="text-muted">Выберите шаблон для быстрого старта:</p>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="card shadow-hover">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-arrows-alt-h"></i> Параллельные прямые
                                            </h5>
                                            <p class="card-text small">
                                                Две параллельные прямые и секущая с демонстрацией равных углов.
                                            </p>
                                            <button class="btn btn-sm btn-primary load-template" data-template="parallel">
                                                <i class="fas fa-upload"></i> Загрузить
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card shadow-hover">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-chart-area"></i> Площадь треугольника
                                            </h5>
                                            <p class="card-text small">
                                                Треугольник в прямоугольнике с демонстрацией площади.
                                            </p>
                                            <button class="btn btn-sm btn-primary load-template" data-template="triangle">
                                                <i class="fas fa-upload"></i> Загрузить
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card shadow-hover">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-file"></i> Пустой шаблон
                                            </h5>
                                            <p class="card-text small">
                                                Базовый шаблон для создания собственного упражнения.
                                            </p>
                                            <button class="btn btn-sm btn-primary load-template" data-template="empty">
                                                <i class="fas fa-upload"></i> Загрузить
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

<?php
$page_scripts = '<script type="module" src="js/editor.js"></script>';
include 'includes/footer.php';
?>
