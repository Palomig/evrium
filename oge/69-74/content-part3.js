// Контент для площадей, векторов и заданий

content['area-triangles'] = `
    <div class="section">
        <h2>Площадь треугольника</h2>
        
        <h3>Основная формула</h3>
        <div class="formula">
            S = ½ × a × h<br>
            где a — основание, h — высота к этому основанию
        </div>

        <svg width="400" height="350" viewBox="0 0 400 350">
            <polygon points="200,50 100,300 300,300" fill="none" stroke="#667eea" stroke-width="3"/>
            <line x1="200" y1="50" x2="200" y2="300" stroke="#ff5722" stroke-width="2" stroke-dasharray="5,5"/>
            <line x1="200" y1="300" x2="215" y2="300" stroke="#ff9800" stroke-width="2"/>
            <line x1="200" y1="300" x2="200" y2="285" stroke="#ff9800" stroke-width="2"/>
            
            <text x="200" y="40" text-anchor="middle" font-size="18" font-weight="bold">A</text>
            <text x="90" y="315" text-anchor="end" font-size="18" font-weight="bold">B</text>
            <text x="310" y="315" text-anchor="start" font-size="18" font-weight="bold">C</text>
            <text x="200" y="185" text-anchor="end" font-size="16" fill="#ff5722">h</text>
            <text x="200" y="330" text-anchor="middle" font-size="16" fill="#4caf50">a</text>
        </svg>

        <h3>Формула через две стороны и угол</h3>
        <div class="formula">
            S = ½ × a × b × sin(γ)<br>
            где a и b — две стороны, γ — угол между ними
        </div>

        <h3>Формула Герона</h3>
        <div class="theorem">
            Для треугольника со сторонами a, b, c:
        </div>
        <div class="formula">
            S = √(p(p-a)(p-b)(p-c))<br>
            где p = (a+b+c)/2 — полупериметр
        </div>

        <h3>Площадь прямоугольного треугольника</h3>
        <div class="formula">
            S = ½ × a × b<br>
            где a и b — катеты
        </div>

        <svg width="400" height="350" viewBox="0 0 400 350">
            <polygon points="150,80 150,300 350,300" fill="none" stroke="#667eea" stroke-width="3"/>
            <line x1="150" y1="300" x2="165" y2="300" stroke="#ff5722" stroke-width="2"/>
            <line x1="150" y1="300" x2="150" y2="285" stroke="#ff5722" stroke-width="2"/>
            
            <text x="150" y="70" text-anchor="middle" font-size="18" font-weight="bold">A</text>
            <text x="140" y="315" text-anchor="end" font-size="18" font-weight="bold">B</text>
            <text x="360" y="315" text-anchor="start" font-size="18" font-weight="bold">C</text>
            <text x="135" y="190" text-anchor="end" font-size="16" fill="#4caf50">a</text>
            <text x="250" y="330" text-anchor="middle" font-size="16" fill="#4caf50">b</text>
        </svg>

        <h3>Площадь равностороннего треугольника</h3>
        <div class="formula">
            S = (a²√3)/4<br>
            где a — сторона треугольника
        </div>
    </div>
`;

