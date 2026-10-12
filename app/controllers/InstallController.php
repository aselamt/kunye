<?php
declare(strict_types=1);

/** /kurulum — yalnızca kurulum kilidi yokken çalışır */
final class InstallController
{
    public static function handle(string $path): void
    {
        if ($path === 'kurulum/test') {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'ok';
            exit;
        }
        if ($path !== 'kurulum') {
            throw new HttpError(404);
        }
        $checks = Installer::checks();
        $errors = [];
        if (Request::isPost()) {
            Csrf::check();
            $errors = Installer::install($_POST, !empty($_POST['ornek']));
            if (!$errors) {
                $u = Db::one("SELECT * FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
                Auth::login($u);
                flash('Kurulum tamamlandı. Kurulum ekranı kilitlendi.');
                Response::redirect(url('panel'));
            }
        }
        $html = View::render('site/kurulum', ['title' => 'Kurulum', 'checks' => $checks, 'errors' => $errors, 'ready' => Installer::ready($checks)], 'auth');
        Response::html($html, $errors ? 422 : 200);
    }
}
