<?php
declare(strict_types=1);

/** Kullanıcının düzeltme ve yeni öğe önerileri */
final class ProposeController
{
    public static function edit(string $cat, string $item): void
    {
        $user = Auth::require();
        $c = Catalog::categoryBySlug($cat) ?? throw new HttpError(404);
        $it = Catalog::itemBySlug((int) $c['id'], $item) ?? throw new HttpError(404);
        if ($it['status'] !== 'published' && !Auth::is('editor')) {
            throw new HttpError(404);
        }
        self::form($user, $c, $it);
    }

    public static function create(string $cat): void
    {
        $user = Auth::require();
        $c = Catalog::categoryBySlug($cat) ?? throw new HttpError(404);
        if (!Settings::bool('yeni_oge_onerisi') && !Auth::is('editor')) {
            throw new HttpError(403, 'Yeni öğe önerileri şu an kapalı.');
        }
        self::form($user, $c, null);
    }

    private static function form(array $user, array $c, ?array $it): void
    {
        $id = $it ? (int) $it['id'] : null;
        $tpl = Catalog::template((int) $c['id'], $id);
        $hidden = $id ? Catalog::hidden($id) : [];
        $props = array_diff_key($tpl['props'], $hidden);
        $groups = [];
        foreach ($tpl['groups'] as $gid => $g) {
            $g['props'] = array_diff_key($g['props'], $hidden);
            if ($g['props']) {
                $groups[$gid] = $g;
            }
        }
        $values = $id ? Catalog::values($id, $props) : [];
        $errors = [];
        $post = [];
        if (Request::isPost()) {
            $post = ['v' => is_array($_POST['v'] ?? null) ? $_POST['v'] : [], 'ad' => Request::post('ad'), 'kaynak' => Request::post('kaynak'), 'not' => Request::post('not')];
            try {
                $submitted = array_intersect_key($post['v'], $props);
                Suggestions::create($user, $c, $it, $props, $submitted, $post['ad'], $post['not'], $post['kaynak']);
                flash($it ? 'Teşekkürler! Önerin editörlere iletildi.' : 'Teşekkürler! Yeni öğe önerin editörlere iletildi.');
                Response::redirect($it ? Catalog::itemUrl($it) : url($c['slug']));
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }
        $sub = '';
        $cover = null;
        if ($it) {
            $card = Catalog::cards([$id])[$id] ?? null;
            $cover = $card['cover'] ?? null;
            $sub = implode(' · ', array_merge([$c['singular']], $card['sub'] ?? []));
        }
        View::send('site/duzelt', [
            'title' => $it ? $it['name'] . ' · Düzeltme öner' : $c['name'] . ' · Yeni öğe öner', 'noindex' => true,
            'cat' => $c, 'item' => $it, 'groups' => $groups, 'values' => $values, 'errors' => $errors, 'post' => $post,
            'cover' => $cover, 'sub' => $sub,
        ], 'site', $errors ? 422 : 200);
    }
}
