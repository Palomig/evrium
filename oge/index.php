<?php
// Настройки страницы
$page_title = 'Подготовка к ОГЭ - Главная';
$_GET['section'] = 'main';

// Подключаем header и sidebar
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="content-wrapper">
    <h1 style="color: #667eea; margin-bottom: 20px;">
        <i class="fas fa-graduation-cap"></i> Подготовка к ОГЭ
    </h1>

    <p style="font-size: 18px; color: #666; line-height: 1.6; margin-bottom: 30px;">
        Добро пожаловать в раздел подготовки к ОГЭ по математике (геометрия).
        Используйте меню слева для навигации по разделам.
    </p>

    <div style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 25px; border-radius: 15px; border-left: 5px solid #2196f3; margin-bottom: 30px;">
        <h3 style="color: #1565c0; margin-bottom: 15px;">
            <i class="fas fa-info-circle"></i> Доступные разделы:
        </h3>
        <ul style="list-style: none; padding: 0;">
            <li style="padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.1);">
                <strong>Задачи 1-6:</strong> Геометрические задачи на вычисление (параллелограммы, треугольники)
            </li>
            <li style="padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.1);">
                <strong>Задачи 7-12:</strong> Задачи на ромбы, углы и диагонали
            </li>
            <li style="padding: 10px 0;">
                <strong>Полный гайд 7-9 класс:</strong> Комплексное руководство по геометрии
            </li>
        </ul>
    </div>

    <div style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); padding: 25px; border-radius: 15px; border-left: 5px solid #4caf50;">
        <h3 style="color: #2e7d32; margin-bottom: 15px;">
            <i class="fas fa-check-circle"></i> Что вы найдёте:
        </h3>
        <ul style="color: #333; line-height: 1.8;">
            <li>Интерактивные калькуляторы для проверки решений</li>
            <li>Подробные пошаговые объяснения</li>
            <li>Наглядные чертежи и визуализации</li>
            <li>Задачи из открытого банка ФИПИ</li>
        </ul>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
