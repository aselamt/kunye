<?php
declare(strict_types=1);

const KUNYE_VERSION = '1.0.0';
define('KUNYE_ROOT', dirname(__DIR__));
define('KUNYE_APP', __DIR__);
if (!defined('KUNYE_DATA')) {
    define('KUNYE_DATA', getenv('KUNYE_DATA') ?: KUNYE_ROOT . '/data');
}
if (!defined('KUNYE_UPLOADS')) {
    define('KUNYE_UPLOADS', getenv('KUNYE_UPLOADS') ?: KUNYE_ROOT . '/uploads');
}

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Istanbul');
ini_set('default_charset', 'UTF-8');

spl_autoload_register(static function (string $class): void {
    foreach (['lib', 'controllers'] as $dir) {
        $f = KUNYE_APP . "/$dir/$class.php";
        if (is_file($f)) {
            require $f;
            return;
        }
    }
});

require KUNYE_APP . '/helpers.php';

set_error_handler(static function (int $no, string $msg, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    if ($no & (E_DEPRECATED | E_USER_DEPRECATED)) {
        return true; // sürüm farklarından gelen uyarılar uygulamayı durdurmasın
    }
    throw new ErrorException($msg, 0, $no, $file, $line);
});
