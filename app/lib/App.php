<?php
declare(strict_types=1);

final class App
{
    public static function run(): void
    {
        Response::securityHeaders();
        try {
            $path = Request::path();
            if (!Installer::installed()) {
                if ($path === 'kurulum' || str_starts_with($path, 'kurulum/')) {
                    InstallController::handle($path);
                    return;
                }
                if (str_starts_with($path, 'assets/') || str_starts_with($path, 'uploads/')) {
                    throw new HttpError(404);
                }
                Response::redirect(url('kurulum'), 302);
            }
            Schema::migrate();
            PageCache::serve();
            $router = new Router();
            require KUNYE_APP . '/routes.php';
            $router->dispatch(Request::method() === 'HEAD' ? 'GET' : Request::method(), $path);
        } catch (HttpError $e) {
            self::error($e->getCode(), $e->getMessage());
        } catch (Throwable $e) {
            self::log($e);
            self::error(500, self::debug() ? $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() : '');
        }
    }

    public static function debug(): bool
    {
        return PHP_SAPI === 'cli-server' || is_file(KUNYE_DATA . '/hata-ayikla');
    }

    public static function log(Throwable $e): void
    {
        $dir = KUNYE_DATA . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $line = date('c') . ' ' . Request::method() . ' /' . Request::path() . "\n" . $e::class . ': ' . $e->getMessage()
            . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() . "\n\n";
        @file_put_contents($dir . '/hata.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function error(int $code, string $message = ''): never
    {
        $titles = [
            403 => 'Erişim yok', 404 => 'Sayfa bulunamadı', 405 => 'Yöntem desteklenmiyor',
            419 => 'Oturum süresi doldu', 429 => 'Çok fazla deneme', 500 => 'Bir şeyler ters gitti',
        ];
        $code = isset($titles[$code]) ? $code : 500;
        if (Request::wantsJson()) {
            Response::json(['hata' => $message ?: $titles[$code]], $code);
        }
        try {
            $html = View::render('site/hata', ['code' => $code, 'title' => $titles[$code], 'message' => $message], Installer::installed() ? 'site' : 'auth');
        } catch (Throwable $e) {
            self::log($e);
            $html = '<!doctype html><meta charset="utf-8"><title>' . $code . '</title><p style="font:16px system-ui;margin:3rem">' . e($titles[$code]) . '</p>';
        }
        Response::html($html, $code);
    }
}
