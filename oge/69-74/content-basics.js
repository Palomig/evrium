// Контент для базовых понятий и координат

content['basics-points-lines'] = `
    <div class="section">
        <h2>Точка и прямая</h2>
        
        <h3>Основные определения</h3>
        
        <div class="theorem">
            <strong>Точка</strong> — основное понятие геометрии, не имеющее определения. Точка не имеет размеров, она только указывает положение в пространстве.
        </div>

        <svg width="500" height="200" viewBox="0 0 500 200">
            <circle cx="100" cy="100" r="4" fill="#667eea"/>
            <circle cx="250" cy="100" r="4" fill="#667eea"/>
            <circle cx="400" cy="100" r="4" fill="#667eea"/>
            <text x="100" y="85" text-anchor="middle" font-size="18" font-weight="bold">A</text>
            <text x="250" y="85" text-anchor="middle" font-size="18" font-weight="bold">B</text>
            <text x="400" y="85" text-anchor="middle" font-size="18" font-weight="bold">C</text>
        </svg>

        <p>Точки обозначаются заглавными латинскими буквами: A, B, C, D...</p>

        <div class="theorem">
            <strong>Прямая</strong> — бесконечная линия, не имеющая ни начала, ни конца. Через любые две точки можно провести прямую, и притом только одну.
        </div>

        <svg width="600" height="250" viewBox="0 0 600 250">
            <defs>
                <marker id="arrow-right" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto">
                    <polygon points="0 0, 10 3, 0 6" fill="#667eea"/>
                </marker>
                <marker id="arrow-left" markerWidth="10" markerHeight="10" refX="0" refY="3" orient="auto">
                    <polygon points="10 0, 0 3, 10 6" fill="#667eea"/>
                </marker>
            </defs>
            <line x1="50" y1="125" x2="550" y2="125" stroke="#667eea" stroke-width="2" marker-start="url(#arrow-left)" marker-end="url(#arrow-right)"/>
            <circle cx="200" cy="125" r="5" fill="#764ba2"/>
            <circle cx="400" cy="125" r="5" fill="#764ba2"/>
            <text x="200" y="110" text-anchor="middle" font-size="18" font-weight="bold">A</text>
            <text x="400" y="110" text-anchor="middle" font-size="18" font-weight="bold">B</text>
            <text x="300" y="155" text-anchor="middle" font-size="16" fill="#667eea">прямая AB</text>
        </svg>

        <p>Прямые обозначаются малыми латинскими буквами (a, b, c...) или двумя точками (AB, CD...)</p>

        <h3>Отрезок и луч</h3>

        <div class="theorem">
            <strong>Отрезок</strong> — часть прямой, ограниченная двумя точками (концами отрезка).
        </div>

        <svg width="600" height="200" viewBox="0 0 600 200">
            <line x1="150" y1="100" x2="450" y2="100" stroke="#667eea" stroke-width="3"/>
            <circle cx="150" cy="100" r="5" fill="#764ba2"/>
            <circle cx="450" cy="100" r="5" fill="#764ba2"/>
            <text x="150" y="85" text-anchor="middle" font-size="18" font-weight="bold">A</text>
            <text x="450" y="85" text-anchor="middle" font-size="18" font-weight="bold">B</text>
            <text x="300" y="135" text-anchor="middle" font-size="16" fill="#667eea">отрезок AB</text>
        </svg>

        <div class="theorem">
            <strong>Луч</strong> — часть прямой, имеющая начало, но не имеющая конца.
        </div>

        <svg width="600" height="200" viewBox="0 0 600 200">
            <defs>
                <marker id="ray-arrow" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto">
                    <polygon points="0 0, 10 3, 0 6" fill="#667eea"/>
                </marker>
            </defs>
            <line x1="150" y1="100" x2="550" y2="100" stroke="#667eea" stroke-width="3" marker-end="url(#ray-arrow)"/>
            <circle cx="150" cy="100" r="5" fill="#764ba2"/>
            <text x="150" y="85" text-anchor="middle" font-size="18" font-weight="bold">A</text>
            <text x="350" y="135" text-anchor="middle" font-size="16" fill="#667eea">луч с началом в точке A</text>
        </svg>

        <h3>Взаимное расположение прямых</h3>

        <svg width="700" height="250" viewBox="0 0 700 250">
            <!-- Пересекающиеся -->
            <line x1="50" y1="50" x2="150" y2="200" stroke="#667eea" stroke-width="2"/>
            <line x1="50" y1="200" x2="150" y2="50" stroke="#ff5722" stroke-width="2"/>
            <circle cx="100" cy="125" r="4" fill="#764ba2"/>
            <text x="100" y="230" text-anchor="middle" font-size="14">Пересекаются</text>
            
            <!-- Параллельные -->
            <line x1="250" y1="80" x2="380" y2="80" stroke="#667eea" stroke-width="2"/>
            <line x1="250" y1="170" x2="380" y2="170" stroke="#ff5722" stroke-width="2"/>
            <text x="315" y="230" text-anchor="middle" font-size="14">Параллельны</text>
            
            <!-- Совпадающие -->
            <line x1="480" y1="125" x2="620" y2="125" stroke="#667eea" stroke-width="4"/>
            <text x="550" y="230" text-anchor="middle" font-size="14">Совпадают</text>
        </svg>

        <div class="example">
            <strong>💡 Пример из жизни:</strong><br>
            Представьте дорогу как прямую линию. Столбы вдоль дороги — это точки на этой прямой. Расстояние между двумя столбами — это отрезок.
        </div>
    </div>
`;

