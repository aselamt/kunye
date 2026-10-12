<?php
declare(strict_types=1);

/** Kategoriler, şablonlar (grup + özellik), öğeler ve değerler */
final class Catalog
{
    public const TYPES = ['number' => 'Sayı', 'single' => 'Tek seçim', 'multi' => 'Çoklu seçim', 'bool' => 'Var / Yok', 'text' => 'Metin'];
    public const TYPE_HINT = ['number' => 'sayı', 'single' => 'tek seçim', 'multi' => 'çoklu seçim', 'bool' => 'var / yok', 'text' => 'metin'];
    public const RESERVED = ['ara', 'api', 'giris', 'kayit', 'cikis', 'sifre', 'favoriler', 'favori', 'kategoriler', 'panel',
        'kurulum', 'assets', 'uploads', 'robots-txt', 'sitemap-xml', 'index-php', 'data', 'app', 'tests', 'tools', 'design', 'hakkinda'];

    private static ?array $cats = null;

    // ------------------------------------------------------------------ kategoriler

    public static function categories(): array
    {
        if (self::$cats === null) {
            self::$cats = [];
            $rows = Db::all('SELECT c.*, s.name AS section, s.sort AS ssort FROM categories c LEFT JOIN sections s ON s.id = c.section_id
                ORDER BY COALESCE(s.sort, 999), s.name, c.sort, c.name');
            foreach ($rows as $r) {
                self::$cats[(int) $r['id']] = $r;
            }
        }
        return self::$cats;
    }

    public static function forget(): void
    {
        self::$cats = null;
    }

    public static function category(int $id): ?array
    {
        return self::categories()[$id] ?? null;
    }

    public static function categoryBySlug(string $slug): ?array
    {
        foreach (self::categories() as $c) {
            if ($c['slug'] === $slug) {
                return $c;
            }
        }
        return null;
    }

    public static function sections(): array
    {
        return Db::all('SELECT * FROM sections ORDER BY sort, name');
    }

    public static function validCategorySlug(string $slug): bool
    {
        return $slug !== '' && !in_array($slug, self::RESERVED, true) && preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug);
    }

    // ------------------------------------------------------------------ şablon

    /**
     * Kategorinin şablonu; $itemId verilirse o öğeye özel grup ve özellikler de eklenir.
     * Dönen: ['groups' => [gid => grup + ['props' => [pid => özellik]]], 'props' => [pid => özellik]]
     */
    public static function template(int $catId, ?int $itemId = null, bool $archived = false): array
    {
        $groups = Db::all('SELECT * FROM prop_groups WHERE category_id = ? AND (item_id IS NULL OR item_id = ?) ORDER BY sort, id', [$catId, $itemId ?? 0]);
        $props = Db::all('SELECT * FROM props WHERE category_id = ? AND (item_id IS NULL OR item_id = ?)' . ($archived ? '' : ' AND archived = 0')
            . ' ORDER BY sort, id', [$catId, $itemId ?? 0]);
        $out = ['groups' => [], 'props' => []];
        foreach ($groups as $g) {
            $g['props'] = [];
            $out['groups'][(int) $g['id']] = $g;
        }
        foreach ($props as $p) {
            $p = self::castProp($p);
            if (!isset($out['groups'][$p['group_id']])) {
                continue;
            }
            $out['groups'][$p['group_id']]['props'][$p['id']] = $p;
            $out['props'][$p['id']] = $p;
        }
        return $out;
    }

    public static function castProp(array $p): array
    {
        foreach (['id', 'category_id', 'group_id', 'sort', 'filterable', 'clickable', 'in_list', 'is_key', 'in_eyebrow', 'required', 'archived'] as $k) {
            $p[$k] = (int) $p[$k];
        }
        $p['item_id'] = $p['item_id'] === null ? null : (int) $p['item_id'];
        $p['label'] = $p['short'] !== '' ? $p['short'] : $p['name'];
        return $p;
    }

    /** Kategorideki tüm (arşivlenmemiş) özellikler, slug anahtarlı — öğeye özel olanlar dahil */
    public static function propsBySlug(int $catId): array
    {
        $out = [];
        foreach (Db::all('SELECT * FROM props WHERE category_id = ? AND archived = 0', [$catId]) as $p) {
            $out[$p['slug']] = self::castProp($p);
        }
        return $out;
    }

