<?php
declare(strict_types=1);

/**
 * Dosya önbelleği. Diziler PHP dosyası olarak yazılır; OPcache açıksa bellekten okunur.
 * Konum: data/cache/
 */
final class Cache
{
    public static function dir(string $sub = ''): string
    {
        $d = KUNYE_DATA . '/cache' . ($sub !== '' ? '/' . $sub : '');
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
        return $d;
    }

    public static function readPhp(string $file): mixed
    {
        if (!is_file($file)) {
            return null;
        }
        try {
            return include $file;
        } catch (Throwable) {
            return null;
        }
    }

    public static function writePhp(string $file, mixed $data): void
    {
        self::atomic($file, '<?php return ' . var_export($data, true) . ";\n");
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
    }

    public static function atomic(string $file, string $content): void
    {
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            return; // önbellek yazılamıyorsa sessizce devam
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }

    /** Tüm önbelleği sil; [dosya sayısı, bayt] döner */
    public static function clear(): array
    {
        $n = 0;
        $b = 0;
        $root = KUNYE_DATA . '/cache';
        if (!is_dir($root)) {
            return [0, 0];
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            if ($f->isFile()) {
                $b += $f->getSize();
                $n++;
                @unlink($f->getPathname());
            } elseif ($f->isDir()) {
                @rmdir($f->getPathname());
            }
        }
        return [$n, $b];
    }

    public static function stats(): array
    {
        $n = 0;
        $b = 0;
        $root = KUNYE_DATA . '/cache';
        if (is_dir($root)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $f) {
                if ($f->isFile()) {
                    $n++;
                    $b += $f->getSize();
                }
            }
        }
        return [$n, $b];
    }
}
