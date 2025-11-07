// Дополнительный контент для других тем (подключается после script.js)

// Добавляем контент для окружностей
content['circles-basic'] = `
    <div class="section">
        <h2>Окружность: Основные понятия</h2>
        
        <p>Окружность — это множество всех точек плоскости, равноудалённых от одной точки, называемой центром окружности.</p>

        <svg width="500" height="500" viewBox="0 0 500 500">
            <circle cx="250" cy="250" r="150" fill="none" stroke="#667eea" stroke-width="3"/>
            <circle cx="250" cy="250" r="5" fill="#764ba2"/>
            <line x1="250" y1="250" x2="400" y2="250" stroke="#ff5722" stroke-width="2"/>
            <line x1="100" y1="250" x2="400" y2="250" stroke="#4caf50" stroke-width="2"/>
            <line x1="250" y1="140" x2="350" y2="320" stroke="#ff9800" stroke-width="2"/>
            
            <text x="250" y="240" text-anchor="middle" font-size="16" font-weight="bold">O</text>
            <text x="325" y="240" text-anchor="middle" font-size="16" fill="#ff5722">r</text>
            <text x="250" y="270" text-anchor="middle" font-size="16" fill="#4caf50">d</text>
            <text x="320" y="220" text-anchor="middle" font-size="16" fill="#ff9800">хорда</text>
        </svg>

        <h3>Основные элементы</h3>
        <ul>
            <li><strong>Радиус (r)</strong> — отрезок, соединяющий центр окружности с любой точкой окружности</li>
            <li><strong>Диаметр (d)</strong> — отрезок, соединяющий две точки окружности и проходящий через центр. d = 2r</li>
            <li><strong>Хорда</strong> — отрезок, соединяющий две точки окружности</li>
            <li><strong>Дуга</strong> — часть окружности между двумя точками</li>
        </ul>

        <div class="formula">
            Длина окружности: C = 2πr = πd<br>
            Площадь круга: S = πr²
        </div>

        <h3>Взаимное расположение прямой и окружности</h3>
        
        <svg width="700" height="200" viewBox="0 0 700 200">
            <circle cx="120" cy="100" r="60" fill="none" stroke="#667eea" stroke-width="2"/>
            <line x1="50" y1="180" x2="190" y2="20" stroke="#ff5722" stroke-width="2"/>
            <text x="120" y="180" text-anchor="middle" font-size="14">Не пересекает</text>
            
            <circle cx="350" cy="100" r="60" fill="none" stroke="#667eea" stroke-width="2"/>
            <line x1="250" y1="100" x2="450" y2="100" stroke="#ff5722" stroke-width="2"/>
            <circle cx="350" cy="100" r="3" fill="#ff5722"/>
            <text x="350" y="180" text-anchor="middle" font-size="14">Касается</text>
            
            <circle cx="580" cy="100" r="60" fill="none" stroke="#667eea" stroke-width="2"/>
            <line x1="500" y1="70" x2="660" y2="130" stroke="#ff5722" stroke-width="2"/>
            <circle cx="555" cy="85" r="3" fill="#ff5722"/>
            <circle cx="605" cy="115" r="3" fill="#ff5722"/>
            <text x="580" y="180" text-anchor="middle" font-size="14">Пересекает</text>
        </svg>

        <p><strong>Расстояние от центра до прямой:</strong></p>
        <ul>
            <li>d > r — прямая не пересекает окружность</li>
            <li>d = r — прямая касается окружности (одна точка касания)</li>
            <li>d < r — прямая пересекает окружность (две точки пересечения)</li>
        </ul>
    </div>
`;

