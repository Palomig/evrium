<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Подготовка к ОГЭ'; ?> | Эвриум</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            min-height: 100vh;
            background: #f5f5f5;
        }

        /* Боковое меню */
        .oge-sidebar {
            width: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .oge-sidebar h1 {
            font-size: 24px;
            margin-bottom: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255,255,255,0.3);
        }

        .oge-sidebar h1 i {
            margin-right: 8px;
        }

        .oge-sidebar .subtitle {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 25px;
        }

        .oge-menu {
            list-style: none;
        }

        .oge-menu li {
            margin-bottom: 10px;
        }

        .oge-menu a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 12px 15px;
            border-radius: 8px;
            transition: all 0.3s;
            background: rgba(255,255,255,0.1);
        }

        .oge-menu a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateX(5px);
        }

        .oge-menu a.active {
            background: rgba(255,255,255,0.3);
            font-weight: bold;
        }

        .oge-menu a i {
            margin-right: 10px;
            width: 20px;
            display: inline-block;
        }

        .back-link {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid rgba(255,255,255,0.3);
        }

        .back-link a {
            background: rgba(255,255,255,0.2);
        }

        /* Основной контент */
        .oge-content {
            margin-left: 280px;
            flex: 1;
            padding: 40px;
            width: calc(100% - 280px);
        }

        .content-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        /* Font Awesome для иконок */
        @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
    </style>
