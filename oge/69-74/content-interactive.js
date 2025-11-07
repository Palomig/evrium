// Интерактивные задания с полями ввода

content['basics-exercises'] = `
    <div class="section">
        <h2>Задания: Базовые понятия</h2>
        
        <div class="exercises">
            <h3>Интерактивные задания</h3>
            
            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 1:</strong> Два смежных угла относятся как 2:3. Найдите эти углы.
                </div>
                <div class="interactive-input">
                    <label>Меньший угол: <input type="number" id="basics-ex1-input" placeholder="Введите ответ"> °</label>
                    <button class="check-btn" onclick="checkAnswer('basics-ex1', 72)">Проверить</button>
                    <div id="basics-ex1-result" class="result-message"></div>
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать решение</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Пусть первый угол равен 2x, второй — 3x.<br>
                    Сумма смежных углов равна 180°:<br>
                    2x + 3x = 180°<br>
                    5x = 180°<br>
                    x = 36°<br>
                    Первый угол: 2 × 36° = 72°<br>
                    Второй угол: 3 × 36° = 108°<br>
                    <strong>Ответ:</strong> 72° и 108°
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 2:</strong> При пересечении двух прямых образовались четыре угла. Один из углов равен 40°. Найдите остальные три угла.<br>
                    Вертикальный угол: <input type="number" id="basics-ex2-1" placeholder="?" style="width: 60px"> °<br>
                    Смежный угол: <input type="number" id="basics-ex2-2" placeholder="?" style="width: 60px"> °
                </div>
                <button class="check-btn" onclick="checkMultipleAnswers('basics-ex2', [40, 140])">Проверить</button>
                <div id="basics-ex2-result" class="result-message"></div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать решение</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Вертикальный угол равен данному: 40°<br>
                    Смежный угол: 180° − 40° = 140°<br>
                    Второй смежный угол (вертикальный первому смежному): 140°<br>
                    <strong>Ответ:</strong> 40°, 140°, 40°, 140°
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 3:</strong> Две параллельные прямые пересечены секущей. Один из накрест лежащих углов равен 65°. Найдите соответственный ему угол.
                </div>
                <div class="interactive-input">
                    <label>Ответ: <input type="number" id="basics-ex3-input" placeholder="Введите ответ"> °</label>
                    <button class="check-btn" onclick="checkAnswer('basics-ex3', 65)">Проверить</button>
                    <div id="basics-ex3-result" class="result-message"></div>
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать решение</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    При параллельных прямых накрест лежащие углы равны: 65°<br>
                    Соответственный угол равен накрест лежащему: 65°<br>
                    <strong>Ответ:</strong> 65°
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 4:</strong> Луч делит развёрнутый угол на два угла так, что один из них в 4 раза больше другого. Найдите эти углы.
                </div>
                <div class="interactive-input">
                    <label>Меньший угол: <input type="number" id="basics-ex4-input" placeholder="Введите ответ"> °</label>
                    <button class="check-btn" onclick="checkAnswer('basics-ex4', 36)">Проверить</button>
                    <div id="basics-ex4-result" class="result-message"></div>
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать решение</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Пусть меньший угол равен x, тогда больший — 4x.<br>
                    x + 4x = 180°<br>
                    5x = 180°<br>
                    x = 36°<br>
                    Больший угол: 4 × 36° = 144°<br>
                    <strong>Ответ:</strong> 36° и 144°
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 5:</strong> Две прямые пересечены секущей. Сумма односторонних углов равна 180°. Параллельны ли эти прямые?
                </div>
                <div class="interactive-input">
                    <label>
                        <input type="radio" name="basics-ex5" value="yes" id="basics-ex5-yes"> Да<br>
                        <input type="radio" name="basics-ex5" value="no" id="basics-ex5-no"> Нет
                    </label><br>
                    <button class="check-btn" onclick="checkRadioAnswer('basics-ex5', 'yes')">Проверить</button>
                    <div id="basics-ex5-result" class="result-message"></div>
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать решение</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    По признаку параллельности: если сумма односторонних углов равна 180°, то прямые параллельны.<br>
                    <strong>Ответ:</strong> Да, прямые параллельны
                </div>
            </div>
        </div>
    </div>
`;