content['area-quadrilaterals'] = `
    <div class="section">
        <h2>Площадь четырёхугольников</h2>
        
        <h3>Площадь параллелограмма</h3>
        <div class="formula">
            S = a × h<br>
            где a — основание, h — высота
        </div>

        <svg width="500" height="300" viewBox="0 0 500 300">
            <polygon points="100,80 350,80 420,250 170,250" fill="none" stroke="#667eea" stroke-width="3"/>
            <line x1="350" y1="80" x2="350" y2="250" stroke="#ff5722" stroke-width="2" stroke-dasharray="5,5"/>
            <text x="250" y="280" text-anchor="middle" font-size="16" fill="#4caf50">a</text>
            <text x="365" y="165" text-anchor="start" font-size="16" fill="#ff5722">h</text>
        </svg>

        <div class="formula">
            S = a × b × sin(α)<br>
            где a, b — стороны, α — угол между ними
        </div>

        <h3>Площадь прямоугольника</h3>
        <div class="formula">
            S = a × b<br>
            где a и b — стороны прямоугольника
        </div>

        <h3>Площадь ромба</h3>
        <div class="formula">
            S = ½ × d₁ × d₂<br>
            где d₁ и d₂ — диагонали ромба
        </div>

        <svg width="400" height="400" viewBox="0 0 400 400">
            <polygon points="200,50 350,200 200,350 50,200" fill="none" stroke="#667eea" stroke-width="3"/>
            <line x1="200" y1="50" x2="200" y2="350" stroke="#ff5722" stroke-width="2" stroke-dasharray="5,5"/>
            <line x1="50" y1="200" x2="350" y2="200" stroke="#4caf50" stroke-width="2" stroke-dasharray="5,5"/>
            <text x="210" y="200" text-anchor="start" font-size="16" fill="#ff5722">d₁</text>
            <text x="200" y="215" text-anchor="middle" font-size="16" fill="#4caf50">d₂</text>
        </svg>

        <h3>Площадь квадрата</h3>
        <div class="formula">
            S = a²<br>
            где a — сторона квадрата
        </div>

        <div class="formula">
            S = ½ × d²<br>
            где d — диагональ квадрата
        </div>

        <h3>Площадь трапеции</h3>
        <div class="formula">
            S = ½ × (a + b) × h<br>
            где a и b — основания, h — высота
        </div>

        <svg width="500" height="350" viewBox="0 0 500 350">
            <polygon points="150,80 350,80 420,280 100,280" fill="none" stroke="#667eea" stroke-width="3"/>
            <line x1="350" y1="80" x2="350" y2="280" stroke="#ff5722" stroke-width="2" stroke-dasharray="5,5"/>
            <text x="250" y="70" text-anchor="middle" font-size="16" fill="#4caf50">a</text>
            <text x="260" y="300" text-anchor="middle" font-size="16" fill="#4caf50">b</text>
            <text x="365" y="180" text-anchor="start" font-size="16" fill="#ff5722">h</text>
        </svg>

        <div class="formula">
            S = m × h<br>
            где m — средняя линия, h — высота
        </div>
    </div>
`;

content['area-circles'] = `
    <div class="section">
        <h2>Площадь круга и его частей</h2>
        
        <h3>Площадь круга</h3>
        <div class="formula">
            S = πr²<br>
            где r — радиус круга
        </div>

        <svg width="400" height="400" viewBox="0 0 400 400">
            <circle cx="200" cy="200" r="120" fill="none" stroke="#667eea" stroke-width="3"/>
            <line x1="200" y1="200" x2="320" y2="200" stroke="#ff5722" stroke-width="2"/>
            <circle cx="200" cy="200" r="5" fill="#764ba2"/>
            <text x="260" y="190" text-anchor="middle" font-size="16" fill="#ff5722">r</text>
        </svg>

        <h3>Длина окружности</h3>
        <div class="formula">
            C = 2πr = πd<br>
            где r — радиус, d — диаметр
        </div>

        <h3>Площадь сектора</h3>
        <div class="theorem">
            Сектор — часть круга, ограниченная двумя радиусами и дугой.
        </div>

        <svg width="400" height="400" viewBox="0 0 400 400">
            <path d="M 200 200 L 320 200 A 120 120 0 0 1 260 304 Z" fill="#e3f2fd" stroke="#667eea" stroke-width="3"/>
            <line x1="200" y1="200" x2="320" y2="200" stroke="#ff5722" stroke-width="2"/>
            <line x1="200" y1="200" x2="260" y2="304" stroke="#ff5722" stroke-width="2"/>
            <path d="M 240 200 A 40 40 0 0 1 225 235" fill="none" stroke="#ff9800" stroke-width="2"/>
            <text x="230" y="220" text-anchor="middle" font-size="16" fill="#ff9800">α</text>
        </svg>

        <div class="formula">
            S = (πr²α)/360°<br>
            где α — центральный угол в градусах
        </div>

        <div class="formula">
            S = ½r²α<br>
            где α — центральный угол в радианах
        </div>

        <h3>Длина дуги</h3>
        <div class="formula">
            l = (2πrα)/360° = (πrα)/180°<br>
            где α — центральный угол в градусах
        </div>
    </div>
`;