content['circles-angles'] = `
    <div class="section">
        <h2>Углы в окружности</h2>
        
        <div class="theorem">
            <strong>Центральный угол</strong> — угол с вершиной в центре окружности.
        </div>

        <svg width="400" height="400" viewBox="0 0 400 400">
            <circle cx="200" cy="200" r="120" fill="none" stroke="#667eea" stroke-width="3"/>
            <circle cx="200" cy="200" r="5" fill="#764ba2"/>
            <line x1="200" y1="200" x2="320" y2="200" stroke="#ff5722" stroke-width="2"/>
            <line x1="200" y1="200" x2="260" y2="96" stroke="#ff5722" stroke-width="2"/>
            <path d="M 240 200 A 40 40 0 0 1 220 160" fill="none" stroke="#ff9800" stroke-width="2"/>
            
            <text x="200" y="190" text-anchor="middle" font-size="16" font-weight="bold">O</text>
            <text x="230" y="180" text-anchor="middle" font-size="16" fill="#ff9800">α</text>
            <text x="330" y="205" text-anchor="start" font-size="16">A</text>
            <text x="270" y="85" text-anchor="middle" font-size="16">B</text>
        </svg>

        <div class="formula">
            Центральный угол равен дуге, на которую он опирается:<br>
            ∠AOB = ⌢AB
        </div>

        <div class="theorem">
            <strong>Вписанный угол</strong> — угол, вершина которого лежит на окружности, а стороны пересекают окружность.
        </div>

        <svg width="400" height="400" viewBox="0 0 400 400">
            <circle cx="200" cy="200" r="120" fill="none" stroke="#667eea" stroke-width="3"/>
            <line x1="320" y1="200" x2="170" y2="95" stroke="#ff5722" stroke-width="2"/>
            <line x1="170" y1="95" x2="200" y2="320" stroke="#ff5722" stroke-width="2"/>
            <line x1="320" y1="200" x2="200" y2="320" stroke="#4caf50" stroke-width="2" stroke-dasharray="5,5"/>
            <path d="M 190 110 A 30 30 0 0 1 185 135" fill="none" stroke="#ff9800" stroke-width="2"/>
            
            <circle cx="170" cy="95" r="4" fill="#764ba2"/>
            <circle cx="320" cy="200" r="4" fill="#764ba2"/>
            <circle cx="200" cy="320" r="4" fill="#764ba2"/>
            
            <text x="160" y="85" text-anchor="end" font-size="16" font-weight="bold">C</text>
            <text x="335" y="205" text-anchor="start" font-size="16" font-weight="bold">A</text>
            <text x="200" y="340" text-anchor="middle" font-size="16" font-weight="bold">B</text>
            <text x="175" y="125" text-anchor="middle" font-size="16" fill="#ff9800">β</text>
        </svg>

        <div class="theorem">
            <strong>Теорема о вписанном угле:</strong><br>
            Вписанный угол равен половине центрального угла, опирающегося на ту же дугу.
        </div>

        <div class="formula">
            ∠ACB = ½∠AOB = ½⌢AB
        </div>

        <div class="important">
            <strong>Следствия:</strong>
            <ul>
                <li>Вписанные углы, опирающиеся на одну и ту же дугу, равны</li>
                <li>Вписанный угол, опирающийся на диаметр, — прямой (90°)</li>
                <li>Вписанный угол, опирающийся на полуокружность, равен 90°</li>
            </ul>
        </div>
    </div>
`;

