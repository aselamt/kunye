<?php
declare(strict_types=1);

final class Session
{
    public const NAME = 'kunye_oturum';
    public const REMEMBER = 'kunye_hatirla';
    private const IDLE = 7200;

    public static function hasCookie(): bool
    {
        return isset($_COOKIE[self::NAME]) || isset($_COOKIE[self::REMEMBER]);
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (PHP_SAPI === 'cli' && !isset($_SERVER['REQUEST_METHOD'])) {
            $_SESSION ??= [];
            return; // testler / komut satırı
        }
        $dir = KUNYE_DATA . '/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) self::IDLE);
        session_name(self::NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (Request::base() ?: '') . '/',
            'secure' => Request::https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_cache_limiter('nocache');
        session_start();
        $now = time();
        if (isset($_SESSION['_t']) && $now - $_SESSION['_t'] > self::IDLE) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['_t'] = $now;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        } else {
            $_SESSION = [];
        }
        self::clearCookie(self::NAME);
    }

    public static function setCookie(string $name, string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie($name, $value, [
            'expires' => $expires,
            'path' => (Request::base() ?: '') . '/',
            'secure' => Request::https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearCookie(string $name): void
    {
        self::setCookie($name, '', time() - 3600);
        unset($_COOKIE[$name]);
    }
}
