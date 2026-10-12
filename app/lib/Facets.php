<?php
declare(strict_types=1);

/**
 * Kategori başına bellek içi filtre dizini.
 * Dizin, kategori sürümüne bağlı bir PHP dosyası olarak önbelleğe yazılır (OPcache ile neredeyse bedava okunur).
 * Filtreleme ve seçenek sayıları bu dizin üzerinde küme işlemleriyle hesaplanır.
 */
final class Facets
{
    public const SORTS = ['yeni' => 'En yeni', 'populer' => 'En popüler', 'az' => 'A → Z'];

    private static array $mem = [];

    // ------------------------------------------------------------------ dizin

    public static function index(array $cat): array
    {
        $id = (int) $cat['id'];
        $v = (int) $cat['version'];
        if (isset(self::$mem[$id]) && self::$mem[$id]['v'] === $v) {
            return self::$mem[$id];
        }
        $file = Cache::dir('dizin') . "/k{$id}-v{$v}.php";
        $idx = Cache::readPhp($file);
        if (!is_array($idx) || ($idx['v'] ?? 0) !== $v) {
            $idx = self::build($id, $v);
            foreach (glob(Cache::dir('dizin') . "/k{$id}-v*.php") ?: [] as $old) {
                @unlink($old);
            }
            Cache::writePhp($file, $idx);
        }
        return self::$mem[$id] = $idx;
    }

    public static function forget(): void
    {
        self::$mem = [];
    }

