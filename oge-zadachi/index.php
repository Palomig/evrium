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

$p23Images = [
    ['src' => 'assets/p23/oge15_p23_img1.png', 'label' => 'Прямоугольный треугольник — исходный рисунок'],
    ['src' => 'assets/p23/oge15_p23_img2.png', 'label' => 'Прямоугольный треугольник — схема 2'],
    ['src' => 'assets/p23/oge15_p23_img3.png', 'label' => 'Прямоугольный треугольник — схема 3'],
    ['src' => 'assets/p23/oge16_p23_img1.png', 'label' => 'Окружность — схема 1'],
    ['src' => 'assets/p23/oge16_p23_img2.png', 'label' => 'Окружность — схема 2'],
    ['src' => 'assets/p23/oge16_p23_img3.png', 'label' => 'Окружность — схема 3'],
    ['src' => 'assets/p23/oge16_p23_img4.png', 'label' => 'Окружность — схема 4'],
    ['src' => 'assets/p23/oge16_p23_img5.png', 'label' => 'Окружность — схема 5'],
    ['src' => 'assets/p23/oge16_p23_img6.png', 'label' => 'Окружность — схема 6'],
    ['src' => 'assets/p23/oge17_p23_img1.png', 'label' => 'Трапеция — схема 1'],
    ['src' => 'assets/p23/oge17_p23_img2.png', 'label' => 'Трапеция — схема 2'],
];
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
            object-fit: contain;
            background: #f8fafc;
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

        .p23-card {
            margin-bottom: 24px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 22px;
        }

        .p23-card h2 {
            color: #fff;
            font-weight: 400;
            margin-bottom: 10px;
        }

        .p23-card p {
            color: #a8b2d1;
            line-height: 1.6;
            margin-bottom: 18px;
        }

        .p23-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 16px;
        }

        .p23-figure {
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px;
            color: #334155;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.18);
        }

        .p23-figure img {
            width: 100%;
            height: 150px;
            object-fit: contain;
            display: block;
            cursor: pointer;
        }

        .p23-figure figcaption {
            margin-top: 8px;
            font-size: 12px;
            line-height: 1.35;
            text-align: center;
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

        <section class="p23-card" id="zadanie-23">
            <h2>Разбор задания 23 ОГЭ — старые PNG-рисунки</h2>
            <p>Восстановленные изображения из прежнего набора материалов. Нажмите на любой рисунок, чтобы открыть крупно.</p>
            <div class="p23-gallery">
                <?php foreach ($p23Images as $image): ?>
                    <figure class="p23-figure">
                        <img src="<?= htmlspecialchars($image['src']) ?>"
                             alt="<?= htmlspecialchars($image['label']) ?>"
                             onclick="openModal(this.src)">
                        <figcaption><?= htmlspecialchars($image['label']) ?></figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        </section>

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