content['circles-tangent'] = `
    <div class="section">
        <h2>Касательная к окружности</h2>
        
        <div class="theorem">
            <strong>Касательная</strong> — прямая, имеющая с окружностью только одну общую точку (точку касания).
        </div>

        <svg width="500" height="400" viewBox="0 0 500 400">
            <circle cx="250" cy="200" r="120" fill="none" stroke="#667eea" stroke-width="3"/>
            <circle cx="250" cy="200" r="5" fill="#764ba2"/>
            <line x1="150" y1="80" x2="450" y2="80" stroke="#ff5722" stroke-width="2"/>
            <line x1="250" y1="200" x2="250" y2="80" stroke="#4caf50" stroke-width="2" stroke-dasharray="5,5"/>
            <circle cx="250" cy="80" r="5" fill="#ff5722"/>
            <line x1="250" y1="80" x2="265" y2="80" stroke="#ff9800" stroke-width="3"/>
            <line x1="250" y1="80" x2="250" y2="95" stroke="#ff9800" stroke-width="3"/>
            
            <text x="250" y="190" text-anchor="middle" font-size="16" font-weight="bold">O</text>
            <text x="250" y="70" text-anchor="middle" font-size="16" font-weight="bold">A</text>
            <text x="270" y="140" text-anchor="start" font-size="16" fill="#4caf50">r</text>
            <text x="160" y="70" text-anchor="middle" font-size="16" fill="#ff5722">касательная</text>
        </svg>

        <div class="theorem">
            <strong>Свойство касательной:</strong><br>
            Касательная к окружности перпендикулярна радиусу, проведённому в точку касания.
        </div>

        <div class="formula">
            OA ⊥ касательной
        </div>

        <div class="theorem">
            <strong>Свойство отрезков касательных:</strong><br>
            Отрезки касательных к окружности, проведённые из одной точки, равны.
        </div>

        <svg width="500" height="400" viewBox="0 0 500 400">
            <circle cx="250" cy="250" r="100" fill="none" stroke="#667eea" stroke-width="3"/>
            <circle cx="250" cy="250" r="5" fill="#764ba2"/>
            <line x1="250" y1="100" x2="250" y2="150" stroke="#4caf50" stroke-width="2"/>
            <line x1="340" y1="290" x2="310" y2="270" stroke="#4caf50" stroke-width="2"/>
            <line x1="100" y1="100" x2="250" y2="150" stroke="#ff5722" stroke-width="2"/>
            <line x1="100" y1="100" x2="310" y2="270" stroke="#ff5722" stroke-width="2"/>
            <circle cx="100" cy="100" r="5" fill="#ff9800"/>
            <circle cx="250" cy="150" r="5" fill="#ff5722"/>
            <circle cx="310" cy="270" r="5" fill="#ff5722"/>
            
            <text x="250" y="240" text-anchor="middle" font-size="16" font-weight="bold">O</text>
            <text x="90" y="95" text-anchor="end" font-size="16" font-weight="bold">P</text>
            <text x="250" y="140" text-anchor="middle" font-size="16" font-weight="bold">A</text>
            <text x="320" y="280" text-anchor="start" font-size="16" font-weight="bold">B</text>
        </svg>

        <div class="formula">
            PA = PB
        </div>

        <div class="proof">
            <strong>Доказательство:</strong><br>
            Треугольники OPA и OPB — прямоугольные (так как касательная перпендикулярна радиусу).<br>
            OA = OB (радиусы), OP — общая сторона.<br>
            По признаку равенства прямоугольных треугольников ΔOPA = ΔOPB.<br>
            Следовательно, PA = PB.
        </div>
    </div>
`;

