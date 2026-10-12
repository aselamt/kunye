<?php
declare(strict_types=1);

/** Kullanıcı önerileri: düzeltme, eksik bilgi, yeni öğe */
final class Suggestions
{
    public const KINDS = ['fix' => ['Düzeltme', 'tag--fix'], 'fill' => ['Eksik bilgi', 'tag--fill'], 'new' => ['Yeni öğe', 'tag--new']];

    /** Gönderilen değerleri mevcutlarla karşılaştırır: [pid => [eski, yeni]] */
    public static function diff(array $props, array $current, array $submitted): array
    {
        $out = [];
        foreach ($submitted as $pid => $raw) {
            $pid = (int) $pid;
            if (!isset($props[$pid])) {
                continue;
            }
            $new = Catalog::normalize($props[$pid], $raw);
            $old = $current[$pid] ?? null;
            if ($props[$pid]['type'] === 'number' && is_string($raw) && trim($raw) !== '' && $new === null) {
                throw new InvalidArgumentException('“' . $props[$pid]['name'] . '” için geçerli bir sayı yaz.');
            }
            if (!Catalog::same($props[$pid], $old, $new)) {
                $out[$pid] = [$old, $new];
            }
        }
        return $out;
    }

    public static function create(array $user, array $cat, ?array $item, array $props, array $submitted, string $name, string $note, string $source): int
    {
        $source = trim($source);
        if ($source !== '' && (!filter_var($source, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $source))) {
            throw new InvalidArgumentException('Kaynak bağlantısı http:// ya da https:// ile başlayan geçerli bir adres olmalı.');
        }
        if ($source === '' && Settings::bool('kaynak_zorunlu')) {
            throw new InvalidArgumentException('Kaynak bağlantısı eklemelisin.');
        }
        $limit = max(1, Settings::int('gunluk_oneri'));
        if ($user['role'] === 'user' && (int) Db::val('SELECT COUNT(*) FROM suggestions WHERE user_id = ? AND created_at > ?', [$user['id'], time() - 86400]) >= $limit) {
            throw new InvalidArgumentException("Günde en fazla $limit öneri gönderebilirsin.");
        }
        $current = $item ? Catalog::values((int) $item['id'], $props) : [];
        $changes = self::diff($props, $current, $submitted);
        $name = Text::clean($name, 120);
        if ($item === null) {
            if ($name === '') {
                throw new InvalidArgumentException('Öğenin adını yaz.');
            }
            if (Db::val('SELECT 1 FROM items WHERE category_id = ? AND slug = ?', [$cat['id'], Text::slug($name)])) {
                throw new InvalidArgumentException('Bu adla bir öğe zaten var.');
            }
        }
        if (!$changes && $item !== null) {
            throw new InvalidArgumentException('Hiçbir değeri değiştirmedin.');
        }
        $kind = $item === null ? 'new' : (array_filter($changes, static fn($c) => !Catalog::isEmpty($c[0])) ? 'fix' : 'fill');
        return Db::tx(static function () use ($user, $cat, $item, $changes, $kind, $name, $note, $source) {
            $sid = Db::insert('suggestions', [
                'user_id' => $user['id'], 'item_id' => $item['id'] ?? null, 'category_id' => $cat['id'], 'kind' => $kind,
                'name' => $name, 'note' => Text::clean($note, 2000), 'source' => mb_substr($source, 0, 500), 'created_at' => time(),
            ]);
            foreach ($changes as $pid => [$old, $new]) {
                Db::insert('changes', ['suggestion_id' => $sid, 'prop_id' => $pid, 'old' => json_encode($old, JSON_UNESCAPED_UNICODE), 'new' => json_encode($new, JSON_UNESCAPED_UNICODE)]);
            }
            return $sid;
        });
    }

    /** Var olan benzer seçenekler (yeni bir değer için): [['name'=>, 'n'=>], …] */
    public static function similar(array $prop, string $value, int $limit = 3): array
    {
        if (!in_array($prop['type'], ['single', 'multi'], true)) {
            return [];
        }
        $norm = Text::fold($value);
        $out = [];
        foreach (Db::all('SELECT o.id, o.name, o.norm, (SELECT COUNT(*) FROM vals v WHERE v.option_id = o.id) AS n FROM options o WHERE o.prop_id = ?', [$prop['id']]) as $o) {
            if ($o['norm'] !== $norm && Text::similar($o['name'], $value)) {
                $out[] = ['name' => $o['name'], 'n' => (int) $o['n']];
            }
        }
        usort($out, static fn($a, $b) => $b['n'] <=> $a['n']);
        return array_slice($out, 0, $limit);
    }

