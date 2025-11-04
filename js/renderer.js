/**
 * renderer.js
 * Модуль для рендеринга SVG упражнений
 *
 * Этот модуль отвечает за визуализацию геометрических элементов
 * и создание интерактивных контролов
 */

/**
 * Основная функция рендеринга упражнения
 * @param {Object} exercise - Объект упражнения из JSON
 * @param {SVGElement} svgElement - SVG элемент для рендеринга
 * @param {HTMLElement} controlsElement - Элемент для размещения контролов
 */
export function renderExercise(exercise, svgElement, controlsElement) {
    // Очищаем предыдущий контент
    svgElement.innerHTML = '';
    controlsElement.innerHTML = '';

    // Устанавливаем размеры SVG
    svgElement.setAttribute('width', exercise.svg.width);
    svgElement.setAttribute('height', exercise.svg.height);
    svgElement.setAttribute('viewBox', `0 0 ${exercise.svg.width} ${exercise.svg.height}`);

    // Сохраняем состояние элементов
    const state = {
        elements: {},
        showDegrees: false
    };

    // Рендерим все SVG элементы
    exercise.svg.elements.forEach(element => {
        renderElement(element, svgElement, state);
    });

    // Рендерим контролы
    if (exercise.controls) {
        exercise.controls.forEach(control => {
            renderControl(control, controlsElement, svgElement, state, exercise);
        });
    }

    // Сохраняем ссылку на упражнение в SVG элементе
    svgElement.exerciseData = { exercise, state };
}

/**
 * Рендерит отдельный SVG элемент
 * @param {Object} element - Описание элемента из JSON
 * @param {SVGElement} svg - SVG контейнер
 * @param {Object} state - Состояние упражнения
 */
function renderElement(element, svg, state) {
    let svgEl;

    switch (element.type) {
        case 'line':
            svgEl = createLine(element);
            break;

        case 'movable_line':
            svgEl = createMovableLine(element, svg, state);
            break;

        case 'rect':
            svgEl = createRect(element);
            break;

        case 'triangle':
            svgEl = createTriangle(element, svg, state);
            break;

        case 'circle':
            svgEl = createCircle(element);
            break;

        case 'point':
            svgEl = createPoint(element);
            break;

        default:
            console.warn(`Неизвестный тип элемента: ${element.type}`);
            return;
    }

    if (svgEl) {
        svg.appendChild(svgEl);
        state.elements[element.id] = { element, svgEl };
    }
}

/**
 * Создает линию
 */
function createLine(element) {
    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    line.setAttribute('x1', element.a[0]);
    line.setAttribute('y1', element.a[1]);
    line.setAttribute('x2', element.b[0]);
    line.setAttribute('y2', element.b[1]);
    line.setAttribute('stroke', element.stroke || '#333');
    line.setAttribute('stroke-width', element.strokeWidth || 2);
    line.setAttribute('id', element.id);
    return line;
}

/**
 * Создает перемещаемую линию
 */
function createMovableLine(element, svg, state) {
    const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    group.setAttribute('id', element.id);

    // Линия
    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    line.setAttribute('x1', element.p1[0]);
    line.setAttribute('y1', element.p1[1]);
    line.setAttribute('x2', element.p2[0]);
    line.setAttribute('y2', element.p2[1]);
    line.setAttribute('stroke', element.stroke || '#e74c3c');
    line.setAttribute('stroke-width', element.strokeWidth || 3);

    group.appendChild(line);

    // Точки для перетаскивания
    const point1 = createDraggablePoint(element.p1, (newPos) => {
        element.p1 = newPos;
        line.setAttribute('x1', newPos[0]);
        line.setAttribute('y1', newPos[1]);
    });

    const point2 = createDraggablePoint(element.p2, (newPos) => {
        element.p2 = newPos;
        line.setAttribute('x2', newPos[0]);
        line.setAttribute('y2', newPos[1]);
    });

    group.appendChild(point1);
    group.appendChild(point2);

    return group;
}

/**
 * Создает прямоугольник
 */
function createRect(element) {
    const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
    rect.setAttribute('x', element.x);
    rect.setAttribute('y', element.y);
    rect.setAttribute('width', element.width);
    rect.setAttribute('height', element.height);
    rect.setAttribute('fill', element.fill || 'none');
    rect.setAttribute('stroke', element.stroke || '#3498db');
    rect.setAttribute('stroke-width', element.strokeWidth || 2);
    rect.setAttribute('id', element.id);
    return rect;
}

/**
 * Создает треугольник
 */
function createTriangle(element, svg, state) {
    const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    group.setAttribute('id', element.id);

    // Полигон треугольника
    const polygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');

    function updatePolygon() {
        const points = `${element.p1[0]},${element.p1[1]} ${element.p2[0]},${element.p2[1]} ${element.p3[0]},${element.p3[1]}`;
        polygon.setAttribute('points', points);
    }

    updatePolygon();
    polygon.setAttribute('fill', element.fill || 'rgba(52, 152, 219, 0.3)');
    polygon.setAttribute('stroke', element.stroke || '#3498db');
    polygon.setAttribute('stroke-width', element.strokeWidth || 2);

    group.appendChild(polygon);

    // Если p3 перемещаемая
    if (element.movable_p3) {
        const point3 = createDraggablePoint(element.p3, (newPos) => {
            element.p3 = newPos;
            updatePolygon();
        });
        group.appendChild(point3);
    }

    return group;
}

/**
 * Создает окружность
 */
