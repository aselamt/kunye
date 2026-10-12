<?php
declare(strict_types=1);

/** Kategori ve şablon düzenleme (yönetici) */
final class Templates
{
    public const ICONS = [
        'phone' => 'Telefon', 'laptop' => 'Laptop', 'watch' => 'Saat', 'headphones' => 'Kulaklık', 'camera' => 'Kamera',
        'tv' => 'Televizyon', 'monitor' => 'Monitör', 'speaker' => 'Hoparlör', 'gamepad' => 'Oyun', 'cpu' => 'İşlemci',
        'drive' => 'Depolama', 'battery' => 'Batarya', 'wifi' => 'Bağlantı', 'layers' => 'Katman', 'code' => 'Yazılım',
        'car' => 'Araç', 'bike' => 'Bisiklet', 'home' => 'Ev', 'droplet' => 'Damla', 'leaf' => 'Yaprak', 'sun' => 'Güneş',
        'bolt' => 'Enerji', 'utensils' => 'Mutfak', 'book' => 'Kitap', 'film' => 'Film', 'music' => 'Müzik', 'shirt' => 'Giyim',
        'tool' => 'Alet', 'ruler' => 'Ölçü', 'weight' => 'Ağırlık', 'palette' => 'Renk', 'globe' => 'Dünya', 'heart' => 'Sağlık',
        'box' => 'Kutu', 'tag' => 'Etiket', 'info' => 'Bilgi', 'shield' => 'Güvenlik', 'clock' => 'Zaman', 'star' => 'Yıldız',
    ];

    public static function icon(string $s): string
    {
        return isset(self::ICONS[$s]) ? $s : 'tag';
    }

    /** Kategori bilgileri; yeni kategori için kimlik döner */
    public static function saveCategory(?int $id, array $in): int
    {
        $name = Text::clean((string) ($in['name'] ?? ''), 80);
        $singular = Text::clean((string) ($in['singular'] ?? ''), 80) ?: $name;
        $slug = Text::slug((string) (($in['slug'] ?? '') ?: $singular), 60);
        if ($name === '') {
            throw new InvalidArgumentException('Kategori adı boş olamaz.');
        }
        if (!Catalog::validCategorySlug($slug)) {
            throw new InvalidArgumentException('“' . $slug . '” adresi kullanılamaz; başka bir adres seç.');
        }
        if (Db::val('SELECT 1 FROM categories WHERE slug = ? AND id != ?', [$slug, $id ?? 0])) {
            throw new InvalidArgumentException('Bu adres başka bir kategoride kullanılıyor.');
        }
        $section = null;
        $newSection = Text::clean((string) ($in['new_section'] ?? ''), 60);
        if ($newSection !== '') {
            $section = (int) (Db::val('SELECT id FROM sections WHERE name = ?', [$newSection])
                ?: Db::insert('sections', ['name' => $newSection, 'sort' => (int) Db::val('SELECT COALESCE(MAX(sort), 0) + 1 FROM sections')]));
        } elseif (!empty($in['section_id']) && Db::val('SELECT 1 FROM sections WHERE id = ?', [(int) $in['section_id']])) {
            $section = (int) $in['section_id'];
        }
        $sectionName = $section ? (string) Db::val('SELECT name FROM sections WHERE id = ?', [$section]) : '';
        $data = [
            'name' => $name, 'singular' => $singular, 'slug' => $slug, 'section_id' => $section,
            'icon' => self::icon((string) ($in['icon'] ?? 'tag')),
            'search' => Text::fold("$name $singular $sectionName"),
        ];
        if ($id === null) {
            $data['sort'] = (int) Db::val('SELECT COALESCE(MAX(sort), 0) + 1 FROM categories');
            $id = Db::insert('categories', $data);
            Db::insert('prop_groups', ['category_id' => $id, 'name' => 'Genel', 'icon' => 'info', 'sort' => 1]);
        } else {
            Db::update('categories', $data, 'id = ?', [$id]);
            foreach (Db::col('SELECT id FROM items WHERE category_id = ?', [$id]) as $iid) {
                Catalog::refreshSearch((int) $iid);
            }
        }
        Catalog::forget();
        Catalog::touch($id);
        return $id;
    }