content['vectors-basic'] = `
    <div class="section">
        <h2>Векторы: Основные понятия</h2>
        
        <div class="theorem">
            <strong>Вектор</strong> — направленный отрезок, имеющий начало и конец.
        </div>

        <svg width="500" height="200" viewBox="0 0 500 200">
            <defs>
                <marker id="arrowhead" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto">
                    <polygon points="0 0, 10 3, 0 6" fill="#667eea" />
                </marker>
            </defs>
            <line x1="100" y1="100" x2="400" y2="100" stroke="#667eea" stroke-width="3" marker-end="url(#arrowhead)"/>
            <circle cx="100" cy="100" r="5" fill="#764ba2"/>
            <circle cx="400" cy="100" r="5" fill="#764ba2"/>
            <text x="100" y="85" text-anchor="middle" font-size="16" font-weight="bold">A</text>
            <text x="400" y="85" text-anchor="middle" font-size="16" font-weight="bold">B</text>
            <text x="250" y="85" text-anchor="middle" font-size="18" fill="#667eea">AB⃗</text>
        </svg>

        <p>Обозначение: AB⃗ или a⃗</p>

        <h3>Длина (модуль) вектора</h3>
        <div class="formula">
            |AB⃗| или |a⃗| — длина вектора
        </div>

        <h3>Равные векторы</h3>
        <p>Векторы равны, если они имеют одинаковую длину и одинаковое направление.</p>

        <svg width="500" height="300" viewBox="0 0 500 300">
            <defs>
                <marker id="arrow1" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto">
                    <polygon points="0 0, 10 3, 0 6" fill="#667eea" />
                </marker>
            </defs>
            <line x1="100" y1="100" x2="250" y2="100" stroke="#667eea" stroke-width="3" marker-end="url(#arrow1)"/>
            <line x1="100" y1="200" x2="250" y2="200" stroke="#667eea" stroke-width="3" marker-end="url(#arrow1)"/>
            <line x1="300" y1="150" x2="450" y2="150" stroke="#667eea" stroke-width="3" marker-end="url(#arrow1)"/>
            <text x="175" y="85" text-anchor="middle" font-size="16" fill="#667eea">a⃗</text>
            <text x="175" y="185" text-anchor="middle" font-size="16" fill="#667eea">b⃗</text>
            <text x="375" y="135" text-anchor="middle" font-size="16" fill="#667eea">c⃗</text>
            <text x="250" y="150" text-anchor="middle" font-size="18">=</text>
        </svg>

        <h3>Нулевой вектор</h3>
        <p>Вектор, у которого начало и конец совпадают: 0⃗</p>
        <p>Длина нулевого вектора равна нулю.</p>

        <h3>Коллинеарные векторы</h3>
        <p>Векторы называются коллинеарными, если они лежат на одной прямой или на параллельных прямых.</p>

        <svg width="500" height="250" viewBox="0 0 500 250">
            <defs>
                <marker id="arrow2" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto">
                    <polygon points="0 0, 10 3, 0 6" fill="#667eea" />
                </marker>
                <marker id="arrow3" markerWidth="10" markerHeight="10" refX="0" refY="3" orient="auto">
                    <polygon points="10 0, 0 3, 10 6" fill="#ff5722" />
                </marker>
            </defs>
            <line x1="50" y1="100" x2="200" y2="100" stroke="#667eea" stroke-width="3" marker-end="url(#arrow2)"/>
            <line x1="400" y1="100" x2="250" y2="100" stroke="#ff5722" stroke-width="3" marker-end="url(#arrow3)"/>
            <text x="125" y="85" text-anchor="middle" font-size="16" fill="#667eea">a⃗</text>
            <text x="325" y="85" text-anchor="middle" font-size="16" fill="#ff5722">b⃗</text>
            <text x="225" y="140" text-anchor="middle" font-size="14">Сонаправленные: a⃗ ↑↑ b⃗</text>
            
            <line x1="50" y1="200" x2="200" y2="200" stroke="#667eea" stroke-width="3" marker-end="url(#arrow2)"/>
            <line x1="250" y1="200" x2="400" y2="200" stroke="#ff5722" stroke-width="3" marker-end="url(#arrow3)"/>
            <text x="125" y="185" text-anchor="middle" font-size="16" fill="#667eea">a⃗</text>
            <text x="325" y="185" text-anchor="middle" font-size="16" fill="#ff5722">b⃗</text>
            <text x="225" y="240" text-anchor="middle" font-size="14">Противоположно направленные: a⃗ ↑↓ b⃗</text>
        </svg>
    </div>
`;