content['parallelogram-basic'] = `
    <div class="section">
        <h2>Параллелограмм: Свойства</h2>
        
        <div class="theorem">
            <strong>Параллелограмм</strong> — четырёхугольник, у которого противоположные стороны попарно параллельны.
        </div>

        <svg width="500" height="350" viewBox="0 0 500 350">
            <polygon points="100,80 380,80 450,280 170,280" fill="none" stroke="#667eea" stroke-width="3"/>
            <line x1="275" y1="180" x2="275" y2="180" stroke="#ff5722" stroke-width="2"/>
            <line x1="100" y1="80" x2="450" y2="280" stroke="#4caf50" stroke-width="2" stroke-dasharray="5,5"/>
            <line x1="380" y1="80" x2="170" y2="280" stroke="#4caf50" stroke-width="2" stroke-dasharray="5,5"/>
            <circle cx="275" cy="180" r="5" fill="#ff5722"/>
            
            <text x="100" y="70" text-anchor="middle" font-size="18" font-weight="bold">A</text>
            <text x="380" y="70" text-anchor="middle" font-size="18" font-weight="bold">B</text>
            <text x="450" y="295" text-anchor="middle" font-size="18" font-weight="bold">C</text>
            <text x="170" y="295" text-anchor="middle" font-size="18" font-weight="bold">D</text>
            <text x="275" y="170" text-anchor="middle" font-size="16" fill="#ff5722">O</text>
        </svg>

        <h3>Свойства параллелограмма</h3>
        
        <div class="theorem">
            <strong>1. Противоположные стороны равны:</strong>
        </div>
        <div class="formula">
            AB = CD, BC = AD
        </div>

        <div class="theorem">
            <strong>2. Противоположные углы равны:</strong>
        </div>
        <div class="formula">
            ∠A = ∠C, ∠B = ∠D
        </div>

        <div class="theorem">
            <strong>3. Диагонали точкой пересечения делятся пополам:</strong>
        </div>
        <div class="formula">
            AO = OC, BO = OD
        </div>

        <div class="theorem">
            <strong>4. Сумма углов, прилежащих к одной стороне, равна 180°:</strong>
        </div>
        <div class="formula">
            ∠A + ∠B = 180°, ∠B + ∠C = 180°
        </div>

        <h3>Признаки параллелограмма</h3>
        <p>Четырёхугольник является параллелограммом, если выполняется одно из условий:</p>
        <ul>
            <li>Противоположные стороны попарно равны</li>
            <li>Противоположные углы попарно равны</li>
            <li>Диагонали точкой пересечения делятся пополам</li>
            <li>Две стороны равны и параллельны</li>
        </ul>
    </div>
`;

