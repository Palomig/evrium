<?php
/**
 * OGE Geometry Prototypes - Public View (Read Only)
 */
require_once __DIR__ . '/config/db.php';

// Get all prototypes
$prototypes = dbQuery("
    SELECT id, num, type, method, image
    FROM prototypes
    ORDER BY sort_order ASC, id ASC
");
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Геометрические прототипы ОГЭ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 30px;
            color: #e4e4e4;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #fff;
            font-weight: 300;
            font-size: 2.2rem;
        }

        .table-wrapper {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 20px;
            overflow-x: auto;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            vertical-align: top;
        }

        th {
            background: rgba(102, 126, 234, 0.3);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
            color: #a8b2d1;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        th:first-child {
            border-radius: 8px 0 0 0;
        }

        th:last-child {
            border-radius: 0 8px 0 0;
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .col-num {
            width: 120px;
            white-space: nowrap;
            font-weight: 600;
            color: #667eea;
        }

        .col-type {
            width: 250px;
            color: #a8b2d1;
        }

        .col-method {
            min-width: 400px;
            line-height: 1.6;
        }

        .image-cell {
            width: 160px;
            text-align: center;
        }

        .image-preview {
            width: 140px;
            height: 100px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .image-preview:hover {
            transform: scale(1.05);
        }

        .no-image {
            color: #555;
            font-size: 12px;
        }

        .stats {
            margin-top: 20px;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            display: flex;
            gap: 30px;
            font-size: 14px;
            color: #a8b2d1;
            flex-wrap: wrap;
        }

        .stats span {
            color: #667eea;
            font-weight: 600;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            cursor: pointer;
        }

        .modal.show {
            display: flex;
        }

        .modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 12px;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            cursor: pointer;
        }

        @media (max-width: 1200px) {
            body {
                padding: 15px;
            }

            th, td {
                padding: 10px;
                font-size: 13px;
            }
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 1.5rem;
            }

            .col-method {
                min-width: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Геометрические прототипы ОГЭ</h1>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th class="col-num">№ прототипов</th>
                        <th class="col-type">Геометрический тип задачи</th>
                        <th class="col-method">Ключевой принцип и метод решения</th>
                        <th>Изображение</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prototypes as $row): ?>
                    <tr>
                        <td class="col-num"><?= htmlspecialchars($row['num']) ?></td>
                        <td class="col-type"><?= htmlspecialchars($row['type']) ?></td>
                        <td class="col-method"><?= htmlspecialchars($row['method']) ?></td>
                        <td class="image-cell">
                            <?php if (!empty($row['image'])): ?>
                                <img src="<?= htmlspecialchars($row['image']) ?>"
                                     class="image-preview"
                                     onclick="openModal(this.src)"
                                     alt="Изображение">
                            <?php else: ?>
                                <span class="no-image">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="stats">
            <div>Всего прототипов: <span><?= count($prototypes) ?></span></div>
            <div>С изображениями: <span><?= count(array_filter($prototypes, fn($p) => !empty($p['image']))) ?></span></div>
        </div>
    </div>

    <div class="modal" id="imageModal" onclick="closeModal()">
        <span class="modal-close">&times;</span>
        <img id="modalImage" src="" alt="Увеличенное изображение">
    </div>

    <script>
        function openModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('imageModal').classList.remove('show');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>
