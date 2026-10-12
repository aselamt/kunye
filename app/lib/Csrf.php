<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Tüm POST isteklerinde çağrılır: belirteç + (varsa) Origin denetimi */
    public static function check(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '' && $origin !== 'null' && strcasecmp(rtrim($origin, '/'), Request::origin()) !== 0) {
            throw new HttpError(403, 'Bu istek başka bir siteden geldiği için reddedildi.');
        }
        Session::start();
        $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
        $have = $_SESSION['_csrf'] ?? '';
        if (!is_string($sent) || !is_string($have) || $have === '' || !hash_equals($have, $sent)) {
            throw new HttpError(419, 'Oturum süresi doldu ya da form geçersiz. Sayfayı yenileyip tekrar dene.');
        }
    }
}
