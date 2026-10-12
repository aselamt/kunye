<?php
declare(strict_types=1);

final class PanelItemsController
{
    public static function index(): void
    {
        Auth::require('editor');
        $q = Text::clean(Request::get('q'), 100);
        $catId = (int) Request::get('kategori');
        $status = Request::get('durum');
        $where = ['1'];
        $p = [];
        foreach (Text::terms($q) as $t) {
            $where[] = "i.search LIKE ? ESCAPE '\\'";
            $p[] = '%' . Text::like($t) . '%';
        }
        if ($catId) {
            $where[] = 'i.category_id = ?';
            $p[] = $catId;
        }
        if (in_array($status, ['published', 'draft'], true)) {
            $where[] = 'i.status = ?';
            $p[] = $status;
        }
        $w = implode(' AND ', $where);
        $total = (int) Db::val("SELECT COUNT(*) FROM items i WHERE $w", $p);
        $per = 30;
        $pages = max(1, (int) ceil($total / $per));
        $page = max(1, min($pages, (int) Request::get('sayfa', '1')));
        $ids = Db::col("SELECT i.id FROM items i WHERE $w ORDER BY i.updated_at DESC, i.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
        $cards = Catalog::cards($ids);
        $editors = Db::pairs('SELECT id, username FROM users WHERE id IN (SELECT DISTINCT updated_by FROM items WHERE updated_by IS NOT NULL)');
        View::send('panel/ogeler', [
            'title' => 'Öğeler', 'nav' => 'ogeler', 'wide' => true,
            'crumbs' => '<b>Öğeler</b>',
            'actions' => '<a class="btn btn--sm" href="' . e(url('panel/oge/yeni', ['kategori' => $catId ?: null])) . '">' . icon('plus', 'icon icon--sm') . 'Yeni öğe</a>',
            'cards' => $cards, 'total' => $total, 'page' => $page, 'pages' => $pages, 'q' => $q, 'catId' => $catId, 'status' => $status,
            'editors' => $editors,
        ], 'panel');
    }

    public static function create(): void
    {
        $u = Auth::require('editor');
        $cats = Catalog::categories();
        $errors = [];
        if (Request::isPost()) {
            $cat = $cats[(int) Request::post('kategori')] ?? null;
            $name = Text::clean(Request::post('ad'), 120);
            if (!$cat) {
                $errors[] = 'Bir kategori seç.';
            }
            if ($name === '') {
                $errors[] = 'Öğenin adını yaz.';
            }
            if (!$errors) {
                $now = time();
                $id = Db::insert('items', [
                    'category_id' => $cat['id'], 'name' => $name, 'slug' => Catalog::uniqueItemSlug((int) $cat['id'], $name), 'status' => 'draft',
                    'created_by' => $u['id'], 'updated_by' => $u['id'], 'created_at' => $now, 'updated_at' => $now,
                ]);
                Catalog::refreshSearch($id);
                Catalog::log($id, 'olustur', 'Öğe oluşturuldu');
                Catalog::touch((int) $cat['id']);
                flash('Öğe taslak olarak oluşturuldu. Özelliklerini doldurup yayınlayabilirsin.');
                Response::redirect(url('panel/oge/' . $id));
            }
        }
        View::send('panel/oge-yeni', [
            'title' => 'Yeni öğe', 'nav' => 'ogeler',
            'crumbs' => '<a href="' . e(url('panel/ogeler')) . '">Öğeler</a><span class="sep">/</span><b>Yeni öğe</b>',
            'cats' => $cats, 'errors' => $errors, 'catId' => (int) (Request::post('kategori') ?: Request::get('kategori')),
        ], 'panel');
    }

    private static function load(string $id): array
    {
        $item = Catalog::item((int) $id) ?? throw new HttpError(404);
        $cat = Catalog::category((int) $item['category_id']) ?? throw new HttpError(404);
        return [$item, $cat];
    }

    public static function edit(string $id): void
    {
        $u = Auth::require('editor');
        [$item, $cat] = self::load($id);
        $iid = (int) $item['id'];
        $errors = [];
        if (Request::isPost()) {
            $errors = self::save($item, $cat, $u);
            if (!$errors) {
                Response::redirect(url('panel/oge/' . $iid) . (Request::post('_ankor') !== '' ? '#' . preg_replace('/[^a-z0-9-]/', '', Request::post('_ankor')) : ''));
            }
            $item = Catalog::item($iid);
        }
        $tpl = Catalog::template((int) $cat['id'], $iid);
        $hidden = Catalog::hidden($iid);
        $values = Catalog::values($iid, $tpl['props']);
        if ($errors && is_array($_POST['v'] ?? null)) {
            foreach ($_POST['v'] as $pid => $raw) {
                if (isset($tpl['props'][(int) $pid])) {
                    $values[(int) $pid] = Catalog::normalize($tpl['props'][(int) $pid], $raw) ?? $values[(int) $pid] ?? null;
                }
            }
        }
        $last = Db::one('SELECT h.*, u.username FROM history h LEFT JOIN users u ON u.id = h.user_id WHERE h.item_id = ? ORDER BY h.id DESC LIMIT 1', [$iid]);
        View::send('panel/oge', [
            'title' => $item['name'], 'nav' => 'ogeler',
            'crumbs' => '<a href="' . e(url('panel/ogeler')) . '">Öğeler</a><span class="sep">/</span><b>' . e($item['name']) . '</b>',
            'actions' => '<span class="status' . ($item['status'] === 'published' ? '' : ' status--muted') . '">' . ($item['status'] === 'published' ? 'Yayında' : 'Taslak') . '</span>'
                . '<a class="btn btn--ghost btn--sm" href="' . e(Catalog::itemUrl($item)) . '">' . icon('eye', 'icon icon--sm') . 'Görüntüle</a>'
                . '<button class="btn btn--sm" form="oge" type="submit">Kaydet</button>',
            'item' => $item, 'cat' => $cat, 'tpl' => $tpl, 'hidden' => $hidden, 'values' => $values, 'errors' => $errors,
            'images' => Catalog::images($iid), 'last' => $last,
        ], 'panel');
    }

    /** Öğe formunu kaydeder; hata listesi döner */
    private static function save(array $item, array $cat, array $u): array
    {
        $iid = (int) $item['id'];
        $catId = (int) $cat['id'];
        $errors = [];
        $notes = [];
        $name = Text::clean(Request::post('ad', $item['name']), 120);
        $slug = Text::slug(Request::post('adres', $item['slug'])) ?: Text::slug($name);
        $status = Request::post('durum', $item['status']) === 'published' ? 'published' : 'draft';
        if ($name === '') {
            $errors[] = 'Ad boş olamaz.';
        }
        if ($slug === 'oner' || Db::val('SELECT 1 FROM items WHERE category_id = ? AND slug = ? AND id != ?', [$catId, $slug, $iid])) {
            $errors[] = 'Bu adres kategoride başka bir öğede kullanılıyor.';
        }
        $tpl = Catalog::template($catId, $iid);
        $hidden = Catalog::hidden($iid);
        $posted = is_array($_POST['v'] ?? null) ? $_POST['v'] : [];
        $values = [];
        foreach ($tpl['props'] as $pid => $p) {
            if (isset($hidden[$pid]) || !array_key_exists($pid, $posted)) {
                continue;
            }
            $v = Catalog::normalize($p, $posted[$pid]);
            if ($p['type'] === 'number' && is_string($posted[$pid]) && trim($posted[$pid]) !== '' && $v === null) {
                $errors[] = '“' . $p['name'] . '” için geçerli bir sayı yaz.';
                continue;
            }
            $values[$pid] = $v;
        }
        if ($status === 'published') {
            foreach ($tpl['props'] as $pid => $p) {
                $v = array_key_exists($pid, $values) ? $values[$pid] : null;
                if ($p['required'] && !isset($hidden[$pid]) && Catalog::isEmpty($v)) {
                    $errors[] = '“' . $p['name'] . '” zorunlu; yayınlamadan önce doldur.';
                }
            }
        }
        if ($errors) {
            return $errors;
        }
        Db::tx(static function () use ($item, $iid, $catId, $name, $slug, $status, $tpl, $values, $u, &$notes) {
            $changed = Catalog::saveValues($iid, $tpl['props'], $values);
            if ($name !== $item['name']) {
                $changed[] = 'Ad';
            }
            if ($status !== $item['status']) {
                $changed[] = $status === 'published' ? 'Yayınlandı' : 'Taslağa alındı';
            }
            Db::update('items', ['name' => $name, 'slug' => $slug, 'status' => $status, 'updated_by' => $u['id'], 'updated_at' => time()], 'id = ?', [$iid]);

            // şablon özelliğini bu öğede gizle / geri getir, öğeye özel özelliği sil
            $hide = (int) Request::post('gizle');
            if ($hide && isset($tpl['props'][$hide]) && $tpl['props'][$hide]['item_id'] === null) {
                Db::exec('INSERT OR IGNORE INTO hidden (item_id, prop_id) VALUES (?, ?)', [$iid, $hide]);
                $changed[] = $tpl['props'][$hide]['name'] . ' gizlendi';
            }
            $show = (int) Request::post('goster');
            if ($show) {
                Db::exec('DELETE FROM hidden WHERE item_id = ? AND prop_id = ?', [$iid, $show]);
            }
            $drop = (int) Request::post('sil_ozel');
            if ($drop && isset($tpl['props'][$drop]) && $tpl['props'][$drop]['item_id'] === $iid) {
                Db::exec('DELETE FROM props WHERE id = ?', [$drop]);
                $changed[] = $tpl['props'][$drop]['name'] . ' silindi';
            }
            // öğeye özel yeni grup ve özellikler
            $ng = is_array($_POST['yeni_grup'] ?? null) ? $_POST['yeni_grup'] : [];
            $ngName = Text::clean((string) ($ng['ad'] ?? ''), 60);
            if ($ngName !== '') {
                $gid = Db::insert('prop_groups', ['category_id' => $catId, 'item_id' => $iid, 'name' => $ngName, 'icon' => Templates::icon((string) ($ng['ikon'] ?? 'layers')), 'sort' => 900]);
                $_POST['yeni'][$gid] = ['ad' => $ng['ozellik'] ?? '', 'tip' => $ng['tip'] ?? 'text', 'birim' => $ng['birim'] ?? '', 'deger' => $ng['deger'] ?? ''];
                $tpl['groups'][$gid] = ['id' => $gid, 'item_id' => $iid];
                $changed[] = $ngName . ' grubu eklendi';
            }
            foreach (is_array($_POST['yeni'] ?? null) ? $_POST['yeni'] : [] as $gid => $np) {
                $gid = (int) $gid;
                $pname = Text::clean((string) ($np['ad'] ?? ''), 80);
                if ($pname === '' || !isset($tpl['groups'][$gid])) {
                    continue;
                }
                $type = isset(Catalog::TYPES[$np['tip'] ?? '']) ? $np['tip'] : 'text';
                $pid = Db::insert('props', [
                    'category_id' => $catId, 'group_id' => $gid, 'item_id' => $iid, 'name' => $pname, 'slug' => Catalog::uniquePropSlug($catId, $pname),
                    'type' => $type, 'unit' => Text::clean((string) ($np['birim'] ?? ''), 20), 'filterable' => 0, 'sort' => 900,
                ]);
                $prop = Catalog::castProp(Db::one('SELECT * FROM props WHERE id = ?', [$pid]));
                $val = Catalog::normalize($prop, $np['deger'] ?? '');
                if ($val !== null) {
                    Catalog::setValue($iid, $prop, $val);
                }
                $changed[] = $pname . ' (öğeye özel) eklendi';
            }
            if ($changed) {
                Catalog::log($iid, 'duzenle', implode(', ', $changed));
            }
        });
        // görseller (JS'siz yükleme)
        foreach (self::files('gorseller') as $f) {
            try {
                $row = Images::fromUpload($f, $iid);
                $row['sort'] = (int) Db::val('SELECT COALESCE(MAX(sort), -1) + 1 FROM images WHERE item_id = ?', [$iid]);
                Db::insert('images', $row);
            } catch (RuntimeException $e) {
                $notes[] = ($f['name'] ?? 'Görsel') . ': ' . $e->getMessage();
            }
        }
        Catalog::refreshSearch($iid);
        Catalog::touch($catId);
        flash($notes ? "Kaydedildi, ancak:\n" . implode("\n", $notes) : 'Kaydedildi.', $notes ? 'bad' : 'ok');
        return [];
    }

    /** $_FILES['ad'][] dizisini satırlara çevirir */
    private static function files(string $key): array
    {
        $f = $_FILES[$key] ?? null;
        if (!$f || !is_array($f['name'] ?? null)) {
            return $f && ($f['error'] ?? 4) !== UPLOAD_ERR_NO_FILE ? [$f] : [];
        }
        $out = [];
        foreach ($f['name'] as $i => $n) {
            if (($f['error'][$i] ?? 4) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        }
        return $out;
    }

    public static function upload(string $id): void
    {
        Auth::require('editor');
        [$item, $cat] = self::load($id);
        $iid = (int) $item['id'];
        $made = [];
        $errors = [];
        foreach (array_merge(self::files('gorsel'), self::files('gorseller')) as $f) {
            try {
                $row = Images::fromUpload($f, $iid);
                $row['sort'] = (int) Db::val('SELECT COALESCE(MAX(sort), -1) + 1 FROM images WHERE item_id = ?', [$iid]);
                $row['id'] = Db::insert('images', $row);
                $made[] = $row;
            } catch (RuntimeException $e) {
                $errors[] = ($f['name'] ?? 'Görsel') . ': ' . $e->getMessage();
            }
        }
        if ($made) {
            Catalog::log($iid, 'gorsel', count($made) . ' görsel eklendi');
            Catalog::touch((int) $cat['id']);
        }
        if (Request::wantsJson()) {
            Response::json([
                'gorseller' => array_map(static fn($r) => ['html' => View::partial('panel/imgcard', ['img' => $r, 'first' => false])], $made),
                'hatalar' => $errors,
            ], $made || !$errors ? 200 : 422);
        }
        flash($errors ? implode("\n", $errors) : 'Görsel eklendi.', $errors ? 'bad' : 'ok');
        Response::redirect(url('panel/oge/' . $iid) . '#gorseller');
    }

    public static function reorder(string $id): void
    {
        Auth::require('editor');
        [$item, $cat] = self::load($id);
        $order = array_map('intval', explode(',', Request::post('sira')));
        foreach ($order as $i => $imgId) {
            Db::exec('UPDATE images SET sort = ? WHERE id = ? AND item_id = ?', [$i, $imgId, $item['id']]);
        }
        Catalog::touch((int) $cat['id']);
        Response::json(['tamam' => true]);
    }

    private static function image(string $id): array
    {
        $img = Db::one('SELECT * FROM images WHERE id = ?', [(int) $id]) ?? throw new HttpError(404);
        $item = Catalog::item((int) $img['item_id']) ?? throw new HttpError(404);
        return [$img, $item];
    }

    public static function deleteImage(string $id): void
    {
        Auth::require('editor');
        [$img, $item] = self::image($id);
        Images::delete($img);
        Catalog::log((int) $item['id'], 'gorsel', 'Görsel silindi');
        Catalog::touch((int) $item['category_id']);
        if (Request::wantsJson()) {
            Response::json(['tamam' => true]);
        }
        flash('Görsel silindi.');
        Response::redirect(url('panel/oge/' . $item['id']) . '#gorseller');
    }

    public static function coverImage(string $id): void
    {
        Auth::require('editor');
        [$img, $item] = self::image($id);
        Db::exec('UPDATE images SET sort = sort + 1 WHERE item_id = ?', [$item['id']]);
        Db::exec('UPDATE images SET sort = 0 WHERE id = ?', [$img['id']]);
        Catalog::touch((int) $item['category_id']);
        if (Request::wantsJson()) {
            Response::json(['tamam' => true]);
        }
        flash('Kapak görseli değişti.');
        Response::redirect(url('panel/oge/' . $item['id']) . '#gorseller');
    }

    public static function delete(string $id): void
    {
        Auth::require('editor');
        [$item] = self::load($id);
        Catalog::log((int) $item['id'], 'sil', $item['name'] . ' silindi');
        Catalog::deleteItem((int) $item['id']);
        flash('“' . $item['name'] . '” silindi.');
        Response::redirect(url('panel/ogeler'));
    }

    public static function history(string $id): void
    {
        Auth::require('editor');
        [$item] = self::load($id);
        $rows = Db::all('SELECT h.*, u.username FROM history h LEFT JOIN users u ON u.id = h.user_id WHERE h.item_id = ? ORDER BY h.id DESC LIMIT 300', [$item['id']]);
        View::send('panel/gecmis', [
            'title' => $item['name'] . ' · Geçmiş', 'nav' => 'ogeler',
            'crumbs' => '<a href="' . e(url('panel/ogeler')) . '">Öğeler</a><span class="sep">/</span><a href="' . e(url('panel/oge/' . $item['id'])) . '">' . e($item['name']) . '</a><span class="sep">/</span><b>Geçmiş</b>',
            'rows' => $rows, 'item' => $item,
        ], 'panel');
    }
}
