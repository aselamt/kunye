<?php
declare(strict_types=1);

final class Request
{
    private static ?string $base = null;
    private static ?string $path = null;

    /** Uygulamanın kök yolu: '' (alan adının kökü) ya da '/alt/klasor' */
    public static function base(): string
    {
        if (self::$base === null) {
            $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
            self::$base = rtrim($dir === '.' ? '' : $dir, '/');
        }
        return self::$base;
    }

    /** Kök yola göre istek yolu, baştaki/sondaki eğik çizgi olmadan: 'telefon/xiaomi-17-ultra' */
    public static function path(): string
    {
        if (self::$path === null) {
            $p = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $p = rawurldecode($p);
            $base = self::base();
            if ($base !== '' && (str_starts_with($p, $base . '/') || $p === $base)) {
                $p = substr($p, strlen($base));
            }
            if (str_starts_with($p, '/index.php')) {
                $p = substr($p, 10); // mod_rewrite yoksa /index.php/telefon biçimi
            }
            self::$path = trim($p, '/');
        }
        return self::$path;
    }

    /** Testler için */
    public static function reset(?string $base = null, ?string $path = null): void
    {
        self::$base = $base;
        self::$path = $path;
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    }

    public static function https(): bool
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public static function host(): string
    {
        $h = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
        return preg_match('/^[a-z0-9.\-]+(:\d{1,5})?$/', $h) ? $h : 'localhost';
    }

    public static function origin(): string
    {
        return (self::https() ? 'https://' : 'http://') . self::host();
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function get(string $k, string $default = ''): string
    {
        $v = $_GET[$k] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public static function post(string $k, string $default = ''): string
    {
        $v = $_POST[$k] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public static function postArray(string $k): array
    {
        $v = $_POST[$k] ?? [];
        return is_array($v) ? $v : [];
    }

    /** Geçerli adres (sorgu dizesiyle), yalnızca uygulama içi */
    public static function uri(): string
    {
        $q = $_SERVER['QUERY_STRING'] ?? '';
        return url(self::path()) . ($q !== '' ? '?' . $q : '');
    }

    /**
     * Açık yönlendirmeye karşı: yalnızca bu uygulamanın içindeki göreli yolları kabul eder.
     * Kabul edilmezse $fallback döner.
     */
    public static function safeReturn(?string $to, string $fallback): string
    {
        $to = trim((string) $to);
        if ($to === '' || strlen($to) > 2000) {
            return $fallback;
        }
        // kontrol karakterleri, ters eğik çizgi, şema, protokole göre adres (//) yasak
        if (preg_match('/[\x00-\x1f\x7f\\\\]/', $to) || !str_starts_with($to, '/') || str_starts_with($to, '//')
            || preg_match('#^/[^/]*:#', $to)) {
            return $fallback;
        }
        $base = self::base();
        if ($base !== '' && $to !== $base && !str_starts_with($to, $base . '/') && !str_starts_with($to, $base . '?')) {
            return $fallback;
        }
        $parts = parse_url($to);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return $fallback;
        }
        return $to;
    }
}
