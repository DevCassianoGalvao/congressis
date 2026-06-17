<?php
// Conversor local — rodar via CLI, não commitar
$base = __DIR__ . '/assets';
$quality = 85;
$converted = 0;
$skipped = 0;
$errors = [];

function convert_to_webp(string $src, int $quality): bool {
    $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
    $dest = preg_replace('/\.(jpe?g|png)$/i', '.webp', $src);

    if (file_exists($dest) && filemtime($dest) >= filemtime($src)) return false; // já existe e é mais novo

    $img = match($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($src),
        'png'         => @imagecreatefrompng($src),
        default       => false,
    };
    if (!$img) return false;

    if ($ext === 'png') {
        imagealphablending($img, false);
        imagesavealpha($img, true);
    }

    $ok = imagewebp($img, $dest, $quality);
    imagedestroy($img);
    return $ok;
}

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
foreach ($it as $file) {
    if (!preg_match('/\.(jpe?g|png)$/i', $file->getFilename())) continue;
    $path = $file->getPathname();

    $ok = convert_to_webp($path, $quality);
    if ($ok === false && !file_exists(preg_replace('/\.(jpe?g|png)$/i', '.webp', $path))) {
        $errors[] = $path;
    } elseif ($ok === false) {
        $skipped++;
    } else {
        echo "✓ " . str_replace(__DIR__ . '/', '', $path) . "\n";
        $converted++;
    }
}

echo "\n--- $converted convertidas, $skipped já existiam ---\n";
if ($errors) { echo "Erros:\n"; foreach ($errors as $e) echo "  ✗ $e\n"; }