    public static function uniquePropSlug(int $catId, string $name, ?int $exceptId = null): string
    {
        $base = Text::slug($name, 60) ?: 'ozellik';
        if (in_array($base, ['sirala', 'sayfa', 'q'], true)) {
            $base .= '-1';
        }
        $slug = $base;
        for ($i = 2; Db::val('SELECT 1 FROM props WHERE category_id = ? AND slug = ? AND id != ?', [$catId, $slug, $exceptId ?? 0]); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    // ------------------------------------------------------------------ öğeler

    public static function item(int $id): ?array
    {
        return Db::one('SELECT * FROM items WHERE id = ?', [$id]);
    }

    public static function itemBySlug(int $catId, string $slug): ?array
    {
        return Db::one('SELECT * FROM items WHERE category_id = ? AND slug = ?', [$catId, $slug]);
    }

    public static function itemUrl(array $item): string
    {
        $c = self::category((int) $item['category_id']);
        return url(($c['slug'] ?? '') . '/' . $item['slug']);
    }

    public static function uniqueItemSlug(int $catId, string $name, ?int $exceptId = null): string
    {
        $base = Text::slug($name) ?: 'oge';
        if ($base === 'oner') {
            $base = 'oner-1'; // {kategori}/oner yeni öğe önerisi sayfasıdır
        }
        $slug = $base;
        for ($i = 2; Db::val('SELECT 1 FROM items WHERE category_id = ? AND slug = ? AND id != ?', [$catId, $slug, $exceptId ?? 0]); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    public static function hidden(int $itemId): array
    {
        return array_fill_keys(array_map('intval', Db::col('SELECT prop_id FROM hidden WHERE item_id = ?', [$itemId])), true);
    }

    /** Ham değer satırları: [pid => [['num'=>, 'bool'=>, 'text'=>, 'oid'=>, 'oname'=>, 'oslug'=>], …]] */
    public static function rows(int $itemId): array
    {
        $out = [];
        $rows = Db::all('SELECT v.prop_id, v.num, v.bool, v.text, v.option_id AS oid, o.name AS oname, o.slug AS oslug
            FROM vals v LEFT JOIN options o ON o.id = v.option_id WHERE v.item_id = ? ORDER BY v.prop_id, v.sort, o.name', [$itemId]);
        foreach ($rows as $r) {
            $out[(int) $r['prop_id']][] = $r;
        }
        return $out;
    }

    /** Değerlerin kanonik biçimi: sayı → float, tek seçim/metin → string, çoklu → string[], var/yok → bool */
    public static function values(int $itemId, array $props): array
    {
        $out = [];
        foreach (self::rows($itemId) as $pid => $rows) {
            if (isset($props[$pid])) {
                $out[$pid] = self::fromRows($props[$pid]['type'], $rows);
            }
        }
        return $out;
    }

    public static function fromRows(string $type, array $rows): mixed
    {
        $r = $rows[0];
        return match ($type) {
            'number' => $r['num'] === null ? null : (float) $r['num'],
            'single' => $r['oname'],
            'multi' => array_values(array_filter(array_column($rows, 'oname'), static fn($v) => $v !== null)),
            'bool' => $r['bool'] === null ? null : (bool) $r['bool'],
            'text' => $r['text'],
        };
    }

    /** Formdan gelen girdiyi kanonik değere çevirir; boşsa null */
    public static function normalize(array $prop, mixed $in): mixed
    {
        switch ($prop['type']) {
            case 'number':
                return is_string($in) ? Text::number($in) : (is_int($in) || is_float($in) ? (float) $in : null);
            case 'single':
                $s = is_string($in) ? Text::clean($in, 120) : '';
                return $s === '' ? null : $s;
            case 'multi':
                $list = is_array($in) ? $in : preg_split('/\r\n|\r|\n|;/', (string) $in);
                $out = [];
                foreach ($list as $v) {
                    $v = is_string($v) ? Text::clean($v, 120) : '';
                    if ($v !== '' && !isset($out[Text::fold($v)])) {
                        $out[Text::fold($v)] = $v;
                    }
                }
                return $out ? array_values(array_slice($out, 0, 60)) : null;
            case 'bool':
                if ($in === true || $in === false) {
                    return $in;
                }
                return match ((string) $in) {
                    '1', 'var', 'evet' => true,
                    '0', 'yok', 'hayir' => false,
                    default => null,
                };
            case 'text':
                $s = is_string($in) ? Text::clean($in, 500) : '';
                return $s === '' ? null : $s;
        }
        return null;
    }

    public static function isEmpty(mixed $v): bool
    {
        return $v === null || $v === [] || $v === '';
    }

    public static function same(array $prop, mixed $a, mixed $b): bool
    {
        if (self::isEmpty($a) || self::isEmpty($b)) {
            return self::isEmpty($a) && self::isEmpty($b);
        }
        return match ($prop['type']) {
            'number' => abs((float) $a - (float) $b) < 1e-9,
            'single' => Text::fold((string) $a) === Text::fold((string) $b),
            'multi' => self::foldSet($a) === self::foldSet($b),
            'bool' => (bool) $a === (bool) $b,
            'text' => trim((string) $a) === trim((string) $b),
        };
    }

    private static function foldSet(array $l): array
    {
        $f = array_map([Text::class, 'fold'], $l);
        sort($f);
        return $f;
    }

    /** Değeri metin olarak göster */
    public static function display(array $prop, mixed $v): string
    {
        if (self::isEmpty($v)) {
            return '';
        }
        $unit = $prop['unit'] !== '' ? ' ' . $prop['unit'] : '';
        return match ($prop['type']) {
            'number' => num((float) $v) . $unit,
            'single' => (string) $v,
            'multi' => implode(', ', $v),
            'bool' => $v ? 'Var' : 'Yok',
            'text' => $v . $unit,
        };
    }

    /** Forma yazılacak biçim */
    public static function inputValue(array $prop, mixed $v): string
    {
        if (self::isEmpty($v)) {
            return '';
        }
        return match ($prop['type']) {
            'number' => num((float) $v, 6),
            'multi' => implode("\n", $v),
            'bool' => $v ? '1' : '0',
            default => (string) $v,
        };
    }

    public static function optionId(int $propId, string $name): int
    {
        $name = Text::clean($name, 120);
        $norm = Text::fold($name);
        $id = Db::val('SELECT id FROM options WHERE prop_id = ? AND norm = ?', [$propId, $norm]);
        if ($id) {
            return (int) $id;
        }
        $base = Text::slug($name, 60) ?: 'deger';
        $slug = $base;
        for ($i = 2; Db::val('SELECT 1 FROM options WHERE prop_id = ? AND slug = ?', [$propId, $slug]); $i++) {
            $slug = $base . '-' . $i;
        }
        return Db::insert('options', ['prop_id' => $propId, 'name' => $name, 'norm' => $norm, 'slug' => $slug]);
    }

    /** Bir özelliğin değerini yazar (null → siler) */
    public static function setValue(int $itemId, array $prop, mixed $v): void
    {
        Db::exec('DELETE FROM vals WHERE item_id = ? AND prop_id = ?', [$itemId, $prop['id']]);
        if (self::isEmpty($v)) {
            return;
        }
        $row = ['item_id' => $itemId, 'prop_id' => $prop['id']];
        switch ($prop['type']) {
            case 'number':
                Db::insert('vals', $row + ['num' => (float) $v]);
                break;
            case 'single':
                Db::insert('vals', $row + ['option_id' => self::optionId($prop['id'], (string) $v)]);
                break;
            case 'multi':
                foreach (array_values($v) as $i => $name) {
                    Db::insert('vals', $row + ['option_id' => self::optionId($prop['id'], (string) $name), 'sort' => $i]);
                }
                break;
            case 'bool':
                Db::insert('vals', $row + ['bool' => $v ? 1 : 0]);
                break;
            case 'text':
                Db::insert('vals', $row + ['text' => (string) $v]);
                break;
        }
    }

    /** Arama metni: ad + kategori + alt başlıkta görünen değerler (ör. marka) */
    public static function refreshSearch(int $itemId): void
    {
        $item = self::item($itemId);
        if (!$item) {
            return;
        }
        $cat = self::category((int) $item['category_id']);
        $extra = Db::col('SELECT o.name FROM vals v JOIN props p ON p.id = v.prop_id JOIN options o ON o.id = v.option_id
            WHERE v.item_id = ? AND p.in_eyebrow = 1', [$itemId]);
        $s = Text::fold($item['name'] . ' ' . implode(' ', $extra) . ' ' . ($cat['singular'] ?? ''));
        Db::update('items', ['search' => $s], 'id = ?', [$itemId]);
    }

    /** Kategori içeriği değişti: dizin sürümü ve öğe sayısı güncellenir, sayfa önbelleği geçersiz olur */
    public static function touch(int $catId): void
    {
        Db::exec("UPDATE categories SET version = version + 1,
            item_count = (SELECT COUNT(*) FROM items WHERE category_id = categories.id AND status = 'published') WHERE id = ?", [$catId]);
        Settings::bump();
        self::$cats = null;
    }

    public static function log(?int $itemId, string $action, string $detail = ''): void
    {
        Db::insert('history', ['item_id' => $itemId, 'user_id' => Auth::id(), 'action' => $action, 'detail' => mb_substr($detail, 0, 4000), 'created_at' => time()]);
    }

    /**
     * Öğeyi kaydeder. $values: [pid => kanonik değer]. Değişen özelliklerin adlarını döner.
     */
    public static function saveValues(int $itemId, array $props, array $values): array
    {
        $current = self::values($itemId, $props);
        $changed = [];
        foreach ($values as $pid => $v) {
            if (!isset($props[$pid])) {
                continue;
            }
            if (!self::same($props[$pid], $current[$pid] ?? null, $v)) {
                self::setValue($itemId, $props[$pid], $v);
                $changed[] = $props[$pid]['name'];
            }
        }
        return $changed;
    }

    /** Liste satırları/kartlar için öğe bilgileri: kapak, alt başlık, listede gösterilen özellikler */
    public static function cards(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $ids = array_values(array_map('intval', $ids));
        $items = [];
        foreach (Db::all('SELECT * FROM items WHERE id IN (' . Db::in($ids) . ')', $ids) as $r) {
            $r['cover'] = null;
            $r['sub'] = [];
            $r['specs'] = [];
            $items[(int) $r['id']] = $r;
        }
        foreach (Db::all('SELECT * FROM images WHERE item_id IN (' . Db::in($ids) . ') ORDER BY sort, id', $ids) as $img) {
            $iid = (int) $img['item_id'];
            if ($items[$iid]['cover'] === null || (int) $img['id'] === (int) $items[$iid]['cover_id']) {
                $items[$iid]['cover'] = $img;
            }
        }
        $rows = Db::all('SELECT v.item_id, v.prop_id, v.num, v.bool, v.text, o.name AS oname, p.type, p.unit, p.name, p.short,
                p.in_list, p.in_eyebrow, p.sort AS psort, g.sort AS gsort
            FROM vals v JOIN props p ON p.id = v.prop_id JOIN prop_groups g ON g.id = p.group_id
            LEFT JOIN options o ON o.id = v.option_id
            WHERE v.item_id IN (' . Db::in($ids) . ') AND (p.in_list = 1 OR p.in_eyebrow = 1) AND p.archived = 0
              AND NOT EXISTS (SELECT 1 FROM hidden h WHERE h.item_id = v.item_id AND h.prop_id = v.prop_id)
            ORDER BY v.item_id, g.sort, p.sort, p.id, v.sort', $ids);
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[(int) $r['item_id']][(int) $r['prop_id']][] = $r;
        }
        foreach ($grouped as $iid => $byProp) {
            foreach ($byProp as $prows) {
                $p = $prows[0];
                $prop = ['type' => $p['type'], 'unit' => $p['unit']];
                $text = self::display($prop, self::fromRows($p['type'], $prows));
                if ((int) $p['in_eyebrow'] === 1) {
                    $items[$iid]['sub'][] = $text;
                }
                if ((int) $p['in_list'] === 1 && count($items[$iid]['specs']) < 4) {
                    $items[$iid]['specs'][] = ['pid' => (int) $p['prop_id'], 'label' => $p['short'] !== '' ? $p['short'] : $p['name'], 'value' => $text];
                }
            }
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($items[$id])) {
                $c = self::category((int) $items[$id]['category_id']);
                $items[$id]['category'] = $c;
                $items[$id]['url'] = url(($c['slug'] ?? '') . '/' . $items[$id]['slug']);
                $out[$id] = $items[$id];
            }
        }
        return $out;
    }

    public static function images(int $itemId): array
    {
        return Db::all('SELECT * FROM images WHERE item_id = ? ORDER BY sort, id', [$itemId]);
    }

    public static function deleteItem(int $itemId): void
    {
        $item = self::item($itemId);
        if (!$item) {
            return;
        }
        foreach (self::images($itemId) as $img) {
            Images::delete($img);
        }
        Db::exec('DELETE FROM items WHERE id = ?', [$itemId]);
        self::touch((int) $item['category_id']);
    }
}