content['parallelogram-special'] = `
    <div class="section">
        <h2>Прямоугольник, ромб, квадрат</h2>
        
        <h3>Прямоугольник</h3>
        <div class="theorem">
            <strong>Прямоугольник</strong> — параллелограмм, у которого все углы прямые.
        </div>

        <svg width="500" height="300" viewBox="0 0 500 300">
            <rect x="100" y="80" width="300" height="150" fill="none" stroke="#667eea" stroke-width="3"/>
            <line x1="100" y1="80" x2="400" y2="230" stroke="#4caf50" stroke-width="2" stroke-dasharray="5,5"/>
            <line x1="400" y1="80" x2="100" y2="230" stroke="#4caf50" stroke-width="2" stroke-dasharray="5,5"/>
            <line x1="100" y1="230" x2="115" y2="230" stroke="#ff5722" stroke-width="2"/>
            <line x1="100" y1="230" x2="100" y2="215" stroke="#ff5722" stroke-width="2"/>
            
            <text x="100" y="70" text-anchor="middle" font-size="18" font-weight="bold">A</text>
            <text x="400" y="70" text-anchor="middle" font-size="18" font-weight="bold">B</text>
            <text x="400" y="250" text-anchor="middle" font-size="18" font-weight="bold">C</text>
            <text x="100" y="250" text-anchor="middle" font-size="18" font-weight="bold">D</text>
        </svg>

        <div class="important">
            <strong>Свойство прямоугольника:</strong><br>
            Диагонали прямоугольника равны: AC = BD
        </div>

        <h3>Ромб</h3>
        <div class="theorem">
            <strong>Ромб</strong> — параллелограмм, у которого все стороны равны.
        </div>

        <svg width="400" height="400" viewBox="0 0 400 400">
            <polygon points="200,50 350,200 200,350 50,200" fill="none" stroke="#667eea" stroke-width="3"/>
            <line x1="200" y1="50" x2="200" y2="350" stroke="#4caf50" stroke-width="2" stroke-dasharray="5,5"/>
            <line x1="50" y1="200" x2="350" y2="200" stroke="#4caf50" stroke-width="2" stroke-dasharray="5,5"/>
            <line x1="200" y1="200" x2="215" y2="200" stroke="#ff5722" stroke-width="3"/>
            <line x1="200" y1="200" x2="200" y2="185" stroke="#ff5722" stroke-width="3"/>
            
            <text x="200" y="40" text-anchor="middle" font-size="18" font-weight="bold">A</text>
            <text x="365" y="205" text-anchor="start" font-size="18" font-weight="bold">B</text>
            <text x="200" y="375" text-anchor="middle" font-size="18" font-weight="bold">C</text>
            <text x="35" y="205" text-anchor="end" font-size="18" font-weight="bold">D</text>
        </svg>

        <div class="important">
            <strong>Свойства ромба:</strong>
            <ul>
                <li>Диагонали ромба перпендикулярны: AC ⊥ BD</li>
                <li>Диагонали ромба являются биссектрисами его углов</li>
            </ul>
        </div>

        <h3>Квадрат</h3>
        <div class="theorem">
            <strong>Квадрат</strong> — прямоугольник, у которого все стороны равны.<br>
            (или ромб, у которого все углы прямые)
        </div>

        <svg width="400" height="400" viewBox="0 0 400 400">
            <rect x="100" y="100" width="200" height="200" fill="none" stroke="#667eea" stroke-width="3"/>
            <line x1="100" y1="100" x2="300" y2="300" stroke="#4caf50" stroke-width="2" stroke-dasharray="5,5"/>
            <line x1="300" y1="100" x2="100" y2="300" stroke="#4caf50" stroke-width="2" stroke-dasharray="5,5"/>
            <line x1="100" y1="300" x2="115" y2="300" stroke="#ff5722" stroke-width="2"/>
            <line x1="100" y1="300" x2="100" y2="285" stroke="#ff5722" stroke-width="2"/>
            <line x1="200" y1="200" x2="215" y2="200" stroke="#ff9800" stroke-width="3"/>
            <line x1="200" y1="200" x2="200" y2="185" stroke="#ff9800" stroke-width="3"/>
            
            <text x="100" y="90" text-anchor="middle" font-size="18" font-weight="bold">A</text>
            <text x="310" y="105" text-anchor="start" font-size="18" font-weight="bold">B</text>
            <text x="310" y="305" text-anchor="start" font-size="18" font-weight="bold">C</text>
            <text x="90" y="305" text-anchor="end" font-size="18" font-weight="bold">D</text>
        </svg>

        <div class="important">
            <strong>Свойства квадрата:</strong><br>
            Квадрат обладает всеми свойствами прямоугольника и ромба:
            <ul>
                <li>Все стороны равны</li>
                <li>Все углы прямые (90°)</li>
                <li>Диагонали равны</li>
                <li>Диагонали перпендикулярны</li>
                <li>Диагонали делят углы пополам (по 45°)</li>
            </ul>
        </div>
    </div>
`;