    /**
     * Şablonu kaydeder.
     * $groups: [gid => ['name','icon','sort','delete']], $newGroup: ['name','icon']
     * $props: [pid => ['name','short','type','unit','group','sort','filterable','clickable','in_list','is_key','in_eyebrow','required','archive']]
     * $newProps: [gid => ['name','type','unit']]
     */
    public static function save(int $catId, array $groups, array $newGroup, array $props, array $newProps): array
    {
        $notes = [];
        Db::tx(static function () use ($catId, $groups, $newGroup, $props, $newProps, &$notes) {
            $tpl = Catalog::template($catId, null, true);
            foreach ($groups as $gid => $g) {
                $gid = (int) $gid;
                if (!isset($tpl['groups'][$gid]) || !is_array($g)) {
                    continue;
                }
                if (!empty($g['delete'])) {
                    if (Db::val('SELECT 1 FROM props WHERE group_id = ?', [$gid])) {
                        $notes[] = '“' . $tpl['groups'][$gid]['name'] . '” grubunda özellik olduğu için silinmedi.';
                    } else {
                        Db::exec('DELETE FROM prop_groups WHERE id = ?', [$gid]);
                    }
                    continue;
                }
                Db::update('prop_groups', [
                    'name' => Text::clean((string) ($g['name'] ?? ''), 60) ?: $tpl['groups'][$gid]['name'],
                    'icon' => self::icon((string) ($g['icon'] ?? 'layers')),
                    'sort' => (int) ($g['sort'] ?? 0),
                ], 'id = ?', [$gid]);
            }
            $ng = Text::clean((string) ($newGroup['name'] ?? ''), 60);
            if ($ng !== '') {
                Db::insert('prop_groups', ['category_id' => $catId, 'name' => $ng, 'icon' => self::icon((string) ($newGroup['icon'] ?? 'layers')),
                    'sort' => (int) Db::val('SELECT COALESCE(MAX(sort), 0) + 1 FROM prop_groups WHERE category_id = ? AND item_id IS NULL', [$catId])]);
            }
            $validGroups = array_map('intval', Db::col('SELECT id FROM prop_groups WHERE category_id = ? AND item_id IS NULL', [$catId]));
            foreach ($props as $pid => $p) {
                $pid = (int) $pid;
                if (!isset($tpl['props'][$pid]) || !is_array($p)) {
                    continue;
                }
                $cur = $tpl['props'][$pid];
                if (!empty($p['purge']) && $cur['archived']) {
                    Db::exec('DELETE FROM props WHERE id = ?', [$pid]);
                    $notes[] = '“' . $cur['name'] . '” ve değerleri kalıcı olarak silindi.';
                    continue;
                }
                $name = Text::clean((string) ($p['name'] ?? ''), 80) ?: $cur['name'];
                $type = isset(Catalog::TYPES[$p['type'] ?? '']) ? $p['type'] : $cur['type'];
                if ($type !== $cur['type']) {
                    try {
                        self::convert($cur, $type);
                    } catch (InvalidArgumentException $e) {
                        $notes[] = $e->getMessage();
                        $type = $cur['type'];
                    }
                }
                $group = (int) ($p['group'] ?? $cur['group_id']);
                $flags = [];
                foreach (['filterable', 'clickable', 'in_list', 'is_key', 'in_eyebrow', 'required'] as $f) {
                    $flags[$f] = array_key_exists($f, $p) ? (int) !empty($p[$f]) : $cur[$f];
                }
                Db::update('props', [
                    'name' => $name,
                    'short' => Text::clean((string) ($p['short'] ?? $cur['short']), 40),
                    // adres anahtarı yalnızca açıkça değiştirilirse değişir; paylaşılmış bağlantılar bozulmasın
                    'slug' => isset($p['slug']) && Text::slug((string) $p['slug'], 60) !== '' && Text::slug((string) $p['slug'], 60) !== $cur['slug']
                        ? Catalog::uniquePropSlug($catId, (string) $p['slug'], $pid) : $cur['slug'],
                    'type' => $type,
                    'unit' => Text::clean((string) ($p['unit'] ?? $cur['unit']), 20),
                    'group_id' => in_array($group, $validGroups, true) ? $group : $cur['group_id'],
                    'sort' => (int) ($p['sort'] ?? $cur['sort']),
                    'archived' => isset($p['archive']) ? (int) !empty($p['archive']) : $cur['archived'],
                ] + $flags, 'id = ?', [$pid]);
            }
            foreach ($newProps as $gid => $np) {
                $gid = (int) $gid;
                $name = Text::clean((string) ($np['name'] ?? ''), 80);
                if ($name === '' || !in_array($gid, $validGroups, true)) {
                    continue;
                }
                $type = isset(Catalog::TYPES[$np['type'] ?? '']) ? $np['type'] : 'text';
                Db::insert('props', [
                    'category_id' => $catId, 'group_id' => $gid, 'name' => $name, 'slug' => Catalog::uniquePropSlug($catId, $name),
                    'type' => $type, 'unit' => Text::clean((string) ($np['unit'] ?? ''), 20),
                    'filterable' => in_array($type, ['text'], true) ? 0 : 1,
                    'sort' => (int) Db::val('SELECT COALESCE(MAX(sort), 0) + 1 FROM props WHERE group_id = ?', [$gid]),
                ]);
            }
            self::limitFlag($catId, 'in_list', 4);
            self::limitFlag($catId, 'is_key', 4);
        });
        foreach (Db::col('SELECT id FROM items WHERE category_id = ?', [$catId]) as $iid) {
            Catalog::refreshSearch((int) $iid);
        }
        Catalog::touch($catId);
        return $notes;
    }