content['vectors-operations'] = `
    <div class="section">
        <h2>Операции с векторами</h2>
        
        <h3>Сложение векторов</h3>
        
        <div class="theorem">
            <strong>Правило треугольника:</strong><br>
            Чтобы сложить векторы a⃗ и b⃗, нужно от конца вектора a⃗ отложить вектор b⃗.
        </div>

        <svg width="500" height="300" viewBox="0 0 500 300">
            <defs>
                <marker id="arrow-a" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto">
                    <polygon points="0 0, 10 3, 0 6" fill="#667eea" />
                </marker>
                <marker id="arrow-b" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto">
                    <polygon points="0 0, 10 3, 0 6" fill="#ff5722" />
                </marker>
                <marker id="arrow-sum" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto">
                    <polygon points="0 0, 10 3, 0 6" fill="#4caf50" />
                </marker>
            </defs>
            <line x1="100" y1="200" x2="250" y2="150" stroke="#667eea" stroke-width="3" marker-end="url(#arrow-a)"/>
            <line x1="250" y1="150" x2="350" y2="100" stroke="#ff5722" stroke-width="3" marker-end="url(#arrow-b)"/>
            <line x1="100" y1="200" x2="350" y2="100" stroke="#4caf50" stroke-width="3" marker-end="url(#arrow-sum)" stroke-dasharray="5,5"/>
            <text x="175" y="165" text-anchor="middle" font-size="16" fill="#667eea">a⃗</text>
            <text x="300" y="115" text-anchor="middle" font-size="16" fill="#ff5722">b⃗</text>
            <text x="225" y="135" text-anchor="middle" font-size="16" fill="#4caf50">a⃗ + b⃗</text>
        </svg>

        <div class="theorem">
            <strong>Правило параллелограмма:</strong><br>
            Векторы a⃗ и b⃗ откладываются от одной точки, достраивается параллелограмм. Диагональ — это сумма.
        </div>

        <h3>Вычитание векторов</h3>
        <div class="formula">
            a⃗ - b⃗ = a⃗ + (-b⃗)
        </div>

        <p>Противоположный вектор (-b⃗) имеет ту же длину, но противоположное направление.</p>

        <h3>Умножение вектора на число</h3>
        <div class="formula">
            k · a⃗ (где k — число)
        </div>

        <p><strong>Свойства:</strong></p>
        <ul>
            <li>Если k > 0, то k·a⃗ сонаправлен с a⃗</li>
            <li>Если k < 0, то k·a⃗ противоположно направлен a⃗</li>
            <li>|k·a⃗| = |k| · |a⃗|</li>
        </ul>

        <svg width="500" height="250" viewBox="0 0 500 250">
            <defs>
                <marker id="arrow-v1" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto">
                    <polygon points="0 0, 10 3, 0 6" fill="#667eea" />
                </marker>
                <marker id="arrow-v2" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto">
                    <polygon points="0 0, 10 3, 0 6" fill="#ff5722" />
                </marker>
            </defs>
            <line x1="100" y1="125" x2="200" y2="125" stroke="#667eea" stroke-width="3" marker-end="url(#arrow-v1)"/>
            <line x1="250" y1="125" x2="450" y2="125" stroke="#ff5722" stroke-width="3" marker-end="url(#arrow-v2)"/>
            <text x="150" y="110" text-anchor="middle" font-size="16" fill="#667eea">a⃗</text>
            <text x="350" y="110" text-anchor="middle" font-size="16" fill="#ff5722">2a⃗</text>
        </svg>

        <h3>Скалярное произведение</h3>
        <div class="formula">
            a⃗ · b⃗ = |a⃗| · |b⃗| · cos(φ)<br>
            где φ — угол между векторами
        </div>

        <div class="important">
            <strong>Свойства:</strong>
            <ul>
                <li>a⃗ · b⃗ = 0 ⇔ векторы перпендикулярны (a⃗ ⊥ b⃗)</li>
                <li>a⃗ · a⃗ = |a⃗|²</li>
                <li>a⃗ · b⃗ = b⃗ · a⃗ (коммутативность)</li>
            </ul>
        </div>

        <h3>Координаты вектора</h3>
        <p>Если a⃗ = {x; y}, b⃗ = {x₁; y₁}, то:</p>
        <div class="formula">
            a⃗ + b⃗ = {x + x₁; y + y₁}<br>
            k · a⃗ = {kx; ky}<br>
            |a⃗| = √(x² + y²)<br>
            a⃗ · b⃗ = x·x₁ + y·y₁
        </div>
    </div>
`;

