<?php
// Определяем текущую страницу
$current_section = isset($_GET['section']) ? $_GET['section'] : 'main';
?>
</head>
<body>
    <!-- Боковое меню -->
    <aside class="oge-sidebar">
        <h1>
            <i class="fas fa-graduation-cap"></i>
            Подготовка к ОГЭ
        </h1>
        <div class="subtitle">Материалы для экзамена по математике</div>

        <ul class="oge-menu">
            <li>
                <a href="/oge/index.php" class="<?php echo ($current_section == 'main') ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    Главная
                </a>
            </li>
            <li>
                <a href="/oge/1-6.php" class="<?php echo ($current_section == '1-6') ? 'active' : ''; ?>">
                    <i class="fas fa-shapes"></i>
                    Задачи 1-6
                </a>
            </li>
            <li>
                <a href="/oge/7-12.php" class="<?php echo ($current_section == '7-12') ? 'active' : ''; ?>">
                    <i class="fas fa-gem"></i>
                    Задачи 7-12
                </a>
            </li>
            <li>
                <a href="/oge/69-74.php" class="<?php echo ($current_section == '69-74') ? 'active' : ''; ?>">
                    <i class="fas fa-book-open"></i>
                    Полный гайд 7-9 класс
                </a>
            </li>
        </ul>

        <div class="back-link">
            <a href="/index.php">
                <i class="fas fa-arrow-left"></i>
                Вернуться на главную
            </a>
        </div>
    </aside>

    <!-- Основной контент -->
    <div class="oge-content">
