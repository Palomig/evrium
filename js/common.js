// Общие функции для всех страниц

// Показать/скрыть ответ
function toggleAnswer(button) {
    const answer = button.nextElementSibling;
    if (answer.classList.contains('show')) {
        answer.classList.remove('show');
        button.textContent = 'Показать ответ';
    } else {
        answer.classList.add('show');
        button.textContent = 'Скрыть ответ';
    }
}

// Подсветка активного пункта меню
function highlightActiveMenu() {
    const currentPath = window.location.pathname;
    const menuLinks = document.querySelectorAll('.submenu-item a');

    menuLinks.forEach(link => {
        if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href'))) {
            link.classList.add('active');
        }
    });
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    highlightActiveMenu();
});

// Утилиты для JSXGraph

// Создать доску JSXGraph с настройками по умолчанию
function createBoard(containerId, options = {}) {
    const defaultOptions = {
        boundingbox: [-10, 10, 10, -10],
        axis: true,
        showNavigation: true,
        showCopyright: false,
        grid: true,
        pan: {
            enabled: true,
            needTwoFingers: false
        },
        zoom: {
            enabled: true,
            needTwoFingers: false,
            wheel: true
        }
    };

    return JXG.JSXGraph.initBoard(containerId, { ...defaultOptions, ...options });
}

// Обновить текстовое значение рядом с бегунком
function updateSliderValue(sliderId, valueId, formatter = (v) => v.toFixed(1)) {
    const slider = document.getElementById(sliderId);
    const valueDisplay = document.getElementById(valueId);

    if (slider && valueDisplay) {
        const updateValue = () => {
            valueDisplay.textContent = formatter(parseFloat(slider.value));
        };

        slider.addEventListener('input', updateValue);
        updateValue(); // Установить начальное значение

        return slider;
    }
}

// Создать бегунок с автоматическим обновлением значения
function createSlider(config) {
    const {
        sliderId,
        valueId,
        min = 0,
        max = 10,
        step = 0.1,
        initial = 5,
        formatter = (v) => v.toFixed(1),
        onChange = null
    } = config;

    const slider = document.getElementById(sliderId);
    const valueDisplay = document.getElementById(valueId);

    if (slider && valueDisplay) {
        slider.min = min;
        slider.max = max;
        slider.step = step;
        slider.value = initial;

        const updateValue = () => {
            const value = parseFloat(slider.value);
            valueDisplay.textContent = formatter(value);
            if (onChange) onChange(value);
        };

        slider.addEventListener('input', updateValue);
        updateValue(); // Установить начальное значение

        return {
            slider,
            getValue: () => parseFloat(slider.value),
            setValue: (v) => {
                slider.value = v;
                updateValue();
            }
        };
    }
}

// Создать точку, которую можно перемещать
function createDraggablePoint(board, x, y, options = {}) {
    const defaultOptions = {
        size: 4,
        fillColor: '#e74c3c',
        strokeColor: '#c0392b',
        strokeWidth: 2,
        highlight: true,
        name: '',
        label: { offset: [10, 10] }
    };

    return board.create('point', [x, y], { ...defaultOptions, ...options });
}

// Создать отрезок
function createSegment(board, p1, p2, options = {}) {
    const defaultOptions = {
        strokeColor: '#2c3e50',
        strokeWidth: 2,
        highlight: false
    };

    return board.create('segment', [p1, p2], { ...defaultOptions, ...options });
}

// Создать текст с формулой
function createText(board, x, y, text, options = {}) {
    const defaultOptions = {
        fontSize: 16,
        color: '#2c3e50',
        cssClass: 'jsx-text'
    };

    return board.create('text', [x, y, text], { ...defaultOptions, ...options });
}

// Вычислить площадь треугольника по координатам вершин
function triangleArea(p1, p2, p3) {
    const x1 = p1.X(), y1 = p1.Y();
    const x2 = p2.X(), y2 = p2.Y();
    const x3 = p3.X(), y3 = p3.Y();

    return Math.abs((x1 * (y2 - y3) + x2 * (y3 - y1) + x3 * (y1 - y2)) / 2);
}

// Вычислить длину отрезка
function distance(p1, p2) {
    return Math.sqrt(Math.pow(p2.X() - p1.X(), 2) + Math.pow(p2.Y() - p1.Y(), 2));
}

// Вычислить угол между тремя точками (в градусах)
function angle(p1, vertex, p2) {
    const v1x = p1.X() - vertex.X();
    const v1y = p1.Y() - vertex.Y();
    const v2x = p2.X() - vertex.X();
    const v2y = p2.Y() - vertex.Y();

    const dot = v1x * v2x + v1y * v2y;
    const len1 = Math.sqrt(v1x * v1x + v1y * v1y);
    const len2 = Math.sqrt(v2x * v2x + v2y * v2y);

    const angleRad = Math.acos(dot / (len1 * len2));
    return angleRad * 180 / Math.PI;
}
