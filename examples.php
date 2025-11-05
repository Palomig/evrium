<?php
// Подключаем конфигурацию
require_once 'config.php';

// Настройки страницы
$current_page = 'examples';
$page_title = 'Примеры упражнений';
$page_description = 'Интерактивные упражнения по геометрии';

// Подключаем header
include 'includes/header.php';
?>

    <!-- Контейнер с боковым меню -->
    <div class="layout-container">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Основной контент -->
        <main class="main-content">
            <div class="container my-4">
                <div class="row">
                    <div class="col-12">
                        <h1 class="mb-4">
                            <i class="fas fa-puzzle-piece"></i> Интерактивные упражнения
                        </h1>
                        <p class="lead">
                            Здесь представлены интерактивные упражнения по геометрии.
                            Вы можете взаимодействовать с фигурами, изменять параметры и наблюдать за результатами.
                        </p>
                    </div>
                </div>

                <!-- Список упражнений -->
                <div id="exercises-list" class="row">
                    <!-- Упражнения будут загружены динамически -->
                    <div class="col-12 text-center my-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                        <p class="mt-3">Загружаем упражнения...</p>
                    </div>
                </div>

                <!-- Контейнер для отображения одного упражнения (если открыто по ID) -->
                <div id="single-exercise-container" style="display: none;">
                    <div class="exercise-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h3 id="exercise-title"></h3>
                                <span id="exercise-class-badge" class="badge bg-primary"></span>
                            </div>
                            <a href="examples.php" class="btn btn-sm btn-secondary">
                                <i class="fas fa-arrow-left"></i> Все упражнения
                            </a>
                        </div>
                        <p id="exercise-description" class="text-muted"></p>

                        <!-- SVG Canvas -->
                        <div class="exercise-container">
                            <svg id="exercise-svg" class="svg-canvas"></svg>
                        </div>

                        <!-- Контролы -->
                        <div id="exercise-controls" class="controls-panel">
                            <!-- Контролы будут добавлены динамически -->
                        </div>

                        <!-- Подсказки -->
                        <div id="exercise-hints" class="hints-panel" style="display: none;">
                            <h5><i class="fas fa-lightbulb"></i> Подсказки</h5>
                            <ul id="hints-list"></ul>
                        </div>

                        <!-- Действия -->
                        <div class="exercise-actions">
                            <button id="show-hints-btn" class="btn btn-warning">
                                <i class="fas fa-question-circle"></i> Показать подсказки
                            </button>
                            <button id="reset-btn" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Сбросить
                            </button>
                            <button id="download-json-btn" class="btn btn-info">
                                <i class="fas fa-download"></i> Скачать JSON
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

