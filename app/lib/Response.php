<?php
declare(strict_types=1);

final class Response
{
    /** Testlerde exit yerine istisna atılır */
    public static bool $testing = false;

    public static function redirect(string $to, int $code = 303): never
    {
        if (self::$testing) {
            throw new RedirectSignal($to, $code);
        }
        header('Location: ' . $to, true, $code);
        exit;
    }

    /** Yalnızca uygulama içi yönlendirme; dışarıdan gelen 'donus' gibi değerler için */
    public static function back(?string $to, string $fallback): never
    {
        self::redirect(Request::safeReturn($to, $fallback));
    }

    public static function json(mixed $data, int $code = 200): never
    {
        if (self::$testing) {
            throw new JsonSignal($data, $code);
        }
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function html(string $html, int $code = 200): never
    {
        if (self::$testing) {
            throw new HtmlSignal($html, $code);
        }
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    public static function securityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; "
            . "script-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
        if (Request::https()) {
            header('Strict-Transport-Security: max-age=15552000');
        }
    }
}

class RedirectSignal extends RuntimeException
{
    public function __construct(public string $to, public int $status)
    {
        parent::__construct('redirect ' . $to);
    }
}

class JsonSignal extends RuntimeException
{
    public function __construct(public mixed $data, public int $status)
    {
        parent::__construct('json');
    }
}

class HtmlSignal extends RuntimeException
{
    public function __construct(public string $html, public int $status)
    {
        parent::__construct('html');
    }
}