// Задания для всех тем
content['triangles-exercises'] = `
    <div class="section">
        <h2>Задания: Треугольники</h2>
        
        <div class="exercises">
            <h3>Практические задания</h3>
            
            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 1:</strong> В треугольнике два угла равны 50° и 70°. Найдите третий угол.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Сумма углов треугольника равна 180°.<br>
                    Третий угол = 180° - 50° - 70° = 60°<br>
                    <strong>Ответ:</strong> 60°
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 2:</strong> В прямоугольном треугольнике катеты равны 3 см и 4 см. Найдите гипотенузу.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    По теореме Пифагора: c² = a² + b²<br>
                    c² = 3² + 4² = 9 + 16 = 25<br>
                    c = 5 см<br>
                    <strong>Ответ:</strong> 5 см
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 3:</strong> Два треугольника имеют две равные стороны: AB = A₁B₁ = 5 см, AC = A₁C₁ = 7 см, и угол между ними ∠A = ∠A₁ = 60°. Равны ли эти треугольники?
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Да, треугольники равны по первому признаку равенства треугольников (по двум сторонам и углу между ними - СУС).<br>
                    <strong>Ответ:</strong> Да, треугольники равны
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 4:</strong> Периметр равностороннего треугольника равен 24 см. Найдите длину стороны.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    У равностороннего треугольника все стороны равны.<br>
                    Периметр P = 3a, где a — сторона<br>
                    24 = 3a<br>
                    a = 8 см<br>
                    <strong>Ответ:</strong> 8 см
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 5:</strong> В треугольнике ABC стороны AB = 6 см, BC = 8 см, AC = 10 см. Является ли этот треугольник прямоугольным?
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Проверим обратную теорему Пифагора:<br>
                    10² = 6² + 8²<br>
                    100 = 36 + 64<br>
                    100 = 100 ✓<br>
                    Равенство выполняется, значит треугольник прямоугольный.<br>
                    <strong>Ответ:</strong> Да, треугольник прямоугольный
                </div>
            </div>
        </div>
    </div>
`;

content['circles-exercises'] = `
    <div class="section">
        <h2>Задания: Окружности</h2>
        
        <div class="exercises">
            <h3>Практические задания</h3>
            
            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 1:</strong> Радиус окружности равен 5 см. Найдите длину окружности и площадь круга. (π ≈ 3,14)
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Длина окружности: C = 2πr = 2 × 3,14 × 5 = 31,4 см<br>
                    Площадь круга: S = πr² = 3,14 × 5² = 3,14 × 25 = 78,5 см²<br>
                    <strong>Ответ:</strong> C = 31,4 см, S = 78,5 см²
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 2:</strong> Центральный угол равен 60°. Найдите величину вписанного угла, опирающегося на ту же дугу.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Вписанный угол равен половине центрального угла.<br>
                    Вписанный угол = 60° / 2 = 30°<br>
                    <strong>Ответ:</strong> 30°
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 3:</strong> Из точки P проведены две касательные к окружности. Длина одной касательной равна 12 см. Чему равна длина второй касательной?
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    По свойству касательных, проведённых из одной точки, они равны.<br>
                    <strong>Ответ:</strong> 12 см
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 4:</strong> Вписанный угол опирается на диаметр окружности. Чему он равен?
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Вписанный угол, опирающийся на диаметр (полуокружность), всегда равен 90°.<br>
                    <strong>Ответ:</strong> 90°
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 5:</strong> Диаметр окружности равен 14 см. Найдите площадь круга. (π ≈ 22/7)
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Радиус r = d/2 = 14/2 = 7 см<br>
                    S = πr² = (22/7) × 7² = (22/7) × 49 = 22 × 7 = 154 см²<br>
                    <strong>Ответ:</strong> 154 см²
                </div>
            </div>
        </div>
    </div>
`;

