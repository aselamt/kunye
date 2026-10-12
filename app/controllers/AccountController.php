<?php
declare(strict_types=1);

final class AccountController
{
    private static function returnTo(): string
    {
        return Request::safeReturn(Request::get('donus') ?: Request::post('donus'), url());
    }

    public static function login(): void
    {
        if (Auth::user()) {
            Response::redirect(self::returnTo());
        }
        $errors = [];
        if (Request::isPost()) {
            $who = Request::post('kim');
            $key = 'giris:' . Request::ip();
            if (Request::post('web') !== '') {
                $errors[] = 'Giriş yapılamadı.';
            } elseif (Throttle::tooMany($key, 10, 900) || Throttle::tooMany('giris-ad:' . Text::fold($who), 10, 900)) {
                $errors[] = 'Çok fazla deneme yapıldı. 15 dakika sonra tekrar dene.';
            } else {
                $u = Auth::attempt($who, (string) ($_POST['sifre'] ?? ''));
                if ($u) {
                    Throttle::clear($key);
                    Auth::login($u, !empty($_POST['hatirla']));
                    Response::redirect(self::returnTo());
                }
                Throttle::hit($key);
                Throttle::hit('giris-ad:' . Text::fold($who));
                $errors[] = 'E-posta ya da şifre hatalı.';
            }
        }
        View::send('site/giris', ['title' => 'Giriş yap', 'mode' => 'giris', 'errors' => $errors, 'return' => self::rawReturn(), 'open' => Settings::bool('acik_kayit')], 'auth', $errors ? 422 : 200);
    }

    private static function rawReturn(): string
    {
        $r = Request::safeReturn(Request::get('donus'), '');
        return $r;
    }

    public static function register(): void
    {
        if (!Settings::bool('acik_kayit')) {
            throw new HttpError(404);
        }
        if (Auth::user()) {
            Response::redirect(self::returnTo());
        }
        $errors = [];
        if (Request::isPost()) {
            $user = Request::post('kullanici');
            $email = Request::post('eposta');
            $pass = (string) ($_POST['sifre'] ?? '');
            $key = 'kayit:' . Request::ip();
            if (Request::post('web') !== '') {
                $errors[] = 'Kayıt yapılamadı.';
            } elseif (Throttle::tooMany($key, 5, 3600)) {
                $errors[] = 'Bu ağdan çok fazla hesap açıldı. Biraz sonra tekrar dene.';
            } else {
                if (!Auth::validUsername($user)) {
                    $errors[] = 'Kullanıcı adı 3–32 karakter olmalı; harf, rakam, nokta, tire ve alt çizgi kullanılabilir.';
                } elseif (Db::val('SELECT 1 FROM users WHERE username = ?', [$user])) {
                    $errors[] = 'Bu kullanıcı adı alınmış.';
                }
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'E-posta adresi geçersiz.';
                } elseif ($email !== '' && Db::val('SELECT 1 FROM users WHERE email = ?', [$email])) {
                    $errors[] = 'Bu e-posta adresiyle bir hesap var.';
                }
                if (mb_strlen($pass) < 8) {
                    $errors[] = 'Şifre en az 8 karakter olmalı.';
                }
            }
            if (!$errors) {
                Throttle::hit($key);
                $id = Auth::create($user, $email ?: null, $pass);
                Auth::login(Db::one('SELECT * FROM users WHERE id = ?', [$id]));
                Response::redirect(self::returnTo());
            }
        }
        View::send('site/giris', ['title' => 'Kaydol', 'mode' => 'kayit', 'errors' => $errors, 'return' => self::rawReturn(), 'open' => true], 'auth', $errors ? 422 : 200);
    }

    public static function logout(): void
    {
        Auth::logout();
        Response::redirect(url());
    }

    public static function reset(string $token): void
    {
        $u = Auth::resetUser($token) ?? throw new HttpError(404, 'Bu bağlantının süresi dolmuş ya da daha önce kullanılmış.');
        $errors = [];
        if (Request::isPost()) {
            $p = (string) ($_POST['sifre'] ?? '');
            if (mb_strlen($p) < 8) {
                $errors[] = 'Şifre en az 8 karakter olmalı.';
            } elseif ($p !== ($_POST['sifre2'] ?? '')) {
                $errors[] = 'Şifreler aynı değil.';
            } else {
                Db::update('users', ['pass' => password_hash($p, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
                Db::exec('DELETE FROM tokens WHERE user_id = ?', [$u['id']]);
                $u = Db::one('SELECT * FROM users WHERE id = ?', [$u['id']]);
                Auth::login($u);
                Response::redirect(url());
            }
        }
        View::send('site/sifre', ['title' => 'Yeni şifre', 'user' => $u, 'errors' => $errors], 'auth');
    }

    public static function favorites(): void
    {
        $u = Auth::require();
        $tabs = Db::all('SELECT c.id, c.name, c.slug, COUNT(*) AS n FROM favorites f JOIN items i ON i.id = f.item_id JOIN categories c ON c.id = i.category_id
            WHERE f.user_id = ? AND i.status = \'published\' GROUP BY c.id ORDER BY c.name', [$u['id']]);
        $active = null;
        $slug = Request::get('kategori');
        foreach ($tabs as $t) {
            if ($t['slug'] === $slug) {
                $active = (int) $t['id'];
            }
        }
        $ids = Db::col('SELECT f.item_id FROM favorites f JOIN items i ON i.id = f.item_id WHERE f.user_id = ? AND i.status = \'published\''
            . ($active ? ' AND i.category_id = ' . $active : '') . ' ORDER BY f.created_at DESC LIMIT 500', [$u['id']]);
        $cards = Catalog::cards($ids);
        View::send('site/favoriler', [
            'title' => 'Favorilerim', 'noindex' => true, 'cards' => $cards, 'tabs' => count($tabs) > 1 ? $tabs : [], 'active' => $active,
            'favs' => array_fill_keys(array_map('intval', $ids), true), 'all' => array_sum(array_column($tabs, 'n')),
        ]);
    }

    public static function toggleFavorite(string $id): void
    {
        $u = Auth::require();
        $item = Db::one("SELECT * FROM items WHERE id = ? AND status = 'published'", [(int) $id]) ?? throw new HttpError(404);
        $on = !Db::val('SELECT 1 FROM favorites WHERE user_id = ? AND item_id = ?', [$u['id'], $item['id']]);
        Db::tx(static function () use ($u, $item, $on) {
            if ($on) {
                Db::exec('INSERT OR IGNORE INTO favorites (user_id, item_id, created_at) VALUES (?, ?, ?)', [$u['id'], $item['id'], time()]);
            } else {
                Db::exec('DELETE FROM favorites WHERE user_id = ? AND item_id = ?', [$u['id'], $item['id']]);
            }
            Db::exec('UPDATE items SET fav_count = (SELECT COUNT(*) FROM favorites WHERE item_id = ?) WHERE id = ?', [$item['id'], $item['id']]);
        });
        if (Request::wantsJson()) {
            Response::json(['favori' => $on]);
        }
        Response::back(Request::post('donus'), Catalog::itemUrl($item));
    }
}