content['basics-angles'] = `
    <div class="section">
        <h2>Углы</h2>
        
        <div class="theorem">
            <strong>Угол</strong> — геометрическая фигура, образованная двумя лучами (сторонами угла), выходящими из одной точки (вершины угла).
        </div>

        <svg width="400" height="350" viewBox="0 0 400 350">
            <line x1="200" y1="250" x2="200" y2="80" stroke="#667eea" stroke-width="3"/>
            <line x1="200" y1="250" x2="380" y2="150" stroke="#ff5722" stroke-width="3"/>
            <path d="M 200 200 A 50 50 0 0 1 250 210" fill="none" stroke="#ff9800" stroke-width="2"/>
            <circle cx="200" cy="250" r="5" fill="#764ba2"/>
            <text x="200" y="270" text-anchor="middle" font-size="18" font-weight="bold">O</text>
            <text x="190" y="65" text-anchor="end" font-size="16">A</text>
            <text x="395" y="145" text-anchor="start" font-size="16">B</text>
            <text x="230" y="195" text-anchor="middle" font-size="16" fill="#ff9800">α</text>
        </svg>

        <p>Обозначение: ∠AOB или просто ∠α</p>

        <h3>Виды углов</h3>

        <svg width="800" height="300" viewBox="0 0 800 300">
            <!-- Острый -->
            <line x1="50" y1="200" x2="50" y2="100" stroke="#667eea" stroke-width="2"/>
            <line x1="50" y1="200" x2="120" y2="150" stroke="#667eea" stroke-width="2"/>
            <path d="M 50 160 A 40 40 0 0 1 80 170" fill="none" stroke="#ff9800" stroke-width="2"/>
            <text x="85" y="125" text-anchor="middle" font-size="14">Острый</text>
            <text x="85" y="145" text-anchor="middle" font-size="12">(< 90°)</text>
            
            <!-- Прямой -->
            <line x1="220" y1="200" x2="220" y2="100" stroke="#667eea" stroke-width="2"/>
            <line x1="220" y1="200" x2="320" y2="200" stroke="#667eea" stroke-width="2"/>
            <rect x="220" y="200" width="15" height="15" fill="none" stroke="#ff5722" stroke-width="2" transform="rotate(-90 220 200)"/>
            <text x="270" y="125" text-anchor="middle" font-size="14">Прямой</text>
            <text x="270" y="145" text-anchor="middle" font-size="12">(= 90°)</text>
            
            <!-- Тупой -->
            <line x1="420" y1="200" x2="420" y2="100" stroke="#667eea" stroke-width="2"/>
            <line x1="420" y1="200" x2="480" y2="180" stroke="#667eea" stroke-width="2"/>
            <path d="M 420 160 A 40 40 0 0 1 460 155" fill="none" stroke="#ff9800" stroke-width="2"/>
            <text x="450" y="125" text-anchor="middle" font-size="14">Тупой</text>
            <text x="450" y="145" text-anchor="middle" font-size="12">(> 90°)</text>
            
            <!-- Развёрнутый -->
            <line x1="580" y1="200" x2="720" y2="200" stroke="#667eea" stroke-width="2"/>
            <circle cx="650" cy="200" r="4" fill="#764ba2"/>
            <text x="650" y="125" text-anchor="middle" font-size="14">Развёрнутый</text>
            <text x="650" y="145" text-anchor="middle" font-size="12">(= 180°)</text>
        </svg>

        <ul>
            <li><strong>Острый угол:</strong> меньше 90° (0° < α < 90°)</li>
            <li><strong>Прямой угол:</strong> равен 90° (α = 90°)</li>
            <li><strong>Тупой угол:</strong> больше 90°, но меньше 180° (90° < α < 180°)</li>
            <li><strong>Развёрнутый угол:</strong> равен 180° (α = 180°)</li>
        </ul>

        <h3>Смежные углы</h3>

        <div class="theorem">
            <strong>Смежные углы</strong> — два угла, у которых одна сторона общая, а две другие являются продолжением друг друга.
        </div>

        <svg width="500" height="300" viewBox="0 0 500 300">
            <line x1="50" y1="150" x2="450" y2="150" stroke="#667eea" stroke-width="2"/>
            <line x1="250" y1="150" x2="350" y2="50" stroke="#ff5722" stroke-width="2"/>
            <path d="M 290 150 A 40 40 0 0 1 270 110" fill="rgba(102, 126, 234, 0.2)"/>
            <path d="M 210 150 A 40 40 0 0 0 230 110" fill="rgba(255, 87, 34, 0.2)"/>
            <text x="305" y="125" text-anchor="middle" font-size="16">α</text>
            <text x="195" y="125" text-anchor="middle" font-size="16">β</text>
        </svg>

        <div class="formula">
            $$\\alpha + \\beta = 180°$$
        </div>

        <div class="important">
            <strong>Свойство:</strong> Сумма смежных углов равна 180°
        </div>

        <h3>Вертикальные углы</h3>

        <div class="theorem">
            <strong>Вертикальные углы</strong> — углы, образованные при пересечении двух прямых. Стороны одного угла являются продолжениями сторон другого.
        </div>

        <svg width="500" height="400" viewBox="0 0 500 400">
            <line x1="50" y1="80" x2="450" y2="320" stroke="#667eea" stroke-width="2"/>
            <line x1="50" y1="320" x2="450" y2="80" stroke="#ff5722" stroke-width="2"/>
            <path d="M 260 190 A 30 30 0 0 1 270 220" fill="rgba(102, 126, 234, 0.2)"/>
            <path d="M 240 210 A 30 30 0 0 1 230 180" fill="rgba(102, 126, 234, 0.2)"/>
            <text x="285" y="205" text-anchor="middle" font-size="16">α</text>
            <text x="215" y="195" text-anchor="middle" font-size="16">α</text>
            <text x="180" y="120" text-anchor="middle" font-size="14">β</text>
            <text x="320" y="280" text-anchor="middle" font-size="14">β</text>
        </svg>

        <div class="formula">
            $$\\angle 1 = \\angle 3,\\quad \\angle 2 = \\angle 4$$
        </div>

        <div class="important">
            <strong>Свойство:</strong> Вертикальные углы равны
        </div>

        <div class="example">
            <strong>💡 Пример:</strong><br>
            Если при пересечении двух прямых один из углов равен 50°, то противоположный ему вертикальный угол тоже равен 50°, а смежные с ними углы равны 180° - 50° = 130°.
        </div>
    </div>
`;

