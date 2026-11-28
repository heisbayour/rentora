<?php

declare(strict_types=1);

/*
 * Generates the Android launcher icons and splash images from the site's
 * icons (public/icons). Run from android-app/: php scripts/make-icons.php
 *
 * - ic_launcher.png         square icon (icon-512.png)
 * - ic_launcher_round.png   the same, cut to a circle
 * - ic_launcher_foreground  adaptive icon foreground (icon-maskable-512.png,
 *                           whose artwork sits inside the adaptive safe zone)
 * - splash.png              white screen with the icon in the middle (Android 11 and older)
 */

$root = dirname(__DIR__);
$icons = dirname($root).'/public/icons';
$res = $root.'/android/app/src/main/res';

$square = imagecreatefrompng($icons.'/icon-512.png');
$maskable = imagecreatefrompng($icons.'/icon-maskable-512.png');

function canvas(int $w, int $h, ?array $rgb = null)
{
    $img = imagecreatetruecolor($w, $h);
    imagesavealpha($img, true);
    imagealphablending($img, false);
    $fill = $rgb === null
        ? imagecolorallocatealpha($img, 0, 0, 0, 127)
        : imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, $fill);
    imagealphablending($img, true);

    return $img;
}

function scaled($src, int $size)
{
    $img = canvas($size, $size);
    imagecopyresampled($img, $src, 0, 0, 0, 0, $size, $size, imagesx($src), imagesy($src));

    return $img;
}

/** Keeps only the pixels inside a circle (or a rounded square), with a soft edge. */
function masked($img, float $cornerRatio)
{
    $size = imagesx($img);
    $out = canvas($size, $size);
    imagealphablending($out, false);
    $r = $size * $cornerRatio;
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $px = $x + 0.5;
            $py = $y + 0.5;
            $cx = min(max($px, $r), $size - $r);
            $cy = min(max($py, $r), $size - $r);
            $d = sqrt(($px - $cx) ** 2 + ($py - $cy) ** 2);
            $coverage = max(0.0, min(1.0, $r - $d + 0.5));
            if ($coverage <= 0) {
                continue;
            }
            $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
            $alpha = 127 - (int) round((127 - $c['alpha']) * $coverage);
            imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $c['red'], $c['green'], $c['blue'], $alpha));
        }
    }

    return $out;
}

$densities = ['mdpi' => 1.0, 'hdpi' => 1.5, 'xhdpi' => 2.0, 'xxhdpi' => 3.0, 'xxxhdpi' => 4.0];

foreach ($densities as $name => $scale) {
    $dir = "{$res}/mipmap-{$name}";
    $launcher = (int) round(48 * $scale);
    imagepng(masked(scaled($square, $launcher), 0.12), "{$dir}/ic_launcher.png");
    imagepng(masked(scaled($square, $launcher), 0.5), "{$dir}/ic_launcher_round.png");
    imagepng(scaled($maskable, (int) round(108 * $scale)), "{$dir}/ic_launcher_foreground.png");
}

// Splash images: same sizes as Capacitor's template, white with a rounded icon.
foreach (glob($res.'/drawable*/splash.png') as $file) {
    [$w, $h] = getimagesize($file);
    $img = canvas($w, $h, [255, 255, 255]);
    $size = (int) round(min($w, $h) * 0.3);
    $icon = masked(scaled($square, $size), 0.18);
    imagecopy($img, $icon, intdiv($w - $size, 2), intdiv($h - $size, 2), 0, 0, $size, $size);
    imagepng($img, $file);
}

echo "Icons and splash images written to android/app/src/main/res\n";