content['coords-exercises'] = `
    <div class="section">
        <h2>Задания: Координаты</h2>
        
        <div class="exercises">
            <h3>Интерактивные задания</h3>
            
            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 1:</strong> Найдите расстояние между точками A(0; 0) и B(3; 4).
                </div>
                <div class="interactive-input">
                    <label>Расстояние: <input type="number" id="coords-ex1-input" placeholder="Введите ответ"></label>
                    <button class="check-btn" onclick="checkAnswer('coords-ex1', 5)">Проверить</button>
                    <div id="coords-ex1-result" class="result-message"></div>
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать решение</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    $$d = \\sqrt{(3-0)^2 + (4-0)^2} = \\sqrt{9 + 16} = \\sqrt{25} = 5$$<br>
                    <strong>Ответ:</strong> 5
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 2:</strong> Точка M — середина отрезка AB, где A(2; 4) и B(6; 8). Найдите координаты точки M.<br>
                    x: <input type="number" id="coords-ex2-x" placeholder="?" style="width: 60px">
                    y: <input type="number" id="coords-ex2-y" placeholder="?" style="width: 60px">
                </div>
                <button class="check-btn" onclick="checkCoordinates('coords-ex2', 4, 6)">Проверить</button>
                <div id="coords-ex2-result" class="result-message"></div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать решение</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    $$x_M = \\frac{2+6}{2} = \\frac{8}{2} = 4$$<br>
                    $$y_M = \\frac{4+8}{2} = \\frac{12}{2} = 6$$<br>
                    <strong>Ответ:</strong> M(4; 6)
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 3:</strong> В какой четверти находится точка A(−3; 5)?
                </div>
                <div class="interactive-input">
                    <label>
                        <input type="radio" name="coords-ex3" value="1"> I четверть<br>
                        <input type="radio" name="coords-ex3" value="2"> II четверть<br>
                        <input type="radio" name="coords-ex3" value="3"> III четверть<br>
                        <input type="radio" name="coords-ex3" value="4"> IV четверть
                    </label><br>
                    <button class="check-btn" onclick="checkRadioAnswer('coords-ex3', '2')">Проверить</button>
                    <div id="coords-ex3-result" class="result-message"></div>
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать решение</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    x = −3 (отрицательный), y = 5 (положительный)<br>
                    Точка с отрицательным x и положительным y находится во II четверти.<br>
                    <strong>Ответ:</strong> II четверть
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 4:</strong> Найдите расстояние между точками C(1; 2) и D(4; 6).
                </div>
                <div class="interactive-input">
                    <label>Расстояние: <input type="number" step="0.1" id="coords-ex4-input" placeholder="Введите ответ"></label>
                    <button class="check-btn" onclick="checkAnswer('coords-ex4', 5, 0.1)">Проверить</button>
                    <div id="coords-ex4-result" class="result-message"></div>
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать решение</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    $$d = \\sqrt{(4-1)^2 + (6-2)^2} = \\sqrt{9 + 16} = \\sqrt{25} = 5$$<br>
                    <strong>Ответ:</strong> 5
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 5:</strong> Точки A(1; 3), B(5; 3) и C лежат на одной горизонтальной прямой. Найдите ординату (y) точки C, если её абсцисса (x) равна 3.
                </div>
                <div class="interactive-input">
                    <label>y = <input type="number" id="coords-ex5-input" placeholder="Введите ответ"></label>
                    <button class="check-btn" onclick="checkAnswer('coords-ex5', 3)">Проверить</button>
                    <div id="coords-ex5-result" class="result-message"></div>
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать решение</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Если точки лежат на одной горизонтальной прямой, то их ординаты равны.<br>
                    У точек A и B ордината y = 3, значит у точки C тоже y = 3.<br>
                    <strong>Ответ:</strong> y = 3, C(3; 3)
                </div>
            </div>
        </div>
    </div>
`;

