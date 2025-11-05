<?php
// Подключаем конфигурацию
require_once 'config.php';

// Настройки страницы
$current_page = 'docs';
$page_title = 'Документация';
$page_description = 'Документация по использованию интерактивного сайта по геометрии';

// Подключаем header
include 'includes/header.php';
?>

    <!-- Контейнер с боковым меню -->
    <div class="layout-container">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Основной контент -->
        <main class="main-content">
            <div class="container my-5">
                <h1 class="mb-4">
                    <i class="fas fa-book-open"></i> Документация
                </h1>

                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h2><i class="fas fa-info-circle"></i> О проекте</h2>
                        <p>
                            Интерактивный сайт для изучения геометрии по учебнику Л.С. Атанасяна (7-9 классы).
                            Полностью динамический сайт на PHP с использованием SVG для визуализации.
                        </p>

                        <h3 class="mt-4"><i class="fas fa-star"></i> Особенности</h3>
                        <ul>
                            <li><i class="fas fa-check text-success"></i> Динамический PHP сайт с удобной навигацией</li>
                            <li><i class="fas fa-check text-success"></i> Интерактивные SVG-упражнения</li>
                            <li><i class="fas fa-check text-success"></i> Адаптивный дизайн для мобильных устройств</li>
                            <li><i class="fas fa-check text-success"></i> Поддержка touch-событий</li>
                            <li><i class="fas fa-check text-success"></i> Визуальный редактор упражнений</li>
                            <li><i class="fas fa-check text-success"></i> JSON-формат для упражнений</li>
                            <li><i class="fas fa-check text-success"></i> Bootstrap 5 и Font Awesome через CDN</li>
                        </ul>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h2><i class="fas fa-folder-tree"></i> Структура проекта</h2>
                        <pre><code><?php echo htmlspecialchars(<<<'STRUCTURE'
/
├── index.php               # Главная страница
├── chapter.php             # Страница для отображения глав
├── examples.php            # Страница с примерами упражнений
├── editor.php              # Редактор упражнений
├── docs.php                # Документация
├── config.php              # Конфигурация (данные о классах и главах)
├── includes/               # Общие компоненты
│   ├── header.php          # Шапка сайта
│   ├── sidebar.php         # Боковое меню
│   └── footer.php          # Подвал сайта
├── css/
│   └── main.css            # Основные стили
├── js/
│   ├── app.js              # Главный модуль приложения
│   ├── renderer.js         # Рендеринг SVG элементов
│   ├── exercises-api.js    # API для работы с упражнениями
│   └── editor.js           # Логика редактора
├── exercises/
│   ├── 001-parallel-transversal.json
│   └── 002-triangle-area.json
└── assets/
    └── icons/              # SVG иконки
STRUCTURE
); ?></code></pre>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h2><i class="fas fa-code"></i> Формат JSON упражнений</h2>
                        <p>Каждое упражнение описывается JSON-файлом со следующей структурой:</p>

