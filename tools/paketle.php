<?php
/**
 * Yükleme paketi: dist/kunye-<sürüm>.zip
 * Dosyalar zip'in köküne yerleşir; public_html (ya da alt klasör) içinde açınca doğrudan çalışır.
 * Çalıştır: php tools/paketle.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';
if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive eklentisi gerekli (php-zip).\n");
    exit(1);
}
$include = ['index.php', '.htaccess', '.user.ini', 'LICENSE', 'README.md', 'app', 'assets',
    'data/.htaccess', 'data/index.html', 'data/koruma.txt', 'uploads/.htaccess', 'uploads/index.html'];
@mkdir($root . '/dist', 0755, true);
$out = $root . '/dist/kunye-' . KUNYE_VERSION . '.zip';
@unlink($out);
$zip = new ZipArchive();
$zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$n = 0;
foreach ($include as $rel) {
    $path = $root . '/' . $rel;
    if (is_file($path)) {
        $zip->addFile($path, $rel);
        $n++;
        continue;
    }
    if (!is_dir($path)) {
        fwrite(STDERR, "Eksik: $rel\n");
        exit(1);
    }
    $zip->addEmptyDir($rel);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) {
        $local = $rel . '/' . substr($f->getPathname(), strlen($path) + 1);
        if (basename($local) === '.DS_Store') {
            continue;
        }
        if ($f->isDir()) {
            $zip->addEmptyDir($local);
        } else {
            $zip->addFile($f->getPathname(), $local);
            $n++;
        }
    }
}
$zip->close();
printf("%s · %d dosya · %.0f KB\n", substr($out, strlen($root) + 1), $n, filesize($out) / 1024);