    /** Listede ve öne çıkanlarda en fazla 4 özellik (şablon sırasındaki ilk 4) */
    private static function limitFlag(int $catId, string $flag, int $max): void
    {
        $ids = Db::col("SELECT p.id FROM props p JOIN prop_groups g ON g.id = p.group_id
            WHERE p.category_id = ? AND p.item_id IS NULL AND p.$flag = 1 AND p.archived = 0 ORDER BY g.sort, p.sort, p.id", [$catId]);
        $extra = array_slice($ids, $max);
        if ($extra) {
            Db::exec("UPDATE props SET $flag = 0 WHERE id IN (" . Db::in($extra) . ')', $extra);
        }
    }

    /** Tip değiştirme: değerler mümkünse dönüştürülür, dönüştürülemiyorsa değişiklik reddedilir */
    public static function convert(array $prop, string $to): void
    {
        $from = $prop['type'];
        if ($from === $to) {
            return;
        }
        $pid = $prop['id'];
        if (in_array($from, ['single', 'multi'], true) && in_array($to, ['single', 'multi'], true)) {
            if ($to === 'single') {
                // her öğede yalnızca ilk değer kalır
                Db::exec('DELETE FROM vals WHERE prop_id = ? AND rowid NOT IN (SELECT MIN(rowid) FROM vals WHERE prop_id = ? GROUP BY item_id)', [$pid, $pid]);
            }
            Db::update('props', ['type' => $to], 'id = ?', [$pid]);
            return;
        }
        $rows = Db::all('SELECT v.item_id, v.num, v.bool, v.text, v.sort, o.name AS oname FROM vals v LEFT JOIN options o ON o.id = v.option_id
            WHERE v.prop_id = ? ORDER BY v.item_id, v.sort', [$pid]);
        $strings = [];
        foreach ($rows as $r) {
            $strings[] = [(int) $r['item_id'], match ($from) {
                'number' => num((float) $r['num'], 6),
                'bool' => $r['bool'] ? 'Var' : 'Yok',
                'text' => (string) $r['text'],
                default => (string) $r['oname'],
            }];
        }
        $target = $prop + ['type' => $to];
        $target['type'] = $to;
        $bad = 0;
        $values = [];
        foreach ($strings as [$iid, $s]) {
            $v = Catalog::normalize($target, $s);
            if ($v === null) {
                $bad++;
                continue;
            }
            if ($to === 'multi') {
                $values[$iid] = array_merge($values[$iid] ?? [], $v);
            } elseif (!isset($values[$iid])) {
                $values[$iid] = $v;
            }
        }
        if ($bad > 0) {
            throw new InvalidArgumentException('“' . $prop['name'] . '” tipi değiştirilemedi: ' . $bad . ' değer ' . Catalog::TYPE_HINT[$to] . ' biçimine çevrilemiyor.');
        }
        Db::exec('DELETE FROM vals WHERE prop_id = ?', [$pid]);
        Db::exec('DELETE FROM options WHERE prop_id = ?', [$pid]);
        Db::update('props', ['type' => $to], 'id = ?', [$pid]);
        foreach ($values as $iid => $v) {
            Catalog::setValue($iid, $target, $v);
        }
    }

    public static function deleteCategory(int $id): void
    {
        if (Db::val('SELECT 1 FROM items WHERE category_id = ?', [$id])) {
            throw new InvalidArgumentException('Kategoride öğe varken silinemez.');
        }
        Db::exec('DELETE FROM categories WHERE id = ?', [$id]);
        Db::exec('DELETE FROM sections WHERE id NOT IN (SELECT section_id FROM categories WHERE section_id IS NOT NULL)');
        Catalog::forget();
        Settings::bump();
    }
}
