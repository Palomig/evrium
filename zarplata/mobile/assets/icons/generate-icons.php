<?php
/**
 * Генератор иконок PWA
 * Создает PNG иконки с буквой "Э" на teal фоне
 */

$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$bgColor = [20, 184, 166]; // #14b8a6 (teal)
$textColor = [255, 255, 255]; // white

foreach ($sizes as $size) {
    // Создаем изображение
    $img = imagecreatetruecolor($size, $size);

    // Включаем сглаживание
    imagealphablending($img, true);
    imageantialias($img, true);

    // Цвета
    $bg = imagecolorallocate($img, $bgColor[0], $bgColor[1], $bgColor[2]);
    $white = imagecolorallocate($img, $textColor[0], $textColor[1], $textColor[2]);

    // Заливаем фон
    imagefilledrectangle($img, 0, 0, $size, $size, $bg);

    // Скругленные углы (рисуем маску)
    $radius = (int)($size * 0.1875); // ~18.75% радиус

    // Рисуем буквы "Э"
    $fontSize = (int)($size * 0.55);
    $letter = "Э";

    // Пробуем найти шрифт
    $fontPath = null;
    $possibleFonts = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
    ];

    foreach ($possibleFonts as $font) {
        if (file_exists($font)) {
            $fontPath = $font;
            break;
        }
    }

    if ($fontPath) {
        // Используем TrueType шрифт
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $letter);
        $textWidth = abs($bbox[4] - $bbox[0]);
        $textHeight = abs($bbox[5] - $bbox[1]);
        $x = ($size - $textWidth) / 2 - $bbox[0];
        $y = ($size + $textHeight) / 2;
        imagettftext($img, $fontSize, 0, (int)$x, (int)$y, $white, $fontPath, $letter);
    } else {
        // Fallback: используем встроенный шрифт
        $font = 5; // Самый большой встроенный шрифт
        $charWidth = imagefontwidth($font);
        $charHeight = imagefontheight($font);

        // Масштабируем через временное изображение
        $scale = $fontSize / $charHeight;
        $tempWidth = (int)($charWidth * $scale);
        $tempHeight = (int)($charHeight * $scale);

        $temp = imagecreatetruecolor($charWidth, $charHeight);
        $tempBg = imagecolorallocate($temp, $bgColor[0], $bgColor[1], $bgColor[2]);
        $tempWhite = imagecolorallocate($temp, 255, 255, 255);
        imagefill($temp, 0, 0, $tempBg);
        imagestring($temp, $font, 0, 0, $letter, $tempWhite);

        // Масштабируем и вставляем
        $x = ($size - $tempWidth) / 2;
        $y = ($size - $tempHeight) / 2;
        imagecopyresampled($img, $temp, (int)$x, (int)$y, 0, 0, $tempWidth, $tempHeight, $charWidth, $charHeight);
        imagedestroy($temp);
    }

    // Сохраняем PNG
    $filename = __DIR__ . "/icon-{$size}x{$size}.png";
    imagepng($img, $filename);
    imagedestroy($img);

    echo "Created: icon-{$size}x{$size}.png\n";
}

echo "\nAll icons generated successfully!\n";
