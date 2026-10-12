<?php
declare(strict_types=1);

/** Değerler (veri temizliği), kullanıcılar, ayarlar ve bakım */
final class PanelDataController
{
    public static function values(): void
    {
        Auth::require('editor');
        $cats = Catalog::categories();
        $pid = (int) (Request::post('ozellik') ?: Request::get('ozellik'));
        $prop = $pid ? Db::one('SELECT * FROM props WHERE id = ?', [$pid]) : null;
        $catId = $prop ? (int) $prop['category_id'] : (int) Request::get('kategori');
        if (!isset($cats[$catId])) {
            $catId = (int) array_key_first($cats);
        }
        $props = $catId ? Db::all("SELECT p.*, g.name AS gname FROM props p JOIN prop_groups g ON g.id = p.group_id
            WHERE p.category_id = ? AND p.item_id IS NULL AND p.archived = 0 AND p.type != 'bool' ORDER BY g.sort, p.sort", [$catId]) : [];
        if (!$prop || (int) $prop['category_id'] !== $catId) {
            $prop = null;
            foreach ($props as $p) {
                if (in_array($p['type'], ['single', 'multi'], true)) {
                    $prop = $p;
                    break;
                }
            }
            $prop ??= $props[0] ?? null;
        }
        $prop = $prop ? Catalog::castProp($prop) : null;

        if (Request::isPost() && $prop) {
            try {
                if (($k = Request::post('adlandir')) !== '') {
                    $names = is_array($_POST['ad'] ?? null) ? $_POST['ad'] : [];
                    flash(Values::rename($prop, $k, (string) ($names[$k] ?? '')));
                } elseif (Request::post('birlestir') !== '') {
                    $sel = array_map('strval', is_array($_POST['sec'] ?? null) ? $_POST['sec'] : []);
                    $target = Request::post('hedef');
                    if ($target === '' && $sel) {
                        $target = $sel[0];
                    }
                    if (count(array_unique(array_merge($sel, [$target]))) < 2) {
                        throw new InvalidArgumentException('Birleştirmek için en az iki değer seç.');
                    }
                    flash(Values::merge($prop, $sel, $target));
                } elseif (Request::post('temizle') !== '') {
                    $n = Values::deleteUnused($prop);
                    flash($n ? "$n kullanılmayan değer silindi." : 'Kullanılmayan değer yok.');
                } elseif (($a = Request::post('benzer')) !== '') {
                    // "birleştir" bağlantısı: a → b
                    [$from, $to] = array_pad(explode('>', $a, 2), 2, '');
                    flash(Values::merge($prop, [$from], $to));
                }
            } catch (InvalidArgumentException $e) {
                flash($e->getMessage(), 'bad');
            }
            Response::redirect(url('panel/degerler', ['ozellik' => $prop['id'], 'gorunum' => Request::get('gorunum') ?: null]));
        }

        $rows = $prop ? Values::list($prop) : [];
        $view = Request::get('gorunum');
        $tabs = [
            '' => ['Tümü', count($rows)],
            'benzer' => ['Benzerleri olanlar', count(array_filter($rows, static fn($r) => $r['similar']))],
            'kullanilmayan' => ['Kullanılmayanlar', count(array_filter($rows, static fn($r) => $r['n'] === 0))],
        ];
        $q = Text::fold(Request::get('q'));
        $shown = array_values(array_filter($rows, static function ($r) use ($view, $q) {
            if ($view === 'benzer' && !$r['similar']) {
                return false;
            }
            if ($view === 'kullanilmayan' && $r['n'] !== 0) {
                return false;
            }
            return $q === '' || str_contains(Text::fold((string) $r['name']), $q);
        }));
        View::send('panel/degerler', [
            'title' => 'Değerler', 'nav' => 'degerler', 'crumbs' => '<b>Değerler</b>',
            'cats' => $cats, 'catId' => $catId, 'props' => $props, 'prop' => $prop, 'rows' => $shown, 'tabs' => $tabs, 'view' => $view,
            'q' => Request::get('q'), 'max' => $rows ? max(array_column($rows, 'n')) : 1,
        ], 'panel');
    }

    public static function users(): void
    {
        $me = Auth::require('admin');
        $resetLink = null;
        if (Request::isPost()) {
            try {
                if (($uid = (int) Request::post('rol_kaydet')) > 0) {
                    $roles = is_array($_POST['rol'] ?? null) ? $_POST['rol'] : [];
                    $role = (string) ($roles[$uid] ?? '');
                    if ($uid === (int) $me['id']) {
                        throw new InvalidArgumentException('Kendi rolünü değiştiremezsin.');
                    }
                    if (!isset(Auth::ROLES[$role])) {
                        throw new InvalidArgumentException('Geçersiz rol.');
                    }
                    Db::update('users', ['role' => $role], 'id = ?', [$uid]);
                    flash('Rol güncellendi.');
                } elseif (($uid = (int) Request::post('engelle')) > 0) {
                    if ($uid === (int) $me['id']) {
                        throw new InvalidArgumentException('Kendini engelleyemezsin.');
                    }
                    Db::exec('UPDATE users SET blocked = 1 - blocked WHERE id = ?', [$uid]);
                    Db::exec("DELETE FROM tokens WHERE user_id = ? AND kind = 'remember'", [$uid]);
                    flash((int) Db::val('SELECT blocked FROM users WHERE id = ?', [$uid]) ? 'Kullanıcı engellendi.' : 'Engel kaldırıldı.');
                } elseif (($uid = (int) Request::post('sifirla')) > 0) {
                    $u = Db::one('SELECT * FROM users WHERE id = ?', [$uid]) ?? throw new InvalidArgumentException('Kullanıcı bulunamadı.');
                    $resetLink = [$u['username'], Auth::resetLink($uid)];
                } elseif (Request::post('ekle') !== '') {
                    $name = Request::post('kullanici');
                    $email = Request::post('eposta');
                    $pass = (string) ($_POST['sifre'] ?? '');
                    $role = Request::post('yeni_rol', 'user');
                    if (!Auth::validUsername($name)) {
                        throw new InvalidArgumentException('Kullanıcı adı 3–32 karakter olmalı.');
                    }
                    if (Db::val('SELECT 1 FROM users WHERE username = ?', [$name]) || ($email !== '' && Db::val('SELECT 1 FROM users WHERE email = ?', [$email]))) {
                        throw new InvalidArgumentException('Bu kullanıcı adı ya da e-posta kullanılıyor.');
                    }
                    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new InvalidArgumentException('E-posta adresi geçersiz.');
                    }
                    if (mb_strlen($pass) < 8) {
                        throw new InvalidArgumentException('Şifre en az 8 karakter olmalı.');
                    }
                    Auth::create($name, $email ?: null, $pass, isset(Auth::ROLES[$role]) ? $role : 'user');
                    flash('“' . $name . '” eklendi.');
                }
            } catch (InvalidArgumentException $e) {
                flash($e->getMessage(), 'bad');
            }
            if (!$resetLink) {
                Response::redirect(url('panel/kullanicilar', ['q' => Request::get('q') ?: null, 'rol' => Request::get('rol') ?: null]));
            }
        }
        $q = Text::clean(Request::get('q'), 80);
        $role = Request::get('rol');
        $where = '1';
        $p = [];
        if ($q !== '') {
            $where .= " AND (u.username LIKE ? ESCAPE '\\' OR u.email LIKE ? ESCAPE '\\')";
            $p[] = '%' . Text::like($q) . '%';
            $p[] = '%' . Text::like($q) . '%';
        }
        if (isset(Auth::ROLES[$role])) {
            $where .= ' AND u.role = ?';
            $p[] = $role;
        }
        $users = Db::all("SELECT u.*, (SELECT COUNT(*) FROM suggestions s WHERE s.user_id = u.id) AS n,
                (SELECT COUNT(*) FROM suggestions s WHERE s.user_id = u.id AND s.status = 'done') AS d,
                (SELECT COUNT(*) FROM suggestions s WHERE s.user_id = u.id AND s.status != 'pending') AS r
            FROM users u WHERE $where ORDER BY (u.role = 'admin') DESC, (u.role = 'editor') DESC, u.last_login IS NULL, u.last_login DESC, u.id LIMIT 300", $p);
        View::send('panel/kullanicilar', [
            'title' => 'Kullanıcılar', 'nav' => 'kullanicilar', 'crumbs' => '<b>Kullanıcılar</b>',
            'users' => $users, 'q' => $q, 'role' => $role, 'me' => $me, 'resetLink' => $resetLink,
            'total' => (int) Db::val('SELECT COUNT(*) FROM users'),
        ], 'panel');
    }

    public static function settings(): void
    {
        Auth::require('admin');
        if (Request::isPost()) {
            $in = $_POST;
            $bool = static fn(string $k) => !empty($in[$k]) ? '1' : '0';
            $int = static fn(string $k, int $min, int $max, int $d) => (string) max($min, min($max, (int) ($in[$k] ?? $d)));
            $widths = implode(',', array_filter(array_map('intval', preg_split('/[\s,]+/', (string) ($in['gorsel_genislik'] ?? ''))), static fn($w) => $w >= 64 && $w <= 2400));
            [$qw, $qa] = array_pad(array_map('intval', preg_split('#\s*/\s*#', (string) ($in['kalite'] ?? '80/55'))), 2, 55);
            Settings::set([
                'site_adi' => Text::clean((string) ($in['site_adi'] ?? ''), 60) ?: 'künye',
                'arama_metni' => Text::clean((string) ($in['arama_metni'] ?? ''), 80),
                'aciklama' => Text::clean((string) ($in['aciklama'] ?? ''), 300),
                'sayfa_basina' => $int('sayfa_basina', 6, 100, 24),
                'ust_baglantilar' => Text::clean((string) ($in['ust_baglantilar'] ?? ''), 500),
                'acik_kayit' => $bool('acik_kayit'),
                'yeni_oge_onerisi' => $bool('yeni_oge_onerisi'),
                'kaynak_zorunlu' => $bool('kaynak_zorunlu'),
                'gunluk_oneri' => $int('gunluk_oneri', 1, 1000, 20),
                'gorsel_genislik' => $widths ?: '320,640,800',
                'gorsel_avif' => $bool('gorsel_avif'),
                'kalite_webp' => (string) max(30, min(100, $qw ?: 80)),
                'kalite_avif' => (string) max(20, min(100, $qa ?: 55)),
                'gorsel_mb' => $int('gorsel_mb', 1, 200, 20),
            ]);
            Settings::bump();
            flash('Ayarlar kaydedildi.');
            Response::redirect(url('panel/ayarlar'));
        }
        [$files, $bytes] = Cache::stats();
        View::send('panel/ayarlar', [
            'title' => 'Ayarlar', 'nav' => 'ayarlar', 'crumbs' => '<b>Ayarlar</b>',
            'actions' => '<button class="btn btn--sm" form="ayarlar" type="submit">Kaydet</button>',
            's' => Settings::all(), 'cacheFiles' => $files, 'cacheBytes' => $bytes,
            'dbBytes' => (int) @filesize(Db::path()) + (int) @filesize(Db::path() . '-wal'),
        ], 'panel');
    }

    public static function maintenance(string $task): void
    {
        Auth::require('admin');
        switch ($task) {
            case 'onbellek':
                [$n, $b] = Cache::clear();
                Facets::forget();
                Settings::bump();
                flash("Önbellek temizlendi: $n dosya, " . number_format($b / 1048576, 1, ',', '.') . ' MB.');
                break;
            case 'dizin':
                @set_time_limit(300);
                foreach (Db::col('SELECT id FROM items') as $iid) {
                    Catalog::refreshSearch((int) $iid);
                }
                foreach (Catalog::categories() as $c) {
                    Db::update('categories', ['search' => Text::fold($c['name'] . ' ' . $c['singular'] . ' ' . ($c['section'] ?? ''))], 'id = ?', [$c['id']]);
                    Catalog::touch((int) $c['id']);
                }
                foreach (Db::all('SELECT id, name FROM options') as $o) {
                    Db::update('options', ['norm' => Text::fold($o['name'])], 'id = ?', [$o['id']]);
                }
                Db::pdo()->exec('ANALYZE');
                Settings::set(['dizin_zamani' => (string) time()]);
                flash('Arama dizini ve filtre dizinleri yeniden oluşturuldu.');
                break;
            default:
                throw new HttpError(404);
        }
        Response::redirect(url('panel/ayarlar'));
    }

    /** Tutarlı bir kopya üretip indirir (VACUUM INTO) */
    public static function backup(): void
    {
        Auth::require('admin');
        $tmp = KUNYE_DATA . '/yedek-' . bin2hex(random_bytes(6)) . '.sqlite';
        try {
            Db::q('VACUUM INTO ?', [$tmp]);
        } catch (Throwable) {
            Db::pdo()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            copy(Db::path(), $tmp);
        }
        header('Content-Type: application/vnd.sqlite3');
        header('Content-Disposition: attachment; filename="kunye-' . date('Y-m-d') . '.sqlite"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: no-store');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
}
