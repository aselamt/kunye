<?php
declare(strict_types=1);

final class Auth
{
    public const ROLES = ['admin' => 'Yönetici', 'editor' => 'Editör', 'user' => 'Kullanıcı'];
    private const RANK = ['user' => 1, 'editor' => 2, 'admin' => 3];

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            self::$user = self::load();
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        return isset(self::user()['id']) ? (int) self::user()['id'] : null;
    }

    public static function is(string $role): bool
    {
        $u = self::user();
        return $u !== null && self::RANK[$u['role']] >= self::RANK[$role];
    }

    /** Yetki yoksa giriş sayfasına ya da 403'e */
    public static function require(string $role = 'user'): array
    {
        $u = self::user();
        if ($u === null) {
            if (Request::wantsJson()) {
                Response::json(['hata' => 'Giriş yapmalısın.', 'giris' => url('giris')], 401);
            }
            Response::redirect(url('giris', ['donus' => Request::uri()]));
        }
        if (!self::is($role)) {
            throw new HttpError(403, 'Bu sayfa için yetkin yok.');
        }
        return $u;
    }

    /** Testler için */
    public static function setUser(?array $u): void
    {
        self::$user = $u;
        self::$loaded = true;
    }

    private static function load(): ?array
    {
        if (!Session::hasCookie() && empty($_SESSION)) {
            return null;
        }
        Session::start();
        $id = $_SESSION['uid'] ?? null;
        if ($id) {
            $u = Db::one('SELECT * FROM users WHERE id = ? AND blocked = 0', [(int) $id]);
            if ($u && hash_equals((string) ($_SESSION['uh'] ?? ''), self::fingerprint($u))) {
                return $u;
            }
            unset($_SESSION['uid'], $_SESSION['uh']);
        }
        return self::fromRemember();
    }

    /** Şifre değişince eski oturumlar düşsün diye özetin bir kısmı oturuma bağlanır */
    private static function fingerprint(array $u): string
    {
        return hash('sha256', $u['id'] . '|' . $u['pass'] . '|' . $u['role']);
    }

    public static function attempt(string $login, string $password): ?array
    {
        $u = Db::one('SELECT * FROM users WHERE username = ? OR email = ?', [$login, $login]);
        if (!$u || !password_verify($password, $u['pass'])) {
            password_verify($password, '$2y$12$UxXd9bEXSUUVHDeRpLpru.XHkAi1Fu031KPV3bpHOpYh.uEx2C6Ry'); // zamanlama eşitleme
            return null;
        }
        if ((int) $u['blocked'] === 1) {
            return null;
        }
        if (password_needs_rehash($u['pass'], PASSWORD_DEFAULT)) {
            $u['pass'] = password_hash($password, PASSWORD_DEFAULT);
            Db::update('users', ['pass' => $u['pass']], 'id = ?', [$u['id']]);
        }
        return $u;
    }

    public static function login(array $u, bool $remember = false): void
    {
        Session::start();
        Session::regenerate();
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['uh'] = self::fingerprint($u);
        unset($_SESSION['_csrf']);
        Db::update('users', ['last_login' => time()], 'id = ?', [$u['id']]);
        if ($remember) {
            self::remember((int) $u['id']);
        }
        self::$user = $u;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        $c = $_COOKIE[Session::REMEMBER] ?? '';
        if (is_string($c) && str_contains($c, ':')) {
            Db::exec("DELETE FROM tokens WHERE kind = 'remember' AND selector = ?", [explode(':', $c)[0]]);
        }
        Session::clearCookie(Session::REMEMBER);
        Session::destroy();
        self::$user = null;
    }

    private static function remember(int $uid): void
    {
        $sel = bin2hex(random_bytes(9));
        $val = bin2hex(random_bytes(32));
        $exp = time() + 86400 * 30;
        Db::insert('tokens', ['user_id' => $uid, 'kind' => 'remember', 'selector' => $sel, 'hash' => hash('sha256', $val), 'expires' => $exp]);
        Session::setCookie(Session::REMEMBER, "$sel:$val", $exp);
    }

    private static function fromRemember(): ?array
    {
        $c = $_COOKIE[Session::REMEMBER] ?? '';
        if (!is_string($c) || !preg_match('/^([a-f0-9]{18}):([a-f0-9]{64})$/', $c, $m)) {
            return null;
        }
        $t = Db::one("SELECT * FROM tokens WHERE kind = 'remember' AND selector = ? AND expires > ?", [$m[1], time()]);
        if (!$t || !hash_equals($t['hash'], hash('sha256', $m[2]))) {
            Session::clearCookie(Session::REMEMBER);
            return null;
        }
        $u = Db::one('SELECT * FROM users WHERE id = ? AND blocked = 0', [$t['user_id']]);
        if (!$u) {
            return null;
        }
        // tek kullanımlık: yenisiyle değiştir
        Db::exec('DELETE FROM tokens WHERE id = ?', [$t['id']]);
        self::login($u, true);
        return $u;
    }

    /** Yöneticinin kullanıcıya verdiği şifre sıfırlama bağlantısı (e-posta gerekmez) */
    public static function resetLink(int $uid): string
    {
        Db::exec("DELETE FROM tokens WHERE user_id = ? AND kind = 'reset'", [$uid]);
        $sel = bin2hex(random_bytes(9));
        $val = bin2hex(random_bytes(32));
        Db::insert('tokens', ['user_id' => $uid, 'kind' => 'reset', 'selector' => $sel, 'hash' => hash('sha256', $val), 'expires' => time() + 86400 * 2]);
        return abs_url('sifre/' . $sel . $val);
    }

    public static function resetUser(string $token): ?array
    {
        if (!preg_match('/^([a-f0-9]{18})([a-f0-9]{64})$/', $token, $m)) {
            return null;
        }
        $t = Db::one("SELECT * FROM tokens WHERE kind = 'reset' AND selector = ? AND expires > ?", [$m[1], time()]);
        if (!$t || !hash_equals($t['hash'], hash('sha256', $m[2]))) {
            return null;
        }
        return Db::one('SELECT * FROM users WHERE id = ?', [$t['user_id']]);
    }

    public static function validUsername(string $u): bool
    {
        return (bool) preg_match('/^[\p{L}0-9_.\-]{3,32}$/u', $u);
    }

    public static function create(string $username, ?string $email, string $password, string $role = 'user'): int
    {
        return Db::insert('users', [
            'username' => $username,
            'email' => $email ?: null,
            'pass' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'created_at' => time(),
        ]);
    }
}