<?php
$page_scripts = <<<'SCRIPTS'
<!-- App JS Modules -->
<script type="module">
    import { loadExercise, loadAllExercises } from './js/exercises-api.js';
    import { renderExercise } from './js/renderer.js';

    // Проверяем, есть ли ID упражнения в URL
    const urlParams = new URLSearchParams(window.location.search);
    const exerciseId = urlParams.get('id');

    if (exerciseId) {
        // Показываем одно упражнение
        showSingleExercise(exerciseId);
    } else {
        // Показываем список всех упражнений
        showAllExercises();
    }

    /**
     * Отображает одно упражнение
     */
    async function showSingleExercise(id) {
        try {
            const exercise = await loadExercise(id);
            document.getElementById('single-exercise-container').style.display = 'block';
            document.getElementById('exercises-list').style.display = 'none';

            // Заполняем информацию
            document.getElementById('exercise-title').textContent = exercise.title;
            document.getElementById('exercise-class-badge').textContent = `${exercise.class} класс`;
            document.getElementById('exercise-description').textContent = exercise.description;

            // Рендерим упражнение
            const svg = document.getElementById('exercise-svg');
            const controls = document.getElementById('exercise-controls');
            renderExercise(exercise, svg, controls);

            // Обработчики кнопок
            document.getElementById('show-hints-btn').addEventListener('click', () => {
                const hintsPanel = document.getElementById('exercise-hints');
                if (hintsPanel.style.display === 'none') {
                    hintsPanel.style.display = 'block';
                    document.getElementById('show-hints-btn').innerHTML = '<i class="fas fa-eye-slash"></i> Скрыть подсказки';

                    // Заполняем подсказки
                    const hintsList = document.getElementById('hints-list');
                    hintsList.innerHTML = '';
                    exercise.hints.forEach(hint => {
                        const li = document.createElement('li');
                        li.textContent = hint;
                        hintsList.appendChild(li);
                    });
                } else {
                    hintsPanel.style.display = 'none';
                    document.getElementById('show-hints-btn').innerHTML = '<i class="fas fa-question-circle"></i> Показать подсказки';
                }
            });

            document.getElementById('reset-btn').addEventListener('click', () => {
                renderExercise(exercise, svg, controls);
            });

            document.getElementById('download-json-btn').addEventListener('click', () => {
                const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(exercise, null, 2));
                const downloadAnchor = document.createElement('a');
                downloadAnchor.setAttribute("href", dataStr);
                downloadAnchor.setAttribute("download", `${exercise.id}.json`);
                downloadAnchor.click();
            });

        } catch (error) {
            console.error('Ошибка загрузки упражнения:', error);
            document.getElementById('single-exercise-container').innerHTML = `
                <div class="alert alert-danger">
                    <h4><i class="fas fa-exclamation-triangle"></i> Ошибка загрузки</h4>
                    <p>Не удалось загрузить упражнение: ${error.message}</p>
                    <a href="examples.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Вернуться к списку
                    </a>
                </div>
            `;
            document.getElementById('single-exercise-container').style.display = 'block';
        }
    }

    /**
     * Отображает список всех упражнений
     */
    async function showAllExercises() {
        const container = document.getElementById('exercises-list');

        try {
            const exercises = await loadAllExercises();

            if (exercises.length === 0) {
                container.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-info">
                            <h4><i class="fas fa-info-circle"></i> Упражнения пока не добавлены</h4>
                            <p>Используйте <a href="editor.php">редактор</a> для создания новых упражнений.</p>
                        </div>
                    </div>
                `;
                return;
            }

            container.innerHTML = '';
            exercises.forEach(exercise => {
                const col = document.createElement('div');
                col.className = 'col-md-6 col-lg-4 mb-4';

                col.innerHTML = `
                    <div class="card h-100 shadow-hover">
                        <div class="card-header">
                            <h5 class="mb-0">${exercise.title}</h5>
                            <span class="badge bg-primary">${exercise.class} класс</span>
                        </div>
                        <div class="card-body">
                            <p class="card-text">${exercise.description}</p>
                        </div>
                        <div class="card-footer">
                            <a href="examples.php?id=${exercise.id}" class="btn btn-primary btn-sm">
                                <i class="fas fa-play"></i> Открыть
                            </a>
                            <button class="btn btn-secondary btn-sm download-json" data-id="${exercise.id}">
                                <i class="fas fa-download"></i> JSON
                            </button>
                        </div>
                    </div>
                `;

                container.appendChild(col);
            });

            // Обработчики для скачивания JSON
            document.querySelectorAll('.download-json').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const id = e.target.closest('button').getAttribute('data-id');
                    const exercise = await loadExercise(id);
                    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(exercise, null, 2));
                    const downloadAnchor = document.createElement('a');
                    downloadAnchor.setAttribute("href", dataStr);
                    downloadAnchor.setAttribute("download", `${exercise.id}.json`);
                    downloadAnchor.click();
                });
            });

        } catch (error) {
            console.error('Ошибка загрузки списка упражнений:', error);
            container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">
                        <h4><i class="fas fa-exclamation-triangle"></i> Ошибка загрузки</h4>
                        <p>Не удалось загрузить список упражнений: ${error.message}</p>
                    </div>
                </div>
            `;
        }
    }
</script>
SCRIPTS;

include 'includes/footer.php';
?>
