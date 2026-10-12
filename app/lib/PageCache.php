<?php
declare(strict_types=1);

/**
 * Ziyaretçiler (oturumu olmayanlar) için tam sayfa önbelleği.
 * İçerik değişince Settings::bump() sürümü artırır; eski kayıtlar kendiliğinden geçersiz olur.
 */
final class PageCache
{
    private const TTL = 86400;
    private static bool $allowed = false;

    public static function eligible(): bool
    {
        return Request::method() === 'GET' && !Session::hasCookie() && !Request::wantsJson()
            && strlen($_SERVER['QUERY_STRING'] ?? '') < 600;
    }

    private static function file(): string
    {
        $key = Request::host() . '|' . Request::path() . '?' . ($_SERVER['QUERY_STRING'] ?? '');
        return Cache::dir('sayfa') . '/' . hash('xxh128', $key) . '.html';
    }

    /** Uygunsa önbellekteki sayfayı gönderir ve çıkar */
    public static function serve(): void
    {
        if (!self::eligible()) {
            return;
        }
        $f = self::file();
        if (!is_file($f) || filemtime($f) < time() - self::TTL) {
            return;
        }
        $h = @fopen($f, 'rb');
        if (!$h) {
            return;
        }
        $stamp = rtrim((string) fgets($h));
        if ($stamp !== 'v' . Settings::get('surum')) {
            fclose($h);
            return;
        }
        header('Content-Type: text/html; charset=utf-8');
        header('X-Kunye-Cache: hit');
        header('Cache-Control: public, max-age=60');
        fpassthru($h);
        fclose($h);
        exit;
    }

    /** Denetleyici bu sayfanın önbelleğe alınabileceğini işaretler */
    public static function allow(): void
    {
        self::$allowed = true;
    }

    public static function store(string $html): void
    {
        if (!self::$allowed || !self::eligible() || Auth::user() !== null || http_response_code() !== 200) {
            return;
        }
        Cache::atomic(self::file(), 'v' . Settings::get('surum') . "\n" . $html);
        if (random_int(1, 200) === 1) {
            self::prune();
        }
    }

    /** Eski sürüme ait ya da süresi geçmiş kayıtları sil (ara sıra, arka planda) */
    public static function prune(): void
    {
        $stamp = 'v' . Settings::get('surum');
        foreach (glob(Cache::dir('sayfa') . '/*.html') ?: [] as $f) {
            $h = @fopen($f, 'rb');
            $first = $h ? rtrim((string) fgets($h)) : '';
            if ($h) {
                fclose($h);
            }
            if ($first !== $stamp || filemtime($f) < time() - self::TTL) {
                @unlink($f);
            }
        }
    }
}