    public static function build(int $catId, int $version): array
    {
        $idx = ['v' => $version, 'items' => [], 'order' => ['yeni' => [], 'az' => []], 'opts' => [], 'olist' => [], 'pop' => [], 'num' => [], 'bool' => [], 'text' => []];
        $items = Db::all("SELECT id, name, created_at FROM items WHERE category_id = ? AND status = 'published' ORDER BY created_at DESC, id DESC", [$catId]);
        $names = [];
        foreach ($items as $r) {
            $idx['items'][(int) $r['id']] = true;
            $idx['order']['yeni'][] = (int) $r['id'];
            $names[(int) $r['id']] = Text::fold($r['name']);
        }
        asort($names, SORT_STRING);
        $idx['order']['az'] = array_keys($names);

        foreach (Db::all('SELECT o.id, o.prop_id, o.name, o.slug FROM options o JOIN props p ON p.id = o.prop_id WHERE p.category_id = ?', [$catId]) as $o) {
            $idx['opts'][(int) $o['id']] = [(int) $o['prop_id'], $o['name'], $o['slug']];
        }
        $st = Db::q("SELECT v.item_id, v.prop_id, v.num, v.option_id, v.bool, v.text FROM vals v
            JOIN items i ON i.id = v.item_id JOIN props p ON p.id = v.prop_id
            WHERE i.category_id = ? AND i.status = 'published' AND p.archived = 0
              AND NOT EXISTS (SELECT 1 FROM hidden h WHERE h.item_id = v.item_id AND h.prop_id = v.prop_id)", [$catId]);
        while ($r = $st->fetch()) {
            $iid = (int) $r['item_id'];
            $pid = (int) $r['prop_id'];
            if ($r['option_id'] !== null) {
                $idx['olist'][(int) $r['option_id']][] = $iid;
            } elseif ($r['num'] !== null) {
                $idx['num'][$pid][$iid] = (float) $r['num'];
            } elseif ($r['bool'] !== null) {
                $idx['bool'][$pid][$iid] = (int) $r['bool'];
            } elseif ($r['text'] !== null) {
                $idx['text'][$pid][$r['text']][] = $iid;
            }
        }
        // seçenekleri özelliğe göre, kullanım sayısına göre sırala
        $byProp = [];
        foreach ($idx['olist'] as $oid => $list) {
            if (isset($idx['opts'][$oid])) {
                $byProp[$idx['opts'][$oid][0]][$oid] = count($list);
            }
        }
        foreach ($byProp as $pid => $counts) {
            arsort($counts);
            $idx['pop'][$pid] = array_keys($counts);
        }
        // kullanılmayan seçenekleri dizine alma
        $idx['opts'] = array_intersect_key($idx['opts'], $idx['olist']);
        return $idx;
    }

    // ------------------------------------------------------------------ adres ↔ filtre

    /**
     * JS'siz form gönderimini (marka[]=a&marka[]=b, depolama[min]=…) kanonik adrese çevirir.
     * Değişiklik yoksa null döner.
     */
    public static function canonical(array $props, array $get): ?array
    {
        $out = [];
        $changed = false;
        foreach ($get as $k => $v) {
            $k = (string) $k;
            if ($k === 'sirala') {
                if (is_string($v) && isset(self::SORTS[$v]) && $v !== 'yeni') {
                    $out[$k] = $v;
                } else {
                    $changed = true;
                }
                continue;
            }
            if ($k === 'sayfa') {
                if (is_string($v) && ctype_digit($v) && (int) $v > 1) {
                    $out[$k] = (string) (int) $v;
                } else {
                    $changed = true;
                }
                continue;
            }
            if (!isset($props[$k])) {
                $changed = true;
                continue;
            }
            $p = $props[$k];
            $val = self::canonicalValue($p, $v);
            if ($val === null) {
                $changed = true;
                continue;
            }
            if (!is_string($v) || $v !== $val) {
                $changed = true;
            }
            $out[$k] = $val;
        }
        return $changed ? $out : null;
    }

    private static function canonicalValue(array $p, mixed $v): ?string
    {
        switch ($p['type']) {
            case 'number':
                if (is_array($v)) {
                    $min = Text::number((string) ($v['min'] ?? ''));
                    $max = Text::number((string) ($v['max'] ?? ''));
                } else {
                    [$min, $max] = self::parseRange((string) $v);
                }
                if ($min === null && $max === null) {
                    return null;
                }
                if ($min !== null && $max !== null && $min > $max) {
                    [$min, $max] = [$max, $min];
                }
                if ($min !== null && $max !== null && abs($min - $max) < 1e-9) {
                    return Text::urlNum($min);
                }
                return ($min !== null ? Text::urlNum($min) : '') . '..' . ($max !== null ? Text::urlNum($max) : '');
            case 'bool':
                $v = is_array($v) ? (string) reset($v) : (string) $v;
                return in_array($v, ['var', 'yok'], true) ? $v : null;
            case 'text':
                $v = is_array($v) ? (string) reset($v) : (string) $v;
                $v = Text::clean($v, 500);
                return $v === '' ? null : $v;
            default:
                $list = is_array($v) ? $v : explode(',', (string) $v);
                $list = array_values(array_unique(array_filter(array_map(static fn($s) => is_string($s) ? Text::slug($s) : '', $list))));
                sort($list);
                return $list ? implode(',', $list) : null;
        }
    }

    /** "256..1024" → [256, 1024] · "512" → [512, 512] · "..64" → [null, 64] */
    public static function parseRange(string $s): array
    {
        if (str_contains($s, '..')) {
            [$a, $b] = explode('..', $s, 2);
            $min = $a === '' ? null : (is_numeric($a) ? (float) $a : null);
            $max = $b === '' ? null : (is_numeric($b) ? (float) $b : null);
            return [$min, $max];
        }
        $n = is_numeric($s) ? (float) $s : null;
        return [$n, $n];
    }

    /** Kanonik sorgudan etkin filtreler (adres sırasıyla) */
    public static function parse(array $props, array $query, array $idx): array
    {
        $out = [];
        foreach ($query as $k => $v) {
            if (!isset($props[$k]) || !is_string($v)) {
                continue;
            }
            $p = $props[$k];
            $f = ['prop' => $p];
            switch ($p['type']) {
                case 'number':
                    [$f['min'], $f['max']] = self::parseRange($v);
                    if ($f['min'] === null && $f['max'] === null) {
                        continue 2;
                    }
                    break;
                case 'bool':
                    $f['val'] = $v === 'var';
                    break;
                case 'text':
                    $f['val'] = $v;
                    break;
                default:
                    $slugs = array_flip(explode(',', $v));
                    $f['oids'] = [];
                    foreach ($idx['pop'][$p['id']] ?? [] as $oid) {
                        if (isset($slugs[$idx['opts'][$oid][2]])) {
                            $f['oids'][] = $oid;
                        }
                    }
                    $f['slugs'] = array_keys($slugs);
                    if (!$f['oids']) {
                        $f['oids'] = [-1]; // bilinmeyen değer: sonuç yok
                    }
            }
            $out[$p['id']] = $f;
        }
        return $out;
    }

    // ------------------------------------------------------------------ sorgu

    private static function setFor(array $idx, array $f): array
    {
        $pid = $f['prop']['id'];
        switch ($f['prop']['type']) {
            case 'number':
                $s = [];
                foreach ($idx['num'][$pid] ?? [] as $iid => $n) {
                    if (($f['min'] === null || $n >= $f['min'] - 1e-9) && ($f['max'] === null || $n <= $f['max'] + 1e-9)) {
                        $s[$iid] = true;
                    }
                }
                return $s;
            case 'bool':
                $s = [];
                foreach ($idx['bool'][$pid] ?? [] as $iid => $b) {
                    if ((bool) $b === $f['val']) {
                        $s[$iid] = true;
                    }
                }
                return $s;
            case 'text':
                return array_fill_keys($idx['text'][$pid][$f['val']] ?? [], true);
            case 'multi': // çoklu seçimde seçilenlerin hepsi (VE)
                $s = null;
                foreach ($f['oids'] as $oid) {
                    $l = array_fill_keys($idx['olist'][$oid] ?? [], true);
                    $s = $s === null ? $l : array_intersect_key($s, $l);
                }
                return $s ?? [];
            default: // tek seçimde herhangi biri (VEYA)
                $s = [];
                foreach ($f['oids'] as $oid) {
                    foreach ($idx['olist'][$oid] ?? [] as $iid) {
                        $s[$iid] = true;
                    }
                }
                return $s;
        }
    }

    private static function intersect(array $sets, array $base): array
    {
        usort($sets, static fn($a, $b) => count($a) <=> count($b));
        foreach ($sets as $s) {
            $base = count($s) < count($base) ? array_intersect_key($s, $base) : array_intersect_key($base, $s);
            if (!$base) {
                break;
            }
        }
        return $base;
    }

    /**
     * Listeleme: sonuç kimlikleri (sıralı) ve filtre kutuları.
     * $filterable: kenar çubuğunda gösterilecek özellikler (şablon sırasıyla).
     */
    public static function run(array $cat, array $idx, array $filters, array $filterable, string $sort): array
    {
        $sets = [];
        foreach ($filters as $pid => $f) {
            $sets[$pid] = self::setFor($idx, $f);
        }
        $result = self::intersect($sets, $idx['items']);

        // kenar çubuğu: önce etkin filtreler (adres sırasıyla), sonra diğerleri
        $show = [];
        foreach ($filters as $pid => $f) {
            $show[$pid] = $f['prop'];
        }
        foreach ($filterable as $p) {
            $show[$p['id']] ??= $p;
        }

        $facets = [];
        foreach ($show as $pid => $p) {
            $active = isset($filters[$pid]);
            // tek seçim / sayı / var-yok: kendi filtresi hariç diğerleriyle say (seçenekler arasında VEYA)
            $base = $result;
            if ($active && $p['type'] !== 'multi') {
                $others = $sets;
                unset($others[$pid]);
                $base = self::intersect($others, $idx['items']);
            }
            $facet = ['prop' => $p, 'active' => $active, 'filter' => $filters[$pid] ?? null];
            switch ($p['type']) {
                case 'number':
                    $vals = [];
                    foreach ($idx['num'][$pid] ?? [] as $iid => $n) {
                        if (isset($base[$iid])) {
                            $k = Text::urlNum($n);
                            $vals[$k] = ($vals[$k] ?? 0) + 1;
                        }
                    }
                    uksort($vals, static fn($a, $b) => (float) $a <=> (float) $b);
                    $facet['values'] = $vals;
                    if (!$vals && !$active) {
                        continue 2;
                    }
                    break;
                case 'bool':
                    $y = $n = 0;
                    foreach ($idx['bool'][$pid] ?? [] as $iid => $b) {
                        if (isset($base[$iid])) {
                            $b ? $y++ : $n++;
                        }
                    }
                    if ($y + $n === 0 && !$active) {
                        continue 2;
                    }
                    $facet['yes'] = $y;
                    $facet['no'] = $n;
                    break;
                case 'text':
                    if (!$active) {
                        continue 2;
                    }
                    break;
                default:
                    $opts = [];
                    $sel = $active ? array_flip($filters[$pid]['oids']) : [];
                    foreach ($idx['pop'][$pid] ?? [] as $oid) {
                        $c = 0;
                        foreach ($idx['olist'][$oid] as $iid) {
                            if (isset($base[$iid])) {
                                $c++;
                            }
                        }
                        if ($c > 0 || isset($sel[$oid])) {
                            [, $name, $slug] = $idx['opts'][$oid];
                            $opts[] = ['id' => $oid, 'name' => $name, 'slug' => $slug, 'n' => $c, 'on' => isset($sel[$oid])];
                        }
                    }
                    if (!$opts && !$active) {
                        continue 2;
                    }
                    usort($opts, static fn($a, $b) => [$b['on'], $b['n']] <=> [$a['on'], $a['n']]);
                    $facet['options'] = $opts;
            }
            $facets[$pid] = $facet;
        }

        // sıralama
        if ($sort === 'populer') {
            $order = array_map('intval', Db::col("SELECT id FROM items WHERE category_id = ? AND status = 'published' ORDER BY fav_count DESC, name", [$cat['id']]));
        } else {
            $order = $idx['order'][$sort] ?? $idx['order']['yeni'];
        }
        $ids = [];
        foreach ($order as $iid) {
            if (isset($result[$iid])) {
                $ids[] = $iid;
            }
        }
        return ['ids' => $ids, 'facets' => $facets];
    }

    /** Bir değere tam olarak sahip yayımlanmış öğe sayısı (detay sayfasındaki "N öğe →") */
    public static function countValue(array $idx, array $prop, array $row): int
    {
        $pid = $prop['id'];
        return match ($prop['type']) {
            'number' => count(array_filter($idx['num'][$pid] ?? [], static fn($n) => abs($n - (float) $row['num']) < 1e-9)),
            'bool' => count(array_filter($idx['bool'][$pid] ?? [], static fn($b) => $b === (int) $row['bool'])),
            'text' => count($idx['text'][$pid][$row['text']] ?? []),
            default => count($idx['olist'][(int) $row['oid']] ?? []),
        };
    }

    /** Detay sayfasındaki değerin bağlantı sorgusu */
    public static function valueQuery(array $prop, array $row): array
    {
        return [$prop['slug'] => match ($prop['type']) {
            'number' => Text::urlNum((float) $row['num']),
            'bool' => $row['bool'] ? 'var' : 'yok',
            'text' => $row['text'],
            default => $row['oslug'],
        }];
    }

    /** Filtre kutusu başlığı için insan okunur özet: "512 GB", "256–1024 GB", "Xiaomi, Apple" */
    public static function chip(array $facet): string
    {
        $p = $facet['prop'];
        $f = $facet['filter'];
        $u = $p['unit'] !== '' ? ' ' . $p['unit'] : '';
        return match ($p['type']) {
            'number' => $f['min'] !== null && $f['max'] !== null && abs($f['min'] - $f['max']) < 1e-9
                ? num($f['min']) . $u
                : ($f['min'] !== null ? num($f['min']) : '…') . '–' . ($f['max'] !== null ? num($f['max']) : '…') . $u,
            'bool' => $f['val'] ? 'Var' : 'Yok',
            'text' => $f['val'],
            default => implode(', ', array_map(static fn($o) => $o['name'], array_filter($facet['options'] ?? [], static fn($o) => $o['on']))) ?: '—',
        };
    }
}