function createCircle(element) {
    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    circle.setAttribute('cx', element.cx);
    circle.setAttribute('cy', element.cy);
    circle.setAttribute('r', element.r);
    circle.setAttribute('fill', element.fill || 'none');
    circle.setAttribute('stroke', element.stroke || '#9b59b6');
    circle.setAttribute('stroke-width', element.strokeWidth || 2);
    circle.setAttribute('id', element.id);
    return circle;
}

/**
 * Создает точку
 */
function createPoint(element) {
    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    circle.setAttribute('cx', element.x);
    circle.setAttribute('cy', element.y);
    circle.setAttribute('r', element.r || 4);
    circle.setAttribute('fill', element.fill || '#2c3e50');
    circle.setAttribute('id', element.id);
    return circle;
}

/**
 * Создает перетаскиваемую точку
 */
function createDraggablePoint(position, onMove) {
    const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    group.classList.add('draggable-point');

    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    circle.setAttribute('cx', position[0]);
    circle.setAttribute('cy', position[1]);
    circle.setAttribute('r', 6);
    circle.setAttribute('fill', '#e74c3c');
    circle.setAttribute('stroke', 'white');
    circle.setAttribute('stroke-width', 2);

    group.appendChild(circle);

    // Drag functionality
    let isDragging = false;

    const startDrag = (e) => {
        isDragging = true;
        e.preventDefault();
    };

    const drag = (e) => {
        if (!isDragging) return;

        e.preventDefault();

        const svg = group.ownerSVGElement;
        const pt = svg.createSVGPoint();

        // Поддержка touch и mouse событий
        if (e.touches) {
            pt.x = e.touches[0].clientX;
            pt.y = e.touches[0].clientY;
        } else {
            pt.x = e.clientX;
            pt.y = e.clientY;
        }

        const svgP = pt.matrixTransform(svg.getScreenCTM().inverse());

        const newPos = [Math.round(svgP.x), Math.round(svgP.y)];

        circle.setAttribute('cx', newPos[0]);
        circle.setAttribute('cy', newPos[1]);

        if (onMove) {
            onMove(newPos);
        }
    };

    const endDrag = () => {
        isDragging = false;
    };

    // Mouse events
    group.addEventListener('mousedown', startDrag);
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', endDrag);

    // Touch events
    group.addEventListener('touchstart', startDrag);
    document.addEventListener('touchmove', drag);
    document.addEventListener('touchend', endDrag);

    return group;
}

/**
 * Рендерит контрол
 */
function renderControl(control, container, svg, state, exercise) {
    const controlDiv = document.createElement('div');
    controlDiv.className = 'control-group';

    switch (control.type) {
        case 'slider':
            createSlider(control, controlDiv, svg, state);
            break;

        case 'checkbox':
            createCheckbox(control, controlDiv, svg, state);
            break;

        case 'draggable_point':
            // Уже обработано в renderElement
            break;

        default:
            console.warn(`Неизвестный тип контрола: ${control.type}`);
            return;
    }

    container.appendChild(controlDiv);
}

/**
 * Создает слайдер
 */
function createSlider(control, container, svg, state) {
    const label = document.createElement('label');
    label.textContent = control.label || control.id;

    const slider = document.createElement('input');
    slider.type = 'range';
    slider.min = control.min || 0;
    slider.max = control.max || 100;
    slider.value = control.value || ((control.min + control.max) / 2);

    const valueSpan = document.createElement('span');
    valueSpan.textContent = slider.value;
    valueSpan.style.marginLeft = '10px';
    valueSpan.style.fontWeight = 'bold';

    slider.addEventListener('input', (e) => {
        valueSpan.textContent = e.target.value;

        // Обновляем элемент на основе контрола
        if (control.target && state.elements[control.target]) {
            // Здесь можно добавить логику изменения элементов
            // В зависимости от control.prop
        }
    });

    container.appendChild(label);
    container.appendChild(slider);
    container.appendChild(valueSpan);
}

/**
 * Создает чекбокс
 */
function createCheckbox(control, container, svg, state) {
    const label = document.createElement('label');
    label.style.display = 'flex';
    label.style.alignItems = 'center';
    label.style.cursor = 'pointer';

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.id = control.id;
    checkbox.style.marginRight = '8px';

    const text = document.createElement('span');
    text.textContent = control.label || control.id;

    checkbox.addEventListener('change', (e) => {
        state[control.id] = e.target.checked;

        // Если это чекбокс "показывать градусы/площади"
        if (control.id === 'show-degrees' || control.id === 'show-areas') {
            // Здесь можно добавить логику отображения измерений
            console.log(`${control.id}: ${e.target.checked}`);
        }
    });

    label.appendChild(checkbox);
    label.appendChild(text);
    container.appendChild(label);
}

/**
 * Вычисляет площадь треугольника по трем точкам
 */
export function calculateTriangleArea(p1, p2, p3) {
    return Math.abs((p1[0] * (p2[1] - p3[1]) + p2[0] * (p3[1] - p1[1]) + p3[0] * (p1[1] - p2[1])) / 2);
}

/**
 * Вычисляет расстояние между двумя точками
 */
export function calculateDistance(p1, p2) {
    return Math.sqrt(Math.pow(p2[0] - p1[0], 2) + Math.pow(p2[1] - p1[1], 2));
}

/**
 * Вычисляет угол между тремя точками (в градусах)
 */
export function calculateAngle(p1, p2, p3) {
    const a = calculateDistance(p2, p3);
    const b = calculateDistance(p1, p3);
    const c = calculateDistance(p1, p2);

    const cosAngle = (a * a + c * c - b * b) / (2 * a * c);
    const angle = Math.acos(cosAngle) * (180 / Math.PI);

    return angle;
}