    public static function exists(array $prop, string $value): bool
    {
        return (bool) Db::val('SELECT 1 FROM options WHERE prop_id = ? AND norm = ?', [$prop['id'], Text::fold($value)]);
    }

    /**
     * Editör kararı. $decisions: [change_id => ['ok' => bool, 'final' => ham girdi|null]]
     * Onaylanan değişiklikler hemen yayına girer. Yeni öğe önerisinde öğe oluşturulur.
     */
    public static function review(array $s, array $decisions, string $reply, array $editor, string $status = 'published'): ?int
    {
        return Db::tx(static function () use ($s, $decisions, $reply, $editor, $status) {
            $cat = Catalog::category((int) $s['category_id']);
            $itemId = $s['item_id'] !== null ? (int) $s['item_id'] : null;
            $changes = Db::all('SELECT * FROM changes WHERE suggestion_id = ?', [$s['id']]);
            $approved = array_filter($changes, static fn($c) => !empty($decisions[$c['id']]['ok']));
            if ($itemId === null && $approved) {
                $now = time();
                $itemId = Db::insert('items', [
                    'category_id' => $cat['id'], 'name' => $s['name'], 'slug' => Catalog::uniqueItemSlug((int) $cat['id'], $s['name']),
                    'status' => $status, 'created_by' => $s['user_id'], 'updated_by' => $editor['id'], 'created_at' => $now, 'updated_at' => $now,
                ]);
                Db::update('suggestions', ['item_id' => $itemId], 'id = ?', [$s['id']]);
            }
            $props = $itemId ? Catalog::template((int) $cat['id'], $itemId)['props'] : [];
            $names = [];
            foreach ($changes as $c) {
                $ok = !empty($decisions[$c['id']]['ok']) && isset($props[(int) $c['prop_id']]);
                $final = null;
                if ($ok) {
                    $prop = $props[(int) $c['prop_id']];
                    $raw = $decisions[$c['id']]['final'] ?? null;
                    $final = ($raw !== null && $raw !== '') ? Catalog::normalize($prop, $raw) : json_decode((string) $c['new'], true);
                    if ($prop['type'] === 'number' && $final !== null) {
                        $final = (float) $final;
                    }
                    Catalog::setValue($itemId, $prop, $final);
                    Db::exec('DELETE FROM hidden WHERE item_id = ? AND prop_id = ?', [$itemId, $prop['id']]);
                    $names[] = $prop['name'];
                }
                Db::update('changes', ['status' => $ok ? 'approved' : 'rejected', 'final' => $ok ? json_encode($final, JSON_UNESCAPED_UNICODE) : null], 'id = ?', [$c['id']]);
            }
            Db::update('suggestions', [
                'status' => $names ? 'done' : 'rejected', 'reply' => Text::clean($reply, 2000),
                'reviewed_by' => $editor['id'], 'reviewed_at' => time(),
            ], 'id = ?', [$s['id']]);
            if ($names && $itemId) {
                Db::update('items', ['updated_at' => time(), 'updated_by' => $editor['id']], 'id = ?', [$itemId]);
                $who = (string) Db::val('SELECT username FROM users WHERE id = ?', [$s['user_id']]);
                Catalog::log($itemId, $s['kind'] === 'new' ? 'oneri-yeni' : 'oneri', $who . ' önerisi: ' . implode(', ', $names));
                Catalog::refreshSearch($itemId);
                Catalog::touch((int) $cat['id']);
            }
            return $itemId;
        });
    }

    /** [toplam, onaylanan] */
    public static function stats(int $userId): array
    {
        $r = Db::one("SELECT COUNT(*) AS t, SUM(status = 'done') AS d, SUM(status != 'pending') AS r FROM suggestions WHERE user_id = ?", [$userId]);
        return [(int) $r['t'], (int) $r['d'], (int) $r['r']];
    }

    public static function pendingCount(): int
    {
        return (int) Db::val("SELECT COUNT(*) FROM suggestions WHERE status = 'pending'");
    }
}