content['parallelogram-exercises'] = `
    <div class="section">
        <h2>Задания: Параллелограммы</h2>
        
        <div class="exercises">
            <h3>Практические задания</h3>
            
            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 1:</strong> В параллелограмме один угол равен 70°. Найдите остальные углы.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Противоположные углы параллелограмма равны, поэтому второй угол тоже 70°.<br>
                    Сумма углов, прилежащих к одной стороне, равна 180°:<br>
                    Другие два угла = 180° - 70° = 110° каждый<br>
                    <strong>Ответ:</strong> 70°, 110°, 70°, 110°
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 2:</strong> Диагонали ромба равны 6 см и 8 см. Найдите площадь ромба.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Площадь ромба: S = ½ × d₁ × d₂<br>
                    S = ½ × 6 × 8 = ½ × 48 = 24 см²<br>
                    <strong>Ответ:</strong> 24 см²
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 3:</strong> Периметр квадрата равен 36 см. Найдите его сторону и площадь.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    У квадрата 4 равные стороны.<br>
                    Сторона a = P/4 = 36/4 = 9 см<br>
                    Площадь S = a² = 9² = 81 см²<br>
                    <strong>Ответ:</strong> a = 9 см, S = 81 см²
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 4:</strong> Диагонали параллелограмма пересекаются в точке O. AO = 5 см, BO = 7 см. Найдите длины диагоналей.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Диагонали параллелограмма точкой пересечения делятся пополам.<br>
                    AC = 2 × AO = 2 × 5 = 10 см<br>
                    BD = 2 × BO = 2 × 7 = 14 см<br>
                    <strong>Ответ:</strong> AC = 10 см, BD = 14 см
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 5:</strong> Стороны прямоугольника равны 5 см и 12 см. Найдите длину диагонали.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Диагональ прямоугольника образует прямоугольный треугольник со сторонами.<br>
                    По теореме Пифагора: d² = 5² + 12² = 25 + 144 = 169<br>
                    d = 13 см<br>
                    <strong>Ответ:</strong> 13 см
                </div>
            </div>
        </div>
    </div>
`;

content['trapezoid-exercises'] = `
    <div class="section">
        <h2>Задания: Трапеции</h2>
        
        <div class="exercises">
            <h3>Практические задания</h3>
            
            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 1:</strong> Основания трапеции равны 8 см и 12 см, высота 5 см. Найдите площадь трапеции.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    S = ½ × (a + b) × h<br>
                    S = ½ × (8 + 12) × 5 = ½ × 20 × 5 = 50 см²<br>
                    <strong>Ответ:</strong> 50 см²
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 2:</strong> Основания трапеции равны 6 см и 14 см. Найдите длину средней линии.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Средняя линия трапеции равна полусумме оснований.<br>
                    m = (a + b)/2 = (6 + 14)/2 = 20/2 = 10 см<br>
                    <strong>Ответ:</strong> 10 см
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 3:</strong> В равнобедренной трапеции углы при большем основании равны 60°. Найдите углы при меньшем основании.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Сумма углов, прилежащих к боковой стороне, равна 180°.<br>
                    Углы при меньшем основании = 180° - 60° = 120° каждый<br>
                    <strong>Ответ:</strong> 120°
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 4:</strong> Средняя линия трапеции равна 10 см, высота 6 см. Найдите площадь трапеции.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Площадь трапеции можно найти через среднюю линию:<br>
                    S = m × h = 10 × 6 = 60 см²<br>
                    <strong>Ответ:</strong> 60 см²
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 5:</strong> Периметр равнобедренной трапеции равен 40 см, боковая сторона 8 см, одно из оснований 12 см. Найдите второе основание.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Периметр = сумма всех сторон<br>
                    P = a + b + 2c, где c — боковая сторона<br>
                    40 = 12 + b + 2×8<br>
                    40 = 12 + b + 16<br>
                    b = 40 - 28 = 12 см<br>
                    <strong>Ответ:</strong> 12 см
                </div>
            </div>
        </div>
    </div>
`;