content['trapezoid-basic'] = `
    <div class="section">
        <h2>Трапеция: Свойства</h2>
        
        <div class="theorem">
            <strong>Трапеция</strong> — четырёхугольник, у которого две стороны параллельны, а две другие — нет.
        </div>

        <svg width="500" height="350" viewBox="0 0 500 350">
            <polygon points="150,80 380,80 450,280 100,280" fill="none" stroke="#667eea" stroke-width="3"/>
            <line x1="150" y1="80" x2="145" y2="95" stroke="#ff5722" stroke-width="2"/>
            <line x1="380" y1="80" x2="385" y2="95" stroke="#ff5722" stroke-width="2"/>
            <line x1="100" y1="280" x2="105" y2="265" stroke="#ff5722" stroke-width="2"/>
            <line x1="450" y1="280" x2="445" y2="265" stroke="#ff5722" stroke-width="2"/>
            <line x1="265" y1="80" x2="275" y2="280" stroke="#4caf50" stroke-width="2" stroke-dasharray="5,5"/>
            
            <text x="150" y="70" text-anchor="middle" font-size="18" font-weight="bold">A</text>
            <text x="380" y="70" text-anchor="middle" font-size="18" font-weight="bold">B</text>
            <text x="450" y="300" text-anchor="middle" font-size="18" font-weight="bold">C</text>
            <text x="100" y="300" text-anchor="middle" font-size="18" font-weight="bold">D</text>
            <text x="265" y="70" text-anchor="middle" font-size="14" fill="#ff9800">основание</text>
            <text x="275" y="310" text-anchor="middle" font-size="14" fill="#ff9800">основание</text>
            <text x="110" y="180" text-anchor="start" font-size="14" fill="#4caf50">боковая</text>
        </svg>

        <p><strong>Основания</strong> — параллельные стороны (AB и CD).<br>
        <strong>Боковые стороны</strong> — непараллельные стороны (AD и BC).</p>

        <h3>Виды трапеций</h3>
        
        <svg width="700" height="300" viewBox="0 0 700 300">
            <polygon points="50,50 200,50 230,220 20,220" fill="none" stroke="#667eea" stroke-width="2"/>
            <text x="125" y="260" text-anchor="middle" font-size="14">Произвольная</text>
            
            <polygon points="280,50 430,50 460,220 250,220" fill="none" stroke="#667eea" stroke-width="2"/>
            <line x1="250" y1="220" x2="250" y2="205" stroke="#ff5722" stroke-width="2"/>
            <line x1="250" y1="220" x2="265" y2="220" stroke="#ff5722" stroke-width="2"/>
            <text x="355" y="260" text-anchor="middle" font-size="14">Прямоугольная</text>
            
            <polygon points="510,50 640,50 650,220 500,220" fill="none" stroke="#667eea" stroke-width="2"/>
            <line x1="510" y1="50" x2="540" y2="100" stroke="#ff9800" stroke-width="2"/>
            <line x1="640" y1="50" x2="635" y2="100" stroke="#ff9800" stroke-width="2"/>
            <text x="575" y="260" text-anchor="middle" font-size="14">Равнобедренная</text>
        </svg>

        <ul>
            <li><strong>Прямоугольная</strong> — один из углов прямой (90°)</li>
            <li><strong>Равнобедренная (равнобокая)</strong> — боковые стороны равны</li>
        </ul>

        <h3>Свойства равнобедренной трапеции</h3>
        <ul>
            <li>Углы при основании равны</li>
            <li>Диагонали равны</li>
            <li>Высота, опущенная из вершины на большее основание, делит его на два отрезка: один равен полуразности оснований, другой — полусумме</li>
        </ul>

        <h3>Средняя линия трапеции</h3>
        <div class="theorem">
            <strong>Средняя линия трапеции</strong> — отрезок, соединяющий середины боковых сторон.
        </div>

        <svg width="500" height="350" viewBox="0 0 500 350">
            <polygon points="150,80 380,80 450,280 100,280" fill="none" stroke="#667eea" stroke-width="3"/>
            <line x1="125" y1="180" x2="415" y2="180" stroke="#ff5722" stroke-width="3"/>
            <circle cx="125" cy="180" r="5" fill="#ff5722"/>
            <circle cx="415" cy="180" r="5" fill="#ff5722"/>
            
            <text x="150" y="70" text-anchor="middle" font-size="18" font-weight="bold">A</text>
            <text x="380" y="70" text-anchor="middle" font-size="18" font-weight="bold">B</text>
            <text x="450" y="300" text-anchor="middle" font-size="18" font-weight="bold">C</text>
            <text x="100" y="300" text-anchor="middle" font-size="18" font-weight="bold">D</text>
            <text x="115" y="175" text-anchor="end" font-size="16" font-weight="bold">M</text>
            <text x="425" y="175" text-anchor="start" font-size="16" font-weight="bold">N</text>
        </svg>

        <div class="theorem">
            <strong>Свойства средней линии:</strong>
        </div>
        <div class="formula">
            1. Средняя линия параллельна основаниям<br>
            2. Средняя линия равна полусумме оснований: MN = (AB + CD)/2
        </div>
    </div>
`;