content['basics-parallel'] = `
    <div class="section">
        <h2>Параллельные прямые</h2>
        
        <div class="theorem">
            <strong>Параллельные прямые</strong> — прямые, которые лежат в одной плоскости и не пересекаются, сколько бы их ни продолжали.
        </div>

        <p>Обозначение: $a \\parallel b$ (читается: "прямая a параллельна прямой b")</p>

        <svg width="500" height="300" viewBox="0 0 500 300">
            <line x1="50" y1="100" x2="450" y2="100" stroke="#667eea" stroke-width="3"/>
            <line x1="50" y1="200" x2="450" y2="200" stroke="#667eea" stroke-width="3"/>
            <text x="25" y="105" text-anchor="end" font-size="18" fill="#667eea">a</text>
            <text x="25" y="205" text-anchor="end" font-size="18" fill="#667eea">b</text>
            <text x="250" y="150" text-anchor="middle" font-size="16" fill="#ff5722">a ∥ b</text>
        </svg>

        <h3>Секущая и углы при параллельных прямых</h3>

        <div class="theorem">
            <strong>Секущая</strong> — прямая, пересекающая две другие прямые.
        </div>

        <svg width="600" height="400" viewBox="0 0 600 400">
            <line x1="50" y1="120" x2="550" y2="120" stroke="#667eea" stroke-width="2"/>
            <line x1="50" y1="280" x2="550" y2="280" stroke="#667eea" stroke-width="2"/>
            <line x1="200" y1="50" x2="350" y2="350" stroke="#ff5722" stroke-width="2"/>
            
            <!-- Углы -->
            <path d="M 240 120 A 30 30 0 0 1 250 150" fill="rgba(255, 152, 0, 0.3)"/>
            <path d="M 285 280 A 30 30 0 0 1 295 250" fill="rgba(255, 152, 0, 0.3)"/>
            
            <text x="255" y="140" text-anchor="middle" font-size="14">1</text>
            <text x="230" y="145" text-anchor="middle" font-size="14">2</text>
            <text x="300" y="270" text-anchor="middle" font-size="14">5</text>
            <text x="275" y="275" text-anchor="middle" font-size="14">6</text>
            
            <text x="25" y="125" text-anchor="end" font-size="16" fill="#667eea">a</text>
            <text x="25" y="285" text-anchor="end" font-size="16" fill="#667eea">b</text>
            <text x="360" y="365" text-anchor="start" font-size="16" fill="#ff5722">c</text>
        </svg>

        <h3>Признаки параллельности прямых</h3>

        <div class="theorem">
            <strong>1. Если при пересечении двух прямых секущей накрест лежащие углы равны, то прямые параллельны.</strong>
        </div>

        <div class="formula">
            $$\\angle 1 = \\angle 5 \\Rightarrow a \\parallel b$$
        </div>

        <div class="theorem">
            <strong>2. Если при пересечении двух прямых секущей соответственные углы равны, то прямые параллельны.</strong>
        </div>

        <div class="theorem">
            <strong>3. Если при пересечении двух прямых секущей сумма односторонних углов равна 180°, то прямые параллельны.</strong>
        </div>

        <div class="formula">
            $$\\angle 2 + \\angle 6 = 180° \\Rightarrow a \\parallel b$$
        </div>

        <h3>Свойства параллельных прямых</h3>

        <div class="important">
            Если две параллельные прямые пересечены секущей, то:
            <ul>
                <li><strong>Накрест лежащие углы равны</strong></li>
                <li><strong>Соответственные углы равны</strong></li>
                <li><strong>Сумма односторонних углов равна 180°</strong></li>
            </ul>
        </div>

        <div class="example">
            <strong>💡 Пример из жизни:</strong><br>
            Железнодорожные рельсы — это параллельные прямые. Шпалы, соединяющие рельсы — это секущие. Углы, которые образуют шпалы с рельсами, одинаковы с обеих сторон, что подтверждает свойство параллельных прямых.
        </div>

        <div class="proof">
            <strong>💭 Доказательство (признак по накрест лежащим углам):</strong><br>
            Пусть при пересечении прямых a и b секущей c накрест лежащие углы равны: ∠1 = ∠5.<br>
            Предположим, что прямые не параллельны и пересекаются в точке M.<br>
            Тогда получится треугольник, в котором один из углов равен ∠1, а другой равен ∠5.<br>
            Но по условию ∠1 = ∠5, что невозможно для треугольника (внешний угол не может равняться внутреннему, не смежному с ним).<br>
            Следовательно, наше предположение неверно, и прямые a ∥ b.
        </div>
    </div>
`;

