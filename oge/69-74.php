<?php
// Настройки страницы
$page_title = 'Полный гайд по геометрии 7-9 класс';
$_GET['section'] = '69-74';

// Подключаем header и sidebar
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    .content-wrapper {
        padding: 0;
        background: transparent;
        box-shadow: none;
        width: 100%;
        height: calc(100vh - 40px);
    }

    .oge-content {
        padding: 0;
    }

    iframe {
        width: 100%;
        height: 100%;
        border: none;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
</style>

<div class="content-wrapper">
    <iframe src="69-74/index.html"></iframe>
</div>

<?php include 'includes/footer.php'; ?>