// Функции для проверки ответов
function checkAnswer(exerciseId, correctAnswer, tolerance = 0) {
    const input = document.getElementById(exerciseId + '-input');
    const result = document.getElementById(exerciseId + '-result');
    const userAnswer = parseFloat(input.value);
    
    if (isNaN(userAnswer)) {
        result.textContent = '⚠️ Пожалуйста, введите число';
        result.className = 'result-message warning';
        return;
    }
    
    if (Math.abs(userAnswer - correctAnswer) <= tolerance) {
        result.textContent = '✅ Правильно!';
        result.className = 'result-message correct';
        input.style.borderColor = '#4caf50';
    } else {
        result.textContent = '❌ Неправильно. Попробуйте ещё раз!';
        result.className = 'result-message incorrect';
        input.style.borderColor = '#f44336';
    }
}

function checkMultipleAnswers(exerciseId, correctAnswers) {
    const inputs = [
        document.getElementById(exerciseId + '-1'),
        document.getElementById(exerciseId + '-2')
    ];
    const result = document.getElementById(exerciseId + '-result');
    
    const userAnswers = inputs.map(input => parseFloat(input.value));
    
    if (userAnswers.some(isNaN)) {
        result.textContent = '⚠️ Пожалуйста, заполните все поля';
        result.className = 'result-message warning';
        return;
    }
    
    let allCorrect = true;
    for (let i = 0; i < correctAnswers.length; i++) {
        if (userAnswers[i] !== correctAnswers[i]) {
            allCorrect = false;
            inputs[i].style.borderColor = '#f44336';
        } else {
            inputs[i].style.borderColor = '#4caf50';
        }
    }
    
    if (allCorrect) {
        result.textContent = '✅ Все ответы правильные!';
        result.className = 'result-message correct';
    } else {
        result.textContent = '❌ Есть ошибки. Попробуйте ещё раз!';
        result.className = 'result-message incorrect';
    }
}

function checkCoordinates(exerciseId, correctX, correctY) {
    const inputX = document.getElementById(exerciseId + '-x');
    const inputY = document.getElementById(exerciseId + '-y');
    const result = document.getElementById(exerciseId + '-result');
    
    const userX = parseFloat(inputX.value);
    const userY = parseFloat(inputY.value);
    
    if (isNaN(userX) || isNaN(userY)) {
        result.textContent = '⚠️ Пожалуйста, заполните обе координаты';
        result.className = 'result-message warning';
        return;
    }
    
    if (userX === correctX && userY === correctY) {
        result.textContent = '✅ Правильно!';
        result.className = 'result-message correct';
        inputX.style.borderColor = '#4caf50';
        inputY.style.borderColor = '#4caf50';
    } else {
        result.textContent = '❌ Неправильно. Попробуйте ещё раз!';
        result.className = 'result-message incorrect';
        if (userX !== correctX) inputX.style.borderColor = '#f44336';
        if (userY !== correctY) inputY.style.borderColor = '#f44336';
    }
}

function checkRadioAnswer(exerciseId, correctAnswer) {
    const radios = document.getElementsByName(exerciseId);
    const result = document.getElementById(exerciseId + '-result');
    
    let selectedValue = null;
    for (const radio of radios) {
        if (radio.checked) {
            selectedValue = radio.value;
            break;
        }
    }
    
    if (!selectedValue) {
        result.textContent = '⚠️ Пожалуйста, выберите ответ';
        result.className = 'result-message warning';
        return;
    }
    
    if (selectedValue === correctAnswer) {
        result.textContent = '✅ Правильно!';
        result.className = 'result-message correct';
    } else {
        result.textContent = '❌ Неправильно. Попробуйте ещё раз!';
        result.className = 'result-message incorrect';
    }
}
