<?php
// Настройки страницы
$page_title = 'Задачи 1-6 ОГЭ 2026';
$_GET['section'] = '1-6';

// Подключаем header и sidebar
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    .content-wrapper {
        padding: 0;
        background: transparent;
        box-shadow: none;
    }

    .oge-content {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px;
    }

    /* Копируем стили из оригинального HTML */
    .container {
        background: white;
        border-radius: 20px;
        padding: 40px;
        max-width: 1200px;
        width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: fadeIn 0.5s;
        margin: 0 auto;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 3px solid #667eea;
    }

    .header h1 {
        color: #667eea;
        font-size: 32px;
        margin-bottom: 10px;
    }

    .header .subtitle {
        color: #764ba2;
        font-size: 18px;
        font-weight: 600;
    }

    .badge {
        display: inline-block;
        background: linear-gradient(135deg, #ff9800, #ff5722);
        color: white;
        padding: 8px 20px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: bold;
        margin-top: 10px;
    }

    .task-box {
        background: #f8f9fa;
        padding: 30px;
        border-radius: 15px;
        border-left: 6px solid #ff9800;
        margin-bottom: 30px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .task-box h2 {
        color: #333;
        font-size: 22px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
    }

    .task-box h2::before {
        content: "📝";
        margin-right: 10px;
        font-size: 28px;
    }

    .task-text {
        color: #555;
        font-size: 20px;
        line-height: 1.8;
        font-weight: 500;
    }

    .given-data {
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        padding: 20px;
        border-radius: 12px;
        margin: 25px 0;
        border-left: 5px solid #2196f3;
    }

    .given-data h3 {
        color: #1565c0;
        font-size: 18px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
    }

    .given-data h3::before {
        content: "📊";
        margin-right: 8px;
    }

    .given-data p {
        color: #333;
        font-size: 18px;
        margin: 8px 0;
        font-weight: 500;
    }

    .illustration {
        background: white;
        padding: 30px;
        border-radius: 15px;
        margin: 30px 0;
        text-align: center;
        border: 3px solid #e0e0e0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }

    .illustration h3 {
        color: #667eea;
        margin-bottom: 20px;
        font-size: 20px;
    }

    .solution-section {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        padding: 30px;
        border-radius: 15px;
        margin: 25px 0;
        border-left: 6px solid #4caf50;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .solution-section h3 {
        color: #2e7d32;
        font-size: 22px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
    }

    .solution-section h3::before {
        content: "💡";
        margin-right: 10px;
        font-size: 26px;
    }

    .step {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin: 18px 0;
        border-left: 4px solid #4caf50;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        transition: transform 0.3s;
    }

    .step:hover {
        transform: translateX(5px);
    }

    .step-header {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }

    .step-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #4caf50, #45a049);
        color: white;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        font-weight: bold;
        margin-right: 12px;
        font-size: 16px;
        box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
    }

    .step-title {
        font-weight: 600;
        color: #2e7d32;
        font-size: 17px;
    }

    .step-content {
        color: #555;
        font-size: 16px;
        line-height: 1.7;
        margin-left: 47px;
    }

    .formula {
        background: #fff3e0;
        padding: 18px;
        border-radius: 10px;
        margin: 15px 0;
        font-family: 'Courier New', monospace;
        font-size: 18px;
        text-align: center;
        border: 2px dashed #ff9800;
        color: #e65100;
        font-weight: 600;
    }

    .answer-box {
        background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
        padding: 30px;
        border-radius: 15px;
        margin: 30px 0;
        text-align: center;
        color: white;
        box-shadow: 0 8px 20px rgba(76, 175, 80, 0.4);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.02); }
    }

    .answer-box h3 {
        font-size: 24px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .answer-box h3::before {
        content: "✅";
        margin-right: 10px;
        font-size: 28px;
    }

    .answer-value {
        font-size: 48px;
        font-weight: bold;
        margin: 15px 0;
        text-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }

    .interactive-section {
        background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
        padding: 30px;
        border-radius: 15px;
        margin: 30px 0;
        border-left: 6px solid #ff9800;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .interactive-section h3 {
        color: #e65100;
        margin-bottom: 25px;
        font-size: 22px;
        display: flex;
        align-items: center;
    }

    .interactive-section h3::before {
        content: "🎮";
        margin-right: 10px;
        font-size: 26px;
    }

    .input-group {
        margin: 20px 0;
        background: white;
        padding: 20px;
        border-radius: 10px;
    }

    .input-group label {
        display: block;
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
        font-size: 16px;
    }

    .input-group input {
        width: 100%;
        padding: 15px;
        font-size: 18px;
        border: 2px solid #ddd;
        border-radius: 8px;
        transition: all 0.3s;
    }

    .input-group input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .calculate-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 15px 40px;
        font-size: 18px;
        font-weight: 600;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        width: 100%;
        margin-top: 10px;
    }

    .calculate-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }

    .calculate-btn:active {
        transform: translateY(0);
    }

    .result-display {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-top: 20px;
        border: 3px solid #4caf50;
        display: none;
    }

    .result-display.show {
        display: block;
        animation: slideIn 0.5s;
    }

    @keyframes slideIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .result-display h4 {
        color: #2e7d32;
        font-size: 20px;
        margin-bottom: 15px;
    }

    .result-value {
        font-size: 36px;
        color: #4caf50;
        font-weight: bold;
        text-align: center;
        margin: 15px 0;
    }

    .explanation {
        background: #f5f5f5;
        padding: 15px;
        border-radius: 8px;
        margin-top: 15px;
        font-size: 14px;
        color: #666;
        line-height: 1.6;
    }

    .note {
        background: #e1f5fe;
        padding: 15px;
        border-radius: 10px;
        border-left: 4px solid #03a9f4;
        margin: 20px 0;
        font-size: 15px;
        color: #01579b;
    }

    .note::before {
        content: "💡 ";
        font-size: 18px;
    }