content['coords-basic'] = `
    <div class="section">
        <h2>Координатная плоскость</h2>
        
        <div class="theorem">
            <strong>Координатная плоскость</strong> — плоскость, в которой выбрана система координат. Она образуется двумя перпендикулярными числовыми прямыми — осями координат.
        </div>

        <svg width="600" height="600" viewBox="0 0 600 600">
            <defs>
                <marker id="axis-arrow" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto">
                    <polygon points="0 0, 10 3, 0 6" fill="#667eea"/>
                </marker>
            </defs>
            
            <!-- Сетка -->
            <g stroke="#e0e0e0" stroke-width="1">
                <line x1="100" y1="100" x2="100" y2="500"/>
                <line x1="150" y1="100" x2="150" y2="500"/>
                <line x1="200" y1="100" x2="200" y2="500"/>
                <line x1="250" y1="100" x2="250" y2="500"/>
                <line x1="350" y1="100" x2="350" y2="500"/>
                <line x1="400" y1="100" x2="400" y2="500"/>
                <line x1="450" y1="100" x2="450" y2="500"/>
                <line x1="500" y1="100" x2="500" y2="500"/>
                
                <line x1="50" y1="150" x2="550" y2="150"/>
                <line x1="50" y1="200" x2="550" y2="200"/>
                <line x1="50" y1="250" x2="550" y2="250"/>
                <line x1="50" y1="350" x2="550" y2="350"/>
                <line x1="50" y1="400" x2="550" y2="400"/>
                <line x1="50" y1="450" x2="550" y2="450"/>
                <line x1="50" y1="500" x2="550" y2="500"/>
            </g>
            
            <!-- Оси -->
            <line x1="50" y1="300" x2="570" y2="300" stroke="#667eea" stroke-width="2" marker-end="url(#axis-arrow)"/>
            <line x1="300" y1="520" x2="300" y2="80" stroke="#667eea" stroke-width="2" marker-end="url(#axis-arrow)"/>
            
            <!-- Метки -->
            <text x="570" y="290" text-anchor="start" font-size="18" fill="#667eea" font-weight="bold">x</text>
            <text x="310" y="75" text-anchor="start" font-size="18" fill="#667eea" font-weight="bold">y</text>
            <text x="285" y="320" text-anchor="end" font-size="16">0</text>
            
            <text x="350" y="320" text-anchor="middle" font-size="14">1</text>
            <text x="400" y="320" text-anchor="middle" font-size="14">2</text>
            <text x="450" y="320" text-anchor="middle" font-size="14">3</text>
            <text x="250" y="320" text-anchor="middle" font-size="14">-1</text>
            <text x="200" y="320" text-anchor="middle" font-size="14">-2</text>
            
            <text x="285" y="250" text-anchor="end" font-size="14">1</text>
            <text x="285" y="200" text-anchor="end" font-size="14">2</text>
            <text x="285" y="150" text-anchor="end" font-size="14">3</text>
            <text x="285" y="350" text-anchor="end" font-size="14">-1</text>
            <text x="285" y="400" text-anchor="end" font-size="14">-2</text>
            
            <!-- Точка примера -->
            <circle cx="400" cy="200" r="6" fill="#ff5722"/>
            <text x="420" y="195" text-anchor="start" font-size="16" fill="#ff5722" font-weight="bold">A(2; 2)</text>
            <line x1="400" y1="200" x2="400" y2="300" stroke="#ff5722" stroke-width="1" stroke-dasharray="5,5"/>
            <line x1="400" y1="200" x2="300" y2="200" stroke="#ff5722" stroke-width="1" stroke-dasharray="5,5"/>
        </svg>

        <h3>Основные понятия</h3>
        <ul>
            <li><strong>Ось абсцисс (Ox)</strong> — горизонтальная ось</li>
            <li><strong>Ось ординат (Oy)</strong> — вертикальная ось</li>
            <li><strong>Начало координат (O)</strong> — точка пересечения осей с координатами (0; 0)</li>
            <li><strong>Координаты точки</strong> — пара чисел (x; y), где x — абсцисса, y — ордината</li>
        </ul>

        <div class="formula">
            $$A(x; y)$$
            где $x$ — абсцисса (расстояние от оси Oy),<br>
            $y$ — ордината (расстояние от оси Ox)
        </div>

        <h3>Четверти координатной плоскости</h3>

        <svg width="500" height="500" viewBox="0 0 500 500">
            <line x1="50" y1="250" x2="450" y2="250" stroke="#667eea" stroke-width="2"/>
            <line x1="250" y1="50" x2="250" y2="450" stroke="#667eea" stroke-width="2"/>
            
            <text x="350" y="150" text-anchor="middle" font-size="24" font-weight="bold" fill="#4caf50">I</text>
            <text x="150" y="150" text-anchor="middle" font-size="24" font-weight="bold" fill="#ff9800">II</text>
            <text x="150" y="350" text-anchor="middle" font-size="24" font-weight="bold" fill="#f44336">III</text>
            <text x="350" y="350" text-anchor="middle" font-size="24" font-weight="bold" fill="#2196f3">IV</text>
            
            <text x="350" y="180" text-anchor="middle" font-size="14">(+; +)</text>
            <text x="150" y="180" text-anchor="middle" font-size="14">(−; +)</text>
            <text x="150" y="380" text-anchor="middle" font-size="14">(−; −)</text>
            <text x="350" y="380" text-anchor="middle" font-size="14">(+; −)</text>
        </svg>

        <div class="example">
            <strong>💡 Примеры точек:</strong><br>
            • A(3; 2) — в I четверти (оба числа положительные)<br>
            • B(−2; 3) — во II четверти (x отрицательный, y положительный)<br>
            • C(−1; −4) — в III четверти (оба числа отрицательные)<br>
            • D(4; −1) — в IV четверти (x положительный, y отрицательный)
        </div>
    </div>
`;