<pre><code class="language-json"><?php echo htmlspecialchars(<<<'JSON'
{
  "id": "unique-id",
  "title": "Название упражнения",
  "class": "7",
  "description": "Описание упражнения",
  "svg": {
    "width": 800,
    "height": 400,
    "elements": [
      {
        "type": "line",
        "id": "line1",
        "a": [x1, y1],
        "b": [x2, y2],
        "stroke": "#color",
        "strokeWidth": 2
      }
    ]
  },
  "controls": [
    {
      "type": "checkbox",
      "id": "control-id",
      "label": "Текст метки"
    }
  ],
  "hints": [
    "Подсказка 1",
    "Подсказка 2"
  ],
  "solution": {
    "type": "visual_equality"
  }
}
JSON
); ?></code></pre>

                        <h3 class="mt-4"><i class="fas fa-shapes"></i> Типы SVG элементов</h3>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Тип</th>
                                        <th>Описание</th>
                                        <th>Параметры</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>line</code></td>
                                        <td>Прямая линия</td>
                                        <td>a: [x1, y1], b: [x2, y2]</td>
                                    </tr>
                                    <tr>
                                        <td><code>movable_line</code></td>
                                        <td>Перемещаемая линия</td>
                                        <td>p1: [x1, y1], p2: [x2, y2]</td>
                                    </tr>
                                    <tr>
                                        <td><code>rect</code></td>
                                        <td>Прямоугольник</td>
                                        <td>x, y, width, height</td>
                                    </tr>
                                    <tr>
                                        <td><code>triangle</code></td>
                                        <td>Треугольник</td>
                                        <td>p1, p2, p3, movable_p3: true/false</td>
                                    </tr>
                                    <tr>
                                        <td><code>circle</code></td>
                                        <td>Окружность</td>
                                        <td>cx, cy, r</td>
                                    </tr>
                                    <tr>
                                        <td><code>point</code></td>
                                        <td>Точка</td>
                                        <td>x, y, r</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h3 class="mt-4"><i class="fas fa-sliders-h"></i> Типы контролов</h3>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-success">
                                    <tr>
                                        <th>Тип</th>
                                        <th>Описание</th>
                                        <th>Параметры</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>slider</code></td>
                                        <td>Ползунок</td>
                                        <td>min, max, value, target, prop</td>
                                    </tr>
                                    <tr>
                                        <td><code>checkbox</code></td>
                                        <td>Чекбокс</td>
                                        <td>id, label</td>
                                    </tr>
                                    <tr>
                                        <td><code>draggable_point</code></td>
                                        <td>Перетаскиваемая точка</td>
                                        <td>target, bounds</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h2><i class="fas fa-plus-circle"></i> Как добавить новое упражнение</h2>
                        <ol>
                            <li>
                                <i class="fas fa-arrow-right text-primary"></i>
                                Откройте <a href="editor.php"><i class="fas fa-pencil-alt"></i> редактор упражнений</a>
                            </li>
                            <li>
                                <i class="fas fa-arrow-right text-primary"></i>
                                Выберите шаблон или создайте JSON с нуля
                            </li>
                            <li>
                                <i class="fas fa-arrow-right text-primary"></i>
                                Отредактируйте JSON согласно формату
                            </li>
                            <li>
                                <i class="fas fa-arrow-right text-primary"></i>
                                Нажмите "Предпросмотр" для проверки
                            </li>
                            <li>
                                <i class="fas fa-arrow-right text-primary"></i>
                                Скачайте JSON файл
                            </li>
                            <li>
                                <i class="fas fa-arrow-right text-primary"></i>
                                Поместите файл в папку <code>/exercises/</code>
                            </li>
                            <li>
                                <i class="fas fa-arrow-right text-primary"></i>
                                Добавьте ID упражнения в массив <code>AVAILABLE_EXERCISES</code> в файле <code>js/exercises-api.js</code>
                            </li>
                        </ol>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h2><i class="fas fa-server"></i> Развертывание на хостинге</h2>
                        <ol>
                            <li>
                                <i class="fas fa-arrow-right text-success"></i>
                                Скачайте все файлы проекта
                            </li>
                            <li>
                                <i class="fas fa-arrow-right text-success"></i>
                                Загрузите их на ваш хостинг через FTP или панель управления
                            </li>
                            <li>
                                <i class="fas fa-arrow-right text-success"></i>
                                Убедитесь, что файл <code>index.php</code> находится в корневой директории
                            </li>
                            <li>
                                <i class="fas fa-arrow-right text-success"></i>
                                Убедитесь, что PHP версии 7.4 или выше установлен на хостинге
                            </li>
                            <li>
                                <i class="fas fa-arrow-right text-success"></i>
                                Откройте сайт в браузере
                            </li>
                        </ol>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Важно:</strong> Проект требует поддержки PHP на хостинге. Все библиотеки подключаются через CDN.
                        </div>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h2><i class="fas fa-cogs"></i> Технические требования</h2>

                        <h3 class="mt-3"><i class="fas fa-browser"></i> Браузеры</h3>
                        <ul>
                            <li><i class="fab fa-chrome"></i> Chrome/Edge 90+</li>
                            <li><i class="fab fa-firefox"></i> Firefox 88+</li>
                            <li><i class="fab fa-safari"></i> Safari 14+</li>
                            <li><i class="fas fa-mobile-alt"></i> Мобильные браузеры (iOS Safari, Chrome Mobile)</li>
                        </ul>

                        <h3 class="mt-4"><i class="fas fa-server"></i> Хостинг</h3>
                        <ul>
                            <li><i class="fas fa-check text-success"></i> Любой хостинг с поддержкой PHP 7.4+</li>
                            <li><i class="fas fa-check text-success"></i> Не требуется MySQL или другая СУБД</li>
                            <li><i class="fas fa-check text-success"></i> Все библиотеки подключаются через CDN (Bootstrap, Font Awesome, jQuery)</li>
                        </ul>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h2><i class="fas fa-mobile-alt"></i> Поддержка мобильных устройств</h2>
                        <ul>
                            <li><i class="fas fa-check text-success"></i> Адаптивный дизайн (responsive design)</li>
                            <li><i class="fas fa-check text-success"></i> Поддержка touch-событий для перетаскивания</li>
                            <li><i class="fas fa-check text-success"></i> Оптимизированные размеры кнопок (минимум 44px)</li>
                            <li><i class="fas fa-check text-success"></i> Viewport meta tag для корректного масштабирования</li>
                            <li><i class="fas fa-check text-success"></i> Touch-friendly контролы</li>
                        </ul>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h2><i class="fas fa-balance-scale"></i> Лицензия</h2>
                        <p>
                            Проект создан для образовательных целей. По учебнику Л.С. Атанасяна "Геометрия 7-9 классы".
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

<?php include 'includes/footer.php'; ?>
