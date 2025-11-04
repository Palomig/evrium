/**
 * editor.js
 * Модуль редактора упражнений
 *
 * Предоставляет визуальный редактор для создания и правки JSON упражнений
 */

import { validateExercise, downloadExercise, loadExerciseFromFile } from './exercises-api.js';
import { renderExercise } from './renderer.js';

// Шаблоны упражнений
const TEMPLATES = {
    parallel: {
        "id": "001-parallel-transversal",
        "title": "Параллельные прямые и секущая",
        "class": "7",
        "description": "Две параллельные прямые и секущая. Подсветить равные углы.",
        "svg": {
            "width": 800,
            "height": 400,
            "elements": [
                {"type":"line","id":"l1","a":[50,60],"b":[750,60]},
                {"type":"line","id":"l2","a":[50,300],"b":[750,300]},
                {"type":"movable_line","id":"t","p1":[200,0],"p2":[400,400]}
            ]
        },
        "controls": [
            {"type":"checkbox","id":"show-degrees","label":"Показывать градусы"}
        ],
        "hints": ["Углы при пересечении параллельных прямых секущей имеют свойства:", "- Накрест лежащие углы равны", "- Соответственные углы равны", "- Односторонние углы в сумме дают 180°"],
        "solution": {"type":"visual_equality","pairs":[["angle1","angle2"],["angle3","angle4"]]}
    },
    triangle: {
        "id": "002-triangle-area",
        "title": "Треугольник в прямоугольнике — демонстрация площади",
        "class": "8",
        "description": "Треугольник с основанием, совпадающим с основанием прямоугольника. Регулируем высоту — демонстрация, что площадь треугольника = 1/2 площади прямоугольника.",
        "svg": {
            "width": 800,
            "height": 400,
            "elements": [
                {"type":"rect","id":"rect","x":100,"y":50,"width":600,"height":300},
                {"type":"triangle","id":"tri","p1":[100,350],"p2":[700,350],"p3":[400,100],"movable_p3":true}
            ]
        },
        "controls": [
            {"type":"checkbox","id":"show-areas","label":"Показывать площади"}
        ],
        "hints": ["Площадь треугольника = 1/2 * основание * высота", "Основание треугольника совпадает с основанием прямоугольника", "Высота треугольника равна высоте прямоугольника", "Поэтому площадь треугольника = 1/2 площади прямоугольника"],
        "solution": {"type":"numeric_check","expression":"area(tri) == 0.5 * area(rect)","tolerance":0.01}
    },
    empty: {
        "id": "new-exercise",
        "title": "Новое упражнение",
        "class": "7",
        "description": "Описание упражнения",
        "svg": {
            "width": 800,
            "height": 400,
            "elements": []
        },
        "controls": [],
        "hints": ["Добавьте подсказки здесь"],
        "solution": {}
    }
};

// Элементы DOM
const jsonEditor = document.getElementById('json-editor');
const previewContainer = document.getElementById('preview-container');
const validationMessage = document.getElementById('validation-message');
const fileInput = document.getElementById('file-input');

// Обработчики кнопок
document.getElementById('load-btn').addEventListener('click', () => {
    fileInput.click();
});

document.getElementById('save-btn').addEventListener('click', () => {
    try {
        const exercise = JSON.parse(jsonEditor.value);
        downloadExercise(exercise);
    } catch (error) {
        showValidationMessage('Ошибка: некорректный JSON', 'danger');
    }
});

document.getElementById('clear-btn').addEventListener('click', () => {
    if (confirm('Вы уверены, что хотите очистить редактор?')) {
        jsonEditor.value = '';
        previewContainer.innerHTML = `
            <div class="alert alert-info">
                <p class="mb-0">Редактор очищен. Введите JSON-код или загрузите файл.</p>
            </div>
        `;
        validationMessage.innerHTML = '';
    }
});

document.getElementById('load-example-btn').addEventListener('click', () => {
    jsonEditor.value = JSON.stringify(TEMPLATES.parallel, null, 2);
    showValidationMessage('Пример загружен. Нажмите "Предпросмотр" для отображения.', 'info');
});

document.getElementById('preview-btn').addEventListener('click', () => {
    previewExercise();
});

// Загрузка файла
fileInput.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    try {
        const exercise = await loadExerciseFromFile(file);
        jsonEditor.value = JSON.stringify(exercise, null, 2);
        showValidationMessage('Файл загружен успешно. Нажмите "Предпросмотр" для отображения.', 'success');
    } catch (error) {
        showValidationMessage(`Ошибка загрузки файла: ${error.message}`, 'danger');
    }

    // Сбрасываем input
    fileInput.value = '';
});

// Загрузка шаблонов
document.querySelectorAll('.load-template').forEach(btn => {
    btn.addEventListener('click', (e) => {
        const templateName = e.target.getAttribute('data-template');
        if (TEMPLATES[templateName]) {
            jsonEditor.value = JSON.stringify(TEMPLATES[templateName], null, 2);
            showValidationMessage(`Шаблон "${templateName}" загружен. Нажмите "Предпросмотр" для отображения.`, 'info');
        }
    });
});

/**
 * Отображает предпросмотр упражнения
 */
function previewExercise() {
    try {
        // Парсим JSON
        const exercise = JSON.parse(jsonEditor.value);

        // Валидируем
        const validation = validateExercise(exercise);

        if (!validation.valid) {
            showValidationMessage('Ошибки валидации: ' + validation.errors.join(', '), 'danger');
            return;
        }

        // Создаем контейнер для предпросмотра
        previewContainer.innerHTML = `
            <div class="exercise-card">
                <h5>${exercise.title}</h5>
                <span class="badge bg-primary">${exercise.class} класс</span>
                <p class="text-muted mt-2">${exercise.description}</p>

                <div class="exercise-container mt-3">
                    <svg id="preview-svg" class="svg-canvas"></svg>
                </div>

                <div id="preview-controls" class="controls-panel mt-3"></div>

                <div class="hints-panel mt-3">
                    <h6>Подсказки:</h6>
                    <ul>
                        ${exercise.hints.map(hint => `<li>${hint}</li>`).join('')}
                    </ul>
                </div>
            </div>
        `;

        // Рендерим упражнение
        const svg = document.getElementById('preview-svg');
        const controls = document.getElementById('preview-controls');

        renderExercise(exercise, svg, controls);

        showValidationMessage('Предпросмотр успешно сгенерирован!', 'success');

    } catch (error) {
        showValidationMessage(`Ошибка: ${error.message}`, 'danger');
        console.error('Ошибка предпросмотра:', error);
    }
}

/**
 * Показывает сообщение валидации
 * @param {string} message - Текст сообщения
 * @param {string} type - Тип сообщения (success, danger, warning, info)
 */
function showValidationMessage(message, type) {
    validationMessage.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

// Автосохранение в localStorage
let autoSaveTimeout;
jsonEditor.addEventListener('input', () => {
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(() => {
        try {
            const exercise = JSON.parse(jsonEditor.value);
            localStorage.setItem('editor_autosave', jsonEditor.value);
            console.log('Автосохранение выполнено');
        } catch (error) {
            // Игнорируем ошибки при неполном JSON
        }
    }, 2000);
});

// Восстановление при загрузке
window.addEventListener('DOMContentLoaded', () => {
    const autosaved = localStorage.getItem('editor_autosave');
    if (autosaved) {
        if (confirm('Найдено автосохранение. Восстановить?')) {
            jsonEditor.value = autosaved;
        }
    }
});