</style>

<div class="container">
    <!-- Заголовок -->
    <div class="header">
        <h1>Задача 1 ОГЭ 2026</h1>
        <div class="subtitle">Геометрическая задача на вычисление</div>
        <span class="badge">БЛОК 1. ФИПИ</span>
    </div>

    <!-- Условие задачи -->
    <div class="task-box">
        <h2>Условие задачи</h2>
        <div class="task-text">
            Биссектриса угла A параллелограмма ABCD пересекает сторону BC в точке K.
            Найдите периметр параллелограмма, если BK=5, CK=14.
        </div>
    </div>

    <!-- Данные -->
    <div class="given-data">
        <h3>Дано:</h3>
        <p>• Параллелограмм ABCD</p>
        <p>• AK — биссектриса угла A</p>
        <p>• K лежит на стороне BC</p>
        <p>• BK = 5</p>
        <p>• CK = 14</p>
        <p><strong>Найти:</strong> Периметр параллелограмма ABCD</p>
    </div>

    <!-- Иллюстрация -->
    <div class="illustration">
        <h3>📐 Чертёж</h3>
        <svg width="700" height="350" viewBox="0 0 700 350">
            <!-- Параллелограмм ABCD с математически точными пропорциями -->
            <!-- AB = BK = CD = 100 пикселей (5 единиц) -->
            <!-- KC = 280 пикселей (14 единиц) -->
            <!-- BC = 380 пикселей (19 единиц) -->

            <polygon points="80,280 151,209 531,209 460,280"
                     fill="rgba(102, 126, 234, 0.05)" stroke="#667eea" stroke-width="3"/>

            <!-- Биссектриса AK -->
            <line x1="80" y1="280" x2="251" y2="209"
                  stroke="#ff5722" stroke-width="3" stroke-dasharray="8,5"/>

            <!-- Точки -->
            <circle cx="80" cy="280" r="7" fill="#764ba2"/>
            <circle cx="151" cy="209" r="7" fill="#764ba2"/>
            <circle cx="531" cy="209" r="7" fill="#764ba2"/>
            <circle cx="460" cy="280" r="7" fill="#764ba2"/>
            <circle cx="251" cy="209" r="8" fill="#ff5722"/>

            <!-- Подписи вершин -->
            <text x="55" y="300" font-size="28" fill="#764ba2" font-weight="bold">A</text>
            <text x="126" y="200" font-size="28" fill="#764ba2" font-weight="bold">B</text>
            <text x="541" y="200" font-size="28" fill="#764ba2" font-weight="bold">C</text>
            <text x="470" y="300" font-size="28" fill="#764ba2" font-weight="bold">D</text>
            <text x="261" y="200" font-size="28" fill="#ff5722" font-weight="bold">K</text>

            <!-- Обозначения отрезков BK и CK -->
            <text x="196" y="200" font-size="22" fill="#4caf50" font-weight="bold">5</text>
            <text x="380" y="200" font-size="22" fill="#4caf50" font-weight="bold">14</text>

            <!-- Метки равных отрезков - зелёные штрихи -->
            <!-- На стороне AB -->
            <line x1="111" y1="240" x2="121" y2="250" stroke="#4caf50" stroke-width="4"/>
            <!-- На отрезке BK -->
            <line x1="196" y1="204" x2="206" y2="214" stroke="#4caf50" stroke-width="4"/>
            <!-- На стороне CD -->
            <line x1="491" y1="240" x2="501" y2="250" stroke="#4caf50" stroke-width="4"/>

            <!-- Подпись длины BC (только эта осталась) -->
            <text x="340" y="320" font-size="19" fill="#2196f3" font-weight="bold">BC = 19</text>
        </svg>
        <p style="color: #666; font-size: 14px; margin-top: 15px; line-height: 1.6;">
            🟢 <strong>Зелёные штрихи:</strong> равные отрезки AB = BK = CD = 5<br>
            🔴 <strong>Красная линия:</strong> биссектриса угла A
        </p>
    </div>

    <!-- Решение -->
    <div class="solution-section">
        <h3>Решение</h3>

        <div class="step">
            <div class="step-header">
                <span class="step-number">1</span>
                <span class="step-title">Найдём сторону BC</span>
            </div>
            <div class="step-content">
                BC = BK + CK = 5 + 14 = 19
            </div>
            <div class="formula">
                BC = 19
            </div>
        </div>

        <div class="step">
            <div class="step-header">
                <span class="step-number">2</span>
                <span class="step-title">Используем свойство биссектрисы</span>
            </div>
            <div class="step-content">
                Когда биссектриса угла параллелограмма пересекает противоположную сторону,
                образуется равнобедренный треугольник. Поскольку AK — биссектриса угла A,
                и BC параллельна AD, то по свойству накрест лежащих углов: ∠BAK = ∠AKB.
                <br><br>
                Это означает, что треугольник ABK — равнобедренный, следовательно:
            </div>
            <div class="formula">
                AB = BK = 5
            </div>
        </div>

        <div class="step">
            <div class="step-header">
                <span class="step-number">3</span>
                <span class="step-title">Используем свойства параллелограмма</span>
            </div>
            <div class="step-content">
                В параллелограмме противоположные стороны равны:
                <br>• AB = CD = 5
                <br>• BC = AD = 19
            </div>
        </div>

        <div class="step">
            <div class="step-header">
                <span class="step-number">4</span>
                <span class="step-title">Вычисляем периметр</span>
            </div>
            <div class="step-content">
                Периметр параллелограмма равен сумме всех его сторон:
            </div>
            <div class="formula">
                P = AB + BC + CD + AD = 5 + 19 + 5 + 19 = 48
            </div>
        </div>
    </div>

    <div class="note">
        <strong>Важное свойство:</strong> Если биссектриса угла параллелограмма пересекает
        противоположную сторону, то она отсекает от параллелограмма равнобедренный треугольник.
    </div>

    <!-- Ответ -->
    <div class="answer-box">
        <h3>Ответ</h3>
        <div class="answer-value">48</div>
        <p style="font-size: 18px;">Периметр параллелограмма равен 48</p>
    </div>

    <!-- Интерактивный калькулятор -->
    <div class="interactive-section">
        <h3>Попробуйте решить с другими данными</h3>
        <p style="margin-bottom: 20px; color: #666;">
            Введите свои значения BK и CK, чтобы найти периметр параллелограмма:
        </p>

        <div class="input-group">
            <label for="bk-input">Длина отрезка BK:</label>
            <input type="number" id="bk-input" placeholder="Введите BK" value="5" min="0.1" step="0.1">
        </div>

        <div class="input-group">
            <label for="ck-input">Длина отрезка CK:</label>
            <input type="number" id="ck-input" placeholder="Введите CK" value="14" min="0.1" step="0.1">
        </div>

        <button class="calculate-btn" onclick="calculate()">🧮 Вычислить периметр</button>

        <div class="result-display" id="result">
            <h4>Результат вычислений:</h4>
            <div class="result-value" id="result-value">—</div>
            <div class="explanation">
                <strong>Решение:</strong><br>
                <span id="explanation-text"></span>
            </div>
        </div>
    </div>
