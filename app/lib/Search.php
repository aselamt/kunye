<?php
declare(strict_types=1);

/**
 * Arama: kategoriler, öğeler ve özellik değerleri.
 * Tüm metinler Text::fold() ile katlanmış sütunlarda tutulur; "sarj" yazınca "Şarj" bulunur.
 */
final class Search
{
    private static function where(string $col, array $terms, array &$p): string
    {
        $w = [];
        foreach ($terms as $t) {
            $w[] = "$col LIKE ? ESCAPE '\\'";
            $p[] = '%' . Text::like($t) . '%';
        }
        return $w ? implode(' AND ', $w) : '1';
    }

    public static function categories(array $terms, int $limit): array
    {
        if (!$terms) {
            return [];
        }
        $p = [];
        $sql = 'SELECT c.*, s.name AS section FROM categories c LEFT JOIN sections s ON s.id = c.section_id WHERE '
            . self::where('c.search', $terms, $p) . ' ORDER BY (c.search LIKE ?) DESC, c.item_count DESC LIMIT ' . $limit;
        $p[] = Text::like($terms[0]) . '%';
        return Db::all($sql, $p);
    }

    /** Öğeler; [kimlikler, toplam] */
    public static function items(array $terms, int $limit, int $offset = 0): array
    {
        if (!$terms) {
            return [[], 0];
        }
        $p = [];
        $where = "status = 'published' AND " . self::where('search', $terms, $p);
        $total = (int) Db::val("SELECT COUNT(*) FROM items WHERE $where", $p);
        $q = $p;
        $q[] = Text::like($terms[0]) . '%';
        $q[] = '% ' . Text::like($terms[0]) . '%';
        $ids = Db::col("SELECT id FROM items WHERE $where
            ORDER BY (search LIKE ? ESCAPE '\\') DESC, (search LIKE ? ESCAPE '\\') DESC, fav_count DESC, name LIMIT $limit OFFSET $offset", $q);
        return [array_map('intval', $ids), $total];
    }

    /** Özellik değerleri (seçenekler), yayımlanmış öğelerdeki kullanım sayısıyla */
    public static function values(array $terms, int $limit): array
    {
        if (!$terms) {
            return [];
        }
        $p = [];
        $sql = "SELECT o.id, o.name, o.slug, p.name AS prop, p.slug AS pslug, c.name AS cat, c.slug AS cslug,
                (SELECT COUNT(DISTINCT v.item_id) FROM vals v JOIN items i ON i.id = v.item_id
                  WHERE v.option_id = o.id AND i.status = 'published') AS n
            FROM options o JOIN props p ON p.id = o.prop_id JOIN categories c ON c.id = p.category_id
            WHERE p.archived = 0 AND p.item_id IS NULL AND " . self::where('o.norm', $terms, $p) . '
            ORDER BY n DESC LIMIT ' . ($limit * 3);
        $rows = array_filter(Db::all($sql, $p), static fn($r) => (int) $r['n'] > 0);
        // var/yok özellikleri de değer gibi önerilir: "sarj" → "Kablosuz Şarj: Var"
        $p = [];
        $bools = Db::all("SELECT p.name AS prop, p.slug AS pslug, c.name AS cat, c.slug AS cslug,
                (SELECT COUNT(*) FROM vals v JOIN items i ON i.id = v.item_id WHERE v.prop_id = p.id AND v.bool = 1 AND i.status = 'published') AS n
            FROM props p JOIN categories c ON c.id = p.category_id
            WHERE p.type = 'bool' AND p.archived = 0 AND p.item_id IS NULL AND " . self::where('p.slug', array_map(static fn($t) => str_replace(' ', '-', $t), $terms), $p) . '
            ORDER BY n DESC LIMIT ' . $limit, $p);
        foreach ($bools as $b) {
            if ((int) $b['n'] > 0) {
                $rows[] = ['id' => 0, 'name' => $b['prop'], 'slug' => 'var', 'prop' => 'Var', 'pslug' => $b['pslug'], 'cat' => $b['cat'], 'cslug' => $b['cslug'], 'n' => $b['n']];
            }
        }
        usort($rows, static fn($a, $b) => (int) $b['n'] <=> (int) $a['n']);
        return array_slice(array_values($rows), 0, $limit);
    }

    /** Arama kutusunun öneri listesi (JSON) */
    public static function suggest(string $q): array
    {
        $terms = Text::terms($q);
        if (!$terms || mb_strlen(implode('', $terms)) < 1) {
            return ['kategoriler' => [], 'ogeler' => [], 'degerler' => []];
        }
        $cats = array_map(static fn($c) => [
            'ad' => $c['name'], 'html' => Text::mark($c['name'], $terms), 'alt' => $c['section'] ?? '',
            'ikon' => $c['icon'], 'n' => (int) $c['item_count'], 'url' => url($c['slug']),
        ], self::categories($terms, 3));
        [$ids] = self::items($terms, 5);
        $items = array_map(static fn($c) => [
            'ad' => $c['name'], 'html' => Text::mark($c['name'], $terms), 'alt' => $c['category']['singular'] ?? '',
            'gorsel' => Images::thumb($c['cover']), 'url' => $c['url'],
        ], array_values(Catalog::cards($ids)));
        $vals = array_map(static fn($v) => [
            'ad' => $v['name'], 'html' => Text::mark($v['name'], $terms), 'alt' => $v['cat'] . ' · ' . $v['prop'],
            'n' => (int) $v['n'], 'url' => url($v['cslug'], [$v['pslug'] => $v['slug']]),
        ], self::values($terms, 3));
        return ['kategoriler' => $cats, 'ogeler' => $items, 'degerler' => $vals];
    }
}
