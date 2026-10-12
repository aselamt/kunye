<?php
declare(strict_types=1);

/** Veri temizliği: değerleri kullanım sayılarıyla listeleme, yeniden adlandırma, birleştirme */
final class Values
{
    /** [['key' => seçenek kimliği | sayı | metin, 'name' => görünen, 'n' => kullanım, 'similar' => [ad…]], …] */
    public static function list(array $prop): array
    {
        $pid = $prop['id'];
        switch ($prop['type']) {
            case 'single':
            case 'multi':
                $rows = Db::all('SELECT o.id AS k, o.name, COUNT(v.item_id) AS n FROM options o LEFT JOIN vals v ON v.option_id = o.id
                    WHERE o.prop_id = ? GROUP BY o.id ORDER BY n DESC, o.name', [$pid]);
                break;
            case 'number':
                $rows = Db::all('SELECT num AS k, COUNT(*) AS n FROM vals WHERE prop_id = ? AND num IS NOT NULL GROUP BY num ORDER BY n DESC, num', [$pid]);
                foreach ($rows as &$r) {
                    $r['name'] = num((float) $r['k'], 6);
                    $r['k'] = Text::urlNum((float) $r['k']);
                }
                unset($r);
                break;
            case 'text':
                $rows = Db::all('SELECT text AS k, text AS name, COUNT(*) AS n FROM vals WHERE prop_id = ? AND text IS NOT NULL GROUP BY text ORDER BY n DESC, text', [$pid]);
                break;
            default:
                $rows = Db::all("SELECT bool AS k, CASE bool WHEN 1 THEN 'Var' ELSE 'Yok' END AS name, COUNT(*) AS n FROM vals
                    WHERE prop_id = ? AND bool IS NOT NULL GROUP BY bool ORDER BY bool DESC", [$pid]);
        }
        $words = $prop['type'] !== 'number' && $prop['type'] !== 'bool' && count($rows) <= 3000;
        foreach ($rows as $i => &$r) {
            $r['n'] = (int) $r['n'];
            $r['k'] = (string) $r['k'];
            $r['similar'] = [];
            if ($words) {
                foreach ($rows as $j => $o) {
                    if ($i !== $j && Text::similar((string) $r['name'], (string) $o['name'])) {
                        $r['similar'][] = ['k' => (string) $o['k'], 'name' => $o['name'], 'n' => (int) $o['n']];
                    }
                }
            }
        }
        unset($r);
        return $rows;
    }

    public static function rename(array $prop, string $key, string $newName): string
    {
        $newName = Text::clean($newName, $prop['type'] === 'text' ? 500 : 120);
        if ($newName === '') {
            throw new InvalidArgumentException('Yeni ad boş olamaz.');
        }
        $pid = $prop['id'];
        $msg = Db::tx(static function () use ($prop, $pid, $key, $newName) {
            switch ($prop['type']) {
                case 'single':
                case 'multi':
                    $o = Db::one('SELECT * FROM options WHERE id = ? AND prop_id = ?', [(int) $key, $pid]);
                    if (!$o) {
                        throw new InvalidArgumentException('Değer bulunamadı.');
                    }
                    $norm = Text::fold($newName);
                    $other = Db::val('SELECT id FROM options WHERE prop_id = ? AND norm = ? AND id != ?', [$pid, $norm, $o['id']]);
                    if ($other) {
                        self::mergeOptions($pid, [(int) $o['id']], (int) $other);
                        return '“' . $o['name'] . '”, var olan “' . $newName . '” değeriyle birleştirildi.';
                    }
                    $slug = Text::slug($newName, 60) ?: 'deger';
                    $base = $slug;
                    for ($i = 2; Db::val('SELECT 1 FROM options WHERE prop_id = ? AND slug = ? AND id != ?', [$pid, $slug, $o['id']]); $i++) {
                        $slug = $base . '-' . $i;
                    }
                    Db::update('options', ['name' => $newName, 'norm' => $norm, 'slug' => $slug], 'id = ?', [$o['id']]);
                    return '“' . $o['name'] . '” → “' . $newName . '”';
                case 'number':
                    $to = Text::number($newName);
                    if ($to === null) {
                        throw new InvalidArgumentException('Geçerli bir sayı yaz.');
                    }
                    $n = Db::exec('UPDATE vals SET num = ? WHERE prop_id = ? AND ABS(num - ?) < 1e-9', [$to, $pid, (float) $key]);
                    return num((float) $key) . ' → ' . num($to) . " ($n öğe)";
                case 'text':
                    $n = Db::exec('UPDATE vals SET text = ? WHERE prop_id = ? AND text = ?', [$newName, $pid, $key]);
                    return "“{$key}” → “{$newName}” ($n öğe)";
            }
            throw new InvalidArgumentException('Bu tipteki değerler yeniden adlandırılamaz.');
        });
        self::after($prop, 'deger-adlandir', $prop['name'] . ': ' . $msg);
        return $msg;
    }

    /** Seçilen değerleri hedefte birleştirir */
    public static function merge(array $prop, array $keys, string $target): string
    {
        $keys = array_values(array_unique(array_filter(array_map('strval', $keys), static fn($k) => $k !== $target)));
        if (!$keys) {
            throw new InvalidArgumentException('Birleştirmek için en az iki değer seç.');
        }
        $pid = $prop['id'];
        $msg = Db::tx(static function () use ($prop, $pid, $keys, $target) {
            switch ($prop['type']) {
                case 'single':
                case 'multi':
                    $t = Db::one('SELECT * FROM options WHERE id = ? AND prop_id = ?', [(int) $target, $pid]);
                    if (!$t) {
                        throw new InvalidArgumentException('Hedef değer bulunamadı.');
                    }
                    $n = self::mergeOptions($pid, array_map('intval', $keys), (int) $t['id']);
                    return count($keys) . ' değer “' . $t['name'] . '” ile birleştirildi (' . $n . ' öğe).';
                case 'number':
                    $to = (float) $target;
                    $n = 0;
                    foreach ($keys as $k) {
                        $n += Db::exec('UPDATE vals SET num = ? WHERE prop_id = ? AND ABS(num - ?) < 1e-9', [$to, $pid, (float) $k]);
                    }
                    return count($keys) . ' değer ' . num($to) . ' ile birleştirildi (' . $n . ' öğe).';
                case 'text':
                    $n = 0;
                    foreach ($keys as $k) {
                        $n += Db::exec('UPDATE vals SET text = ? WHERE prop_id = ? AND text = ?', [$target, $pid, $k]);
                    }
                    return count($keys) . ' değer “' . $target . '” ile birleştirildi (' . $n . ' öğe).';
            }
            throw new InvalidArgumentException('Bu tipteki değerler birleştirilemez.');
        });
        self::after($prop, 'deger-birlestir', $prop['name'] . ': ' . $msg);
        return $msg;
    }

    private static function mergeOptions(int $pid, array $sources, int $target): int
    {
        $n = 0;
        foreach ($sources as $src) {
            if ($src === $target || !Db::val('SELECT 1 FROM options WHERE id = ? AND prop_id = ?', [$src, $pid])) {
                continue;
            }
            // aynı öğede iki değer birden varsa (çoklu seçim) tekrarı önle
            Db::exec('DELETE FROM vals WHERE option_id = ? AND item_id IN (SELECT item_id FROM vals WHERE option_id = ?)', [$src, $target]);
            $n += Db::exec('UPDATE vals SET option_id = ? WHERE option_id = ?', [$target, $src]);
            Db::exec('DELETE FROM options WHERE id = ?', [$src]);
        }
        return $n;
    }

    public static function deleteUnused(array $prop): int
    {
        $n = Db::exec('DELETE FROM options WHERE prop_id = ? AND NOT EXISTS (SELECT 1 FROM vals v WHERE v.option_id = options.id)', [$prop['id']]);
        if ($n) {
            Catalog::touch($prop['category_id']);
        }
        return $n;
    }

    private static function after(array $prop, string $action, string $detail): void
    {
        if ($prop['in_eyebrow']) {
            foreach (Db::col('SELECT DISTINCT item_id FROM vals WHERE prop_id = ?', [$prop['id']]) as $iid) {
                Catalog::refreshSearch((int) $iid);
            }
        }
        Catalog::log(null, $action, $detail);
        Catalog::touch($prop['category_id']);
    }
}