</div>

<script>
    function calculate() {
        const bk = parseFloat(document.getElementById('bk-input').value);
        const ck = parseFloat(document.getElementById('ck-input').value);

        if (isNaN(bk) || isNaN(ck) || bk <= 0 || ck <= 0) {
            alert('Пожалуйста, введите корректные положительные числа!');
            return;
        }

        // Вычисления
        const bc = bk + ck;
        const ab = bk;  // По свойству биссектрисы
        const perimeter = 2 * (ab + bc);

        // Отображение результата
        document.getElementById('result-value').textContent = perimeter;

        const explanation = `
            1. BC = BK + CK = ${bk} + ${ck} = ${bc}<br>
            2. AB = BK = ${bk} (по свойству биссектрисы)<br>
            3. P = 2(AB + BC) = 2(${ab} + ${bc}) = 2 × ${ab + bc} = ${perimeter}
        `;

        document.getElementById('explanation-text').innerHTML = explanation;
        document.getElementById('result').classList.add('show');
    }

    // Автоматический расчёт при изменении значений
    document.getElementById('bk-input').addEventListener('input', function() {
        document.getElementById('result').classList.remove('show');
    });

    document.getElementById('ck-input').addEventListener('input', function() {
        document.getElementById('result').classList.remove('show');
    });
</script>

<?php include 'includes/footer.php'; ?>
