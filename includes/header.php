<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : 'Интерактивный сайт по геометрии для 7-9 классов (учебник Атанасяна)'; ?>">
    <title><?php echo isset($page_title) ? $page_title : 'Интерактивная геометрия'; ?> | Атанасян 7-9 класс</title>

    <!-- CSS Framework - Bootstrap через CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome для иконок -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Кастомные стили -->
    <link rel="stylesheet" href="css/main.css">
</head>
<body>
    <!-- Навигационное меню -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-shapes me-2"></i>
                Интерактивная геометрия
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'index') ? 'active' : ''; ?>" href="index.php">
                            <i class="fas fa-home"></i> Главная
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'oge') ? 'active' : ''; ?>" href="oge/index.php">
                            <i class="fas fa-graduation-cap"></i> ОГЭ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'examples') ? 'active' : ''; ?>" href="examples.php">
                            <i class="fas fa-book"></i> Примеры
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'editor') ? 'active' : ''; ?>" href="editor.php">
                            <i class="fas fa-edit"></i> Редактор
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'docs') ? 'active' : ''; ?>" href="docs.php">
                            <i class="fas fa-book-open"></i> Документация
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
