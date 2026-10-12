<?php
declare(strict_types=1);

final class PanelCategoriesController
{
    public static function index(): void
    {
        Auth::require('admin');
        $counts = Db::pairs('SELECT category_id, COUNT(*) FROM props WHERE item_id IS NULL AND archived = 0 GROUP BY category_id');
        $all = Db::pairs('SELECT category_id, COUNT(*) FROM items GROUP BY category_id');
        View::send('panel/kategoriler', [
            'title' => 'Kategoriler', 'nav' => 'kategoriler', 'crumbs' => '<b>Kategoriler</b>',
            'actions' => '<a class="btn btn--sm" href="' . e(url('panel/kategori/yeni')) . '">' . icon('plus', 'icon icon--sm') . 'Yeni kategori</a>',
            'cats' => Catalog::categories(), 'counts' => $counts, 'all' => $all,
        ], 'panel');
    }

    public static function create(): void
    {
        Auth::require('admin');
        $errors = [];
        if (Request::isPost()) {
            try {
                $id = Templates::saveCategory(null, $_POST);
                flash('Kategori oluşturuldu. Şimdi özellik gruplarını ve özellikleri ekle.');
                Response::redirect(url('panel/kategori/' . $id));
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }
        View::send('panel/kategori-yeni', [
            'title' => 'Yeni kategori', 'nav' => 'kategoriler',
            'crumbs' => '<a href="' . e(url('panel/kategoriler')) . '">Kategoriler</a><span class="sep">/</span><b>Yeni</b>',
            'errors' => $errors, 'sections' => Catalog::sections(),
        ], 'panel');
    }

    public static function edit(string $id): void
    {
        Auth::require('admin');
        $cat = Catalog::category((int) $id) ?? throw new HttpError(404);
        $errors = [];
        if (Request::isPost()) {
            try {
                Templates::saveCategory((int) $cat['id'], is_array($_POST['kategori'] ?? null) ? $_POST['kategori'] : []);
                $notes = Templates::save(
                    (int) $cat['id'],
                    is_array($_POST['grup'] ?? null) ? $_POST['grup'] : [],
                    is_array($_POST['yeni_grup'] ?? null) ? $_POST['yeni_grup'] : [],
                    is_array($_POST['oz'] ?? null) ? $_POST['oz'] : [],
                    is_array($_POST['yeni_oz'] ?? null) ? $_POST['yeni_oz'] : [],
                );
                flash($notes ? "Kaydedildi, ancak:\n" . implode("\n", $notes) : 'Şablon kaydedildi.', $notes ? 'bad' : 'ok');
                Response::redirect(url('panel/kategori/' . $cat['id']) . (Request::post('_ankor') !== '' ? '#' . preg_replace('/[^a-z0-9-]/', '', Request::post('_ankor')) : ''));
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }
        $cat = Catalog::category((int) $id);
        $tpl = Catalog::template((int) $cat['id'], null, true);
        // değer önizlemesi: seçenekler ve kullanım sayıları
        $opts = [];
        foreach (Db::all('SELECT o.id, o.prop_id, o.name, COUNT(v.item_id) AS n FROM options o JOIN props p ON p.id = o.prop_id
                LEFT JOIN vals v ON v.option_id = o.id WHERE p.category_id = ? GROUP BY o.id ORDER BY n DESC', [$cat['id']]) as $o) {
            $opts[(int) $o['prop_id']][] = $o;
        }
        $filled = Db::pairs('SELECT prop_id, COUNT(DISTINCT item_id) FROM vals WHERE prop_id IN (SELECT id FROM props WHERE category_id = ?) GROUP BY prop_id', [$cat['id']]);
        View::send('panel/kategori', [
            'title' => $cat['name'] . ' · Şablon', 'nav' => 'kategoriler',
            'crumbs' => '<a href="' . e(url('panel/kategoriler')) . '">Kategoriler</a><span class="sep">/</span><b>' . e($cat['name']) . '</b>',
            'actions' => '<a class="btn btn--ghost btn--sm" href="' . e(url($cat['slug'])) . '">' . icon('eye', 'icon icon--sm') . 'Görüntüle</a><button class="btn btn--sm" form="sablon" type="submit">Kaydet</button>',
            'cat' => $cat, 'tpl' => $tpl, 'opts' => $opts, 'filled' => $filled, 'errors' => $errors, 'sections' => Catalog::sections(),
            'itemCount' => (int) Db::val('SELECT COUNT(*) FROM items WHERE category_id = ?', [$cat['id']]),
        ], 'panel');
    }

    public static function delete(string $id): void
    {
        Auth::require('admin');
        $cat = Catalog::category((int) $id) ?? throw new HttpError(404);
        try {
            Templates::deleteCategory((int) $cat['id']);
            flash('“' . $cat['name'] . '” silindi.');
            Response::redirect(url('panel/kategoriler'));
        } catch (InvalidArgumentException $e) {
            flash($e->getMessage(), 'bad');
            Response::redirect(url('panel/kategori/' . $cat['id']));
        }
    }
}
