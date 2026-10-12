<?php
/**
 * künye — tek giriş noktası.
 * Apache/LiteSpeed'de .htaccess tüm istekleri buraya yönlendirir.
 * Geliştirme: php -S localhost:8000 index.php
 */
declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    // Yerleşik sunucu: yalnızca assets/ ve uploads/ altındaki gerçek dosyaları doğrudan ver.
    $p = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    if (preg_match('#^/(assets|uploads)/[^\0]+\.(css|js|svg|webp|avif|png|jpe?g|gif|ico|woff2)$#i', $p)
        && !str_contains($p, '..') && is_file(__DIR__ . $p)) {
        return false;
    }
}

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('künye PHP 8.2 ya da üstünü gerektirir. Şu anki sürüm: ' . PHP_VERSION);
}

require __DIR__ . '/app/bootstrap.php';
App::run();
