<?php
declare(strict_types=1);

/** Editör: öneri kutusu */
final class PanelReviewController
{
    public const TABS = ['pending' => 'Bekleyen', 'done' => 'Onaylanan', 'rejected' => 'Reddedilen'];

    public static function index(): void
    {
        Auth::require('editor');
        $tab = isset(self::TABS[Request::get('durum')]) ? Request::get('durum') : 'pending';
        $q = Text::clean(Request::get('q'), 80);
        $where = 's.status = ?';
        $p = [$tab];
        if ($q !== '') {
            $where .= " AND (i.search LIKE ? ESCAPE '\\' OR u.username LIKE ? ESCAPE '\\' OR s.name LIKE ? ESCAPE '\\')";
            $like = '%' . Text::like(Text::fold($q)) . '%';
            array_push($p, $like, '%' . Text::like($q) . '%', '%' . Text::like($q) . '%');
        }
        $list = Db::all("SELECT s.*, u.username, i.name AS item_name, i.slug AS item_slug, c.name AS cat_name, c.slug AS cat_slug, c.icon AS cat_icon,
                (SELECT COUNT(*) FROM changes ch WHERE ch.suggestion_id = s.id) AS n
            FROM suggestions s JOIN users u ON u.id = s.user_id JOIN categories c ON c.id = s.category_id
            LEFT JOIN items i ON i.id = s.item_id
            WHERE $where ORDER BY s.created_at " . ($tab === 'pending' ? 'ASC' : 'DESC') . ' LIMIT 200', $p);
        $counts = Db::pairs('SELECT status, COUNT(*) FROM suggestions GROUP BY status');
        $sel = null;
        $selId = (int) Request::get('id');
        foreach ($list as $s) {
            if ($selId === 0 || (int) $s['id'] === $selId) {
                $sel = $s;
                break;
            }
        }
        if ($selId && !$sel) {
            $sel = Db::one('SELECT s.*, u.username, i.name AS item_name, i.slug AS item_slug, c.name AS cat_name, c.slug AS cat_slug, c.icon AS cat_icon, 0 AS n
                FROM suggestions s JOIN users u ON u.id = s.user_id JOIN categories c ON c.id = s.category_id LEFT JOIN items i ON i.id = s.item_id WHERE s.id = ?', [$selId]);
        }
        $detail = $sel ? self::detail($sel) : null;
        // liste özetleri: değişen grup adları
        $summaries = [];
        if ($list) {
            $ids = array_column($list, 'id');
            foreach (Db::all('SELECT ch.suggestion_id, p.name, g.name AS gname FROM changes ch JOIN props p ON p.id = ch.prop_id JOIN prop_groups g ON g.id = p.group_id
                    WHERE ch.suggestion_id IN (' . Db::in($ids) . ') ORDER BY g.sort, p.sort', $ids) as $r) {
                $summaries[$r['suggestion_id']][$r['gname']] = $r['name'];
            }
        }
        $covers = [];
        foreach ($list as $s) {
            if ($s['item_id']) {
                $covers[$s['item_id']] = null;
            }
        }
        if ($covers) {
            foreach (Catalog::cards(array_keys($covers)) as $iid => $card) {
                $covers[$iid] = $card['cover'];
            }
        }
        View::send('panel/oneriler', [
            'title' => 'Öneriler', 'nav' => 'oneriler', 'wide' => true, 'crumbs' => '<b>Öneriler</b>',
            'actions' => '<form method="get" action="' . e(url('panel/oneriler')) . '"><input type="hidden" name="durum" value="' . e($tab) . '"><span class="input input--sm" style="width:220px">'
                . icon('search', 'icon icon--sm') . '<input type="search" name="q" value="' . e($q) . '" placeholder="Öğe ya da kullanıcı ara" aria-label="Önerilerde ara"></span></form>',
            'tab' => $tab, 'q' => $q, 'list' => $list, 'counts' => $counts, 'sel' => $sel, 'detail' => $detail, 'summaries' => $summaries, 'covers' => $covers,
        ], 'panel');
    }

    /** Seçili önerinin değişiklikleri, eski/yeni gösterimleri ve benzer değer uyarıları */
    private static function detail(array $s): array
    {
        $props = Catalog::template((int) $s['category_id'], $s['item_id'] ? (int) $s['item_id'] : null)['props'];
        $groups = Db::pairs('SELECT id, name FROM prop_groups WHERE category_id = ?', [$s['category_id']]);
        $gicons = Db::pairs('SELECT id, icon FROM prop_groups WHERE category_id = ?', [$s['category_id']]);
        $rows = [];
        foreach (Db::all('SELECT * FROM changes WHERE suggestion_id = ? ORDER BY id', [$s['id']]) as $c) {
            $p = $props[(int) $c['prop_id']] ?? null;
            if (!$p) {
                continue;
            }
            $old = json_decode((string) $c['old'], true);
            $new = json_decode((string) $c['new'], true);
            $final = $c['final'] !== null ? json_decode((string) $c['final'], true) : null;
            $warn = [];
            $candidates = $p['type'] === 'single' ? [(string) $new] : ($p['type'] === 'multi' ? array_diff((array) $new, (array) $old) : []);
            foreach ($candidates as $val) {
                if ($val !== '' && !Suggestions::exists($p, $val)) {
                    $warn[] = ['value' => $val, 'similar' => Suggestions::similar($p, $val)];
                }
            }
            $rows[] = [
                'change' => $c, 'prop' => $p, 'group' => $groups[$p['group_id']] ?? '', 'gicon' => $gicons[$p['group_id']] ?? 'tag',
                'old' => $old, 'new' => $new, 'final' => $final, 'warn' => $warn,
                'added' => $p['type'] === 'multi' ? array_values(array_udiff((array) $new, (array) $old, static fn($a, $b) => strcmp(Text::fold($a), Text::fold($b)))) : [],
                'removed' => $p['type'] === 'multi' ? array_values(array_udiff((array) $old, (array) $new, static fn($a, $b) => strcmp(Text::fold($a), Text::fold($b)))) : [],
            ];
        }
        [$total, $done, $reviewed] = Suggestions::stats((int) $s['user_id']);
        return ['rows' => $rows, 'stats' => [$total, $reviewed ? (int) round($done / $reviewed * 100) : null]];
    }

    public static function decide(string $id): void
    {
        $editor = Auth::require('editor');
        $s = Db::one('SELECT * FROM suggestions WHERE id = ?', [(int) $id]) ?? throw new HttpError(404);
        if ($s['status'] !== 'pending') {
            flash('Bu öneri zaten sonuçlandırılmış.', 'bad');
            Response::redirect(url('panel/oneriler', ['id' => $s['id'], 'durum' => $s['status']]));
        }
        $reject = Request::post('islem') === 'reddet';
        $ok = is_array($_POST['ok'] ?? null) ? $_POST['ok'] : [];
        $final = is_array($_POST['final'] ?? null) ? $_POST['final'] : [];
        $decisions = [];
        foreach (Db::col('SELECT id FROM changes WHERE suggestion_id = ?', [$s['id']]) as $cid) {
            $decisions[(int) $cid] = ['ok' => !$reject && !empty($ok[$cid]), 'final' => isset($final[$cid]) && is_string($final[$cid]) ? trim($final[$cid]) : null];
        }
        $status = Request::post('yayin') === 'draft' ? 'draft' : 'published';
        $itemId = Suggestions::review($s, $decisions, Request::post('yanit'), $editor, $status);
        $n = count(array_filter($decisions, static fn($d) => $d['ok']));
        flash($n ? "$n değişiklik onaylandı ve yayına girdi." : 'Öneri reddedildi.');
        $next = Db::val("SELECT id FROM suggestions WHERE status = 'pending' ORDER BY created_at LIMIT 1");
        Response::redirect(url('panel/oneriler', ['id' => $next ?: null]));
    }
}