content['area-exercises'] = `
    <div class="section">
        <h2>Задания: Площади фигур</h2>
        
        <div class="exercises">
            <h3>Практические задания</h3>
            
            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 1:</strong> Основание треугольника равно 10 см, высота 6 см. Найдите площадь.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    S = ½ × a × h = ½ × 10 × 6 = 30 см²<br>
                    <strong>Ответ:</strong> 30 см²
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 2:</strong> Стороны параллелограмма 8 см и 5 см, угол между ними 30°. Найдите площадь. (sin 30° = 0,5)
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    S = a × b × sin(α) = 8 × 5 × 0,5 = 20 см²<br>
                    <strong>Ответ:</strong> 20 см²
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 3:</strong> Стороны треугольника равны 3 см, 4 см и 5 см. Найдите площадь по формуле Герона.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    p = (3 + 4 + 5)/2 = 6 см<br>
                    S = √(6×(6-3)×(6-4)×(6-5)) = √(6×3×2×1) = √36 = 6 см²<br>
                    <strong>Ответ:</strong> 6 см²
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 4:</strong> Радиус круга равен 4 см. Найдите площадь сектора с центральным углом 90°. (π ≈ 3,14)
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    S = (πr²α)/360° = (3,14 × 16 × 90)/360 = (3,14 × 16)/4 = 12,56 см²<br>
                    <strong>Ответ:</strong> 12,56 см²
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 5:</strong> Диагональ квадрата равна 10 см. Найдите площадь квадрата.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    S = ½ × d² = ½ × 10² = ½ × 100 = 50 см²<br>
                    <strong>Ответ:</strong> 50 см²
                </div>
            </div>
        </div>
    </div>
`;

content['vectors-exercises'] = `
    <div class="section">
        <h2>Задания: Векторы</h2>
        
        <div class="exercises">
            <h3>Практические задания</h3>
            
            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 1:</strong> Даны векторы a⃗ = {3; 4} и b⃗ = {1; 2}. Найдите a⃗ + b⃗.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    a⃗ + b⃗ = {3+1; 4+2} = {4; 6}<br>
                    <strong>Ответ:</strong> {4; 6}
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 2:</strong> Найдите длину вектора a⃗ = {3; 4}.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    |a⃗| = √(x² + y²) = √(3² + 4²) = √(9 + 16) = √25 = 5<br>
                    <strong>Ответ:</strong> 5
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 3:</strong> Даны векторы a⃗ = {2; 3} и b⃗ = {4; -1}. Найдите скалярное произведение a⃗ · b⃗.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    a⃗ · b⃗ = x₁x₂ + y₁y₂ = 2×4 + 3×(-1) = 8 - 3 = 5<br>
                    <strong>Ответ:</strong> 5
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 4:</strong> Вектор a⃗ = {6; 8}. Найдите вектор 2a⃗.
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    2a⃗ = {2×6; 2×8} = {12; 16}<br>
                    <strong>Ответ:</strong> {12; 16}
                </div>
            </div>

            <div class="exercise">
                <div class="exercise-question">
                    <strong>Задание 5:</strong> Даны векторы a⃗ = {1; 2} и b⃗ = {-2; 1}. Перпендикулярны ли эти векторы?
                </div>
                <button class="toggle-btn" onclick="toggleAnswer(this)">Показать ответ</button>
                <div class="exercise-answer">
                    <strong>Решение:</strong><br>
                    Векторы перпендикулярны, если их скалярное произведение равно 0.<br>
                    a⃗ · b⃗ = 1×(-2) + 2×1 = -2 + 2 = 0<br>
                    Скалярное произведение равно 0, значит векторы перпендикулярны.<br>
                    <strong>Ответ:</strong> Да, векторы перпендикулярны
                </div>
            </div>
        </div>
    </div>
`;