content['coords-distance'] = `
    <div class="section">
        <h2>Расстояние между точками</h2>
        
        <div class="theorem">
            <strong>Расстояние между двумя точками</strong> на координатной плоскости вычисляется по формуле, основанной на теореме Пифагора.
        </div>

        <svg width="600" height="500" viewBox="0 0 600 500">
            <defs>
                <marker id="coord-arrow" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto">
                    <polygon points="0 0, 10 3, 0 6" fill="#667eea"/>
                </marker>
            </defs>
            
            <!-- Оси -->
            <line x1="50" y1="400" x2="550" y2="400" stroke="#ccc" stroke-width="1" marker-end="url(#coord-arrow)"/>
            <line x1="100" y1="450" x2="100" y2="50" stroke="#ccc" stroke-width="1" marker-end="url(#coord-arrow)"/>
            
            <!-- Точки -->
            <circle cx="200" cy="300" r="6" fill="#ff5722"/>
            <circle cx="450" cy="150" r="6" fill="#ff5722"/>
            
            <!-- Линии -->
            <line x1="200" y1="300" x2="450" y2="150" stroke="#667eea" stroke-width="3"/>
            <line x1="200" y1="300" x2="450" y2="300" stroke="#4caf50" stroke-width="2" stroke-dasharray="5,5"/>
            <line x1="450" y1="300" x2="450" y2="150" stroke="#ff9800" stroke-width="2" stroke-dasharray="5,5"/>
            
            <!-- Прямой угол -->
            <rect x="450" y="300" width="15" height="15" fill="none" stroke="#ff5722" stroke-width="2" transform="rotate(-90 450 300)"/>
            
            <!-- Подписи -->
            <text x="190" y="290" text-anchor="end" font-size="18" fill="#ff5722" font-weight="bold">A(x₁; y₁)</text>
            <text x="460" y="140" text-anchor="start" font-size="18" fill="#ff5722" font-weight="bold">B(x₂; y₂)</text>
            
            <text x="325" y="210" text-anchor="middle" font-size="16" fill="#667eea" font-weight="bold">d</text>
            <text x="325" y="330" text-anchor="middle" font-size="14" fill="#4caf50">|x₂ − x₁|</text>
            <text x="470" y="230" text-anchor="start" font-size="14" fill="#ff9800">|y₂ − y₁|</text>
        </svg>

        <div class="formula">
            $$d = \\sqrt{(x_2 - x_1)^2 + (y_2 - y_1)^2}$$
        </div>

        <div class="proof">
            <strong>💭 Вывод формулы:</strong><br>
            Рассмотрим прямоугольный треугольник с вершинами в точках A(x₁; y₁), B(x₂; y₂) и C(x₂; y₁).<br>
            Катеты этого треугольника:<br>
            • AC = |x₂ − x₁| (горизонтальный катет)<br>
            • BC = |y₂ − y₁| (вертикальный катет)<br>
            <br>
            По теореме Пифагора:<br>
            $$AB^2 = AC^2 + BC^2$$<br>
            $$d^2 = (x_2 - x_1)^2 + (y_2 - y_1)^2$$<br>
            $$d = \\sqrt{(x_2 - x_1)^2 + (y_2 - y_1)^2}$$
        </div>

        <h3>Координаты середины отрезка</h3>

        <div class="theorem">
            Если точки A(x₁; y₁) и B(x₂; y₂) — концы отрезка, то координаты середины M отрезка AB вычисляются по формулам:
        </div>

        <div class="formula">
            $$M\\left(\\frac{x_1 + x_2}{2}; \\frac{y_1 + y_2}{2}\\right)$$
        </div>

        <svg width="600" height="400" viewBox="0 0 600 400">
            <line x1="150" y1="300" x2="450" y2="100" stroke="#667eea" stroke-width="3"/>
            <circle cx="150" cy="300" r="6" fill="#ff5722"/>
            <circle cx="450" cy="100" r="6" fill="#ff5722"/>
            <circle cx="300" cy="200" r="6" fill="#4caf50"/>
            
            <text x="140" y="295" text-anchor="end" font-size="16" fill="#ff5722" font-weight="bold">A</text>
            <text x="460" y="95" text-anchor="start" font-size="16" fill="#ff5722" font-weight="bold">B</text>
            <text x="310" y="195" text-anchor="start" font-size="16" fill="#4caf50" font-weight="bold">M</text>
        </svg>

        <div class="example">
            <strong>💡 Пример 1:</strong><br>
            Найдите расстояние между точками A(1; 2) и B(4; 6).<br>
            <br>
            <strong>Решение:</strong><br>
            $$d = \\sqrt{(4-1)^2 + (6-2)^2} = \\sqrt{3^2 + 4^2} = \\sqrt{9 + 16} = \\sqrt{25} = 5$$<br>
            <strong>Ответ:</strong> 5 единиц
        </div>

        <div class="example">
            <strong>💡 Пример 2:</strong><br>
            Найдите координаты середины отрезка с концами A(2; 3) и B(6; 7).<br>
            <br>
            <strong>Решение:</strong><br>
            $$M\\left(\\frac{2+6}{2}; \\frac{3+7}{2}\\right) = M(4; 5)$$<br>
            <strong>Ответ:</strong> M(4; 5)
        </div>
    </div>
`;

// Добавляем стиль для примеров
const styleSheet = document.createElement('style');
styleSheet.textContent = `
    .example {
        background: #e3f2fd;
        padding: 15px;
        margin: 15px 0;
        border-radius: 6px;
        border-left: 4px solid #2196f3;
    }
`;
document.head.appendChild(styleSheet);
