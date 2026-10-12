<?php
declare(strict_types=1);

final class SiteController
{
    public static function home(): void
    {
        PageCache::allow();
        $cats = array_values(array_filter(Catalog::categories(), static fn($c) => (int) $c['item_count'] > 0));
        usort($cats, static fn($a, $b) => (int) $b['item_count'] <=> (int) $a['item_count']);
        View::send('site/home', [
            'home' => true, 'popular' => array_slice($cats, 0, 4), 'links' => array_slice($cats, 0, 5),
            'itemTotal' => array_sum(array_map(static fn($c) => (int) $c['item_count'], $cats)), 'catTotal' => count($cats),
        ]);
    }

    public static function categories(): void
    {
        PageCache::allow();
        $sections = [];
        foreach (Catalog::categories() as $c) {
            $sections[$c['section'] ?? 'Diğer'][] = $c;
        }
        View::send('site/kategoriler', ['title' => 'Kategoriler', 'sections' => $sections]);
    }

    private static function favs(): array
    {
        $uid = Auth::id();
        return $uid ? array_fill_keys(array_map('intval', Db::col('SELECT item_id FROM favorites WHERE user_id = ?', [$uid])), true) : [];
    }

    public static function listing(string $cat): void
    {
        $c = Catalog::categoryBySlug($cat) ?? throw new HttpError(404);
        $props = Catalog::propsBySlug((int) $c['id']);
        $canon = Facets::canonical($props, $_GET);
        if ($canon !== null) {
            Response::redirect(url($cat, $canon), 302);
        }
        $query = $_GET;
        $idx = Facets::index($c);
        $filters = Facets::parse($props, $query, $idx);
        $filterable = [];
        foreach (Catalog::template((int) $c['id'])['groups'] as $g) {
            foreach ($g['props'] as $p) {
                if ($p['filterable'] && $p['type'] !== 'text') {
                    $filterable[] = $p;
                }
            }
        }
        $sort = isset($query['sirala']) && isset(Facets::SORTS[$query['sirala']]) ? $query['sirala'] : 'yeni';
        $res = Facets::run($c, $idx, $filters, $filterable, $sort);
        $per = max(6, min(100, Settings::int('sayfa_basina')));
        $total = count($res['ids']);
        $pages = max(1, (int) ceil($total / $per));
        $page = max(1, min($pages, (int) ($query['sayfa'] ?? 1)));
        $cards = Catalog::cards(array_slice($res['ids'], ($page - 1) * $per, $per));

        $heading = $c['name'];
        $crumb = null;
        $active = array_filter($res['facets'], static fn($f) => $f['active']);
        if (count($active) === 1) {
            $f = reset($active);
            $heading = $f['prop']['name'] . ': ' . Facets::chip($f);
            $crumb = $f['prop']['name'];
        }
        if (count($active) <= 1 && $sort === 'yeni' && $page === 1) {
            PageCache::allow();
        }
        View::send('site/liste', [
            'title' => $heading === $c['name'] ? $c['name'] : $heading . ' · ' . $c['name'],
            'description' => $c['name'] . ': ' . count_fmt($total) . ' öğenin tüm özellikleri, filtrelenebilir liste.',
            'noindex' => count($active) > 1 || $page > 1,
            'canonical' => abs_url($cat),
            'cat' => $c, 'facets' => $res['facets'], 'cards' => $cards, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'query' => $query, 'sort' => $sort, 'favs' => self::favs(), 'heading' => $heading, 'crumb' => $crumb,
        ]);
    }

    public static function detail(string $cat, string $item): void
    {
        $c = Catalog::categoryBySlug($cat) ?? throw new HttpError(404);
        $it = Catalog::itemBySlug((int) $c['id'], $item) ?? throw new HttpError(404);
        $published = $it['status'] === 'published';
        if (!$published && !Auth::is('editor')) {
            throw new HttpError(404);
        }
        $id = (int) $it['id'];
        $tpl = Catalog::template((int) $c['id'], $id);
        $hidden = Catalog::hidden($id);
        $rows = Catalog::rows($id);
        $idx = Facets::index($c);
        $groups = [];
        $keyfacts = [];
        $eyebrow = [];
        foreach ($tpl['groups'] as $gid => $g) {
            $props = [];
            foreach ($g['props'] as $pid => $p) {
                if (isset($hidden[$pid]) || empty($rows[$pid])) {
                    continue;
                }
                $props[$pid] = $p;
                $value = Catalog::fromRows($p['type'], $rows[$pid]);
                if ($p['is_key']) {
                    $keyfacts[] = ['icon' => $p['icon'] ?: $g['icon'], 'label' => $p['label'], 'value' => Catalog::display($p, $value)];
                }
                if ($p['in_eyebrow'] && $p['type'] !== 'multi') {
                    // alt başlıktaki değerler (ör. marka, yıl) zaten bağlantı; gruplarda tekrar edilmez
                    $eyebrow[] = ['text' => Catalog::display($p, $value), 'url' => url($cat, Facets::valueQuery($p, $rows[$pid][0]))];
                    unset($props[$pid]);
                }
            }
            if ($props) {
                $g['props'] = $props;
                $groups[$gid] = $g;
            }
        }
        $isFav = Auth::id() !== null && (bool) Db::val('SELECT 1 FROM favorites WHERE user_id = ? AND item_id = ?', [Auth::id(), $id]);
        if ($published) {
            PageCache::allow();
        }
        $images = Catalog::images($id);
        View::send('site/detay', [
            'title' => $it['name'],
            'description' => $it['name'] . ' — ' . implode(', ', array_map(static fn($k) => $k['label'] . ': ' . $k['value'], $keyfacts)),
            'canonical' => abs_url("$cat/$item"),
            'noindex' => !$published,
            'cat' => $c, 'item' => $it, 'groups' => $groups, 'rows' => $rows, 'idx' => $idx, 'images' => $images,
            'keyfacts' => array_slice($keyfacts, 0, 4), 'eyebrow' => $eyebrow, 'isFav' => $isFav, 'published' => $published,
        ]);
    }

    public static function search(): void
    {
        $q = Text::clean(Request::get('q'), 100);
        $terms = Text::terms($q);
        $per = 20;
        $page = max(1, (int) Request::get('sayfa', '1'));
        [$ids, $total] = Search::items($terms, $per, ($page - 1) * $per);
        // tek bir kategori adıyla tam eşleşme: doğrudan listeye git
        if ($page === 1 && $terms) {
            foreach (Catalog::categories() as $c) {
                if (Text::fold($c['name']) === Text::fold($q) || Text::fold($c['singular']) === Text::fold($q)) {
                    Response::redirect(url($c['slug']), 302);
                }
            }
        }
        PageCache::allow();
        View::send('site/sonuclar', [
            'title' => $q === '' ? 'Arama' : "“{$q}” araması", 'noindex' => true, 'q' => $q, 'terms' => $terms,
            'cats' => Search::categories($terms, 6), 'vals' => Search::values($terms, 8),
            'cards' => Catalog::cards($ids), 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $per)),
            'favs' => self::favs(),
        ]);
    }

    public static function suggestApi(): void
    {
        header('Cache-Control: public, max-age=60');
        $q = Text::clean(Request::get('q'), 100);
        Response::json(Search::suggest($q));
    }

    /** Düzenleme kutuları için var olan seçenekler (kullanım sayısıyla) */
    public static function optionsApi(string $id): void
    {
        $p = Db::one('SELECT * FROM props WHERE id = ? AND archived = 0', [(int) $id]) ?? throw new HttpError(404);
        header('Cache-Control: public, max-age=120');
        $rows = Db::all("SELECT o.name, COUNT(v.item_id) AS n FROM options o LEFT JOIN vals v ON v.option_id = o.id
            WHERE o.prop_id = ? GROUP BY o.id ORDER BY n DESC, o.name LIMIT 500", [$p['id']]);
        Response::json(array_map(static fn($r) => ['ad' => $r['name'], 'n' => (int) $r['n']], $rows));
    }

    public static function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $b = Request::base();
        echo "User-agent: *\nDisallow: $b/panel\nDisallow: $b/ara\nDisallow: $b/api/\nDisallow: $b/giris\nDisallow: $b/kayit\nDisallow: $b/favoriler\n"
            . 'Sitemap: ' . abs_url('sitemap.xml') . "\n";
        exit;
    }

    public static function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        echo '<url><loc>' . e(abs_url()) . "</loc></url>\n";
        foreach (Catalog::categories() as $c) {
            echo '<url><loc>' . e(abs_url($c['slug'])) . "</loc></url>\n";
        }
        $st = Db::q("SELECT i.slug, i.updated_at, c.slug AS cslug FROM items i JOIN categories c ON c.id = i.category_id WHERE i.status = 'published' ORDER BY i.id LIMIT 45000");
        while ($r = $st->fetch()) {
            echo '<url><loc>' . e(abs_url($r['cslug'] . '/' . $r['slug'])) . '</loc><lastmod>' . date('Y-m-d', (int) $r['updated_at']) . "</lastmod></url>\n";
        }
        echo '</urlset>';
        exit;
    }

    public static function favicon(): void
    {
        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=604800');
        $l = e(initial(Settings::get('site_adi') ?: 'k'));
        echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#1b1b19"/>'
            . '<text x="29" y="44" font-family="system-ui,sans-serif" font-size="36" font-weight="700" fill="#fff" text-anchor="middle">' . $l . '</text>'
            . '<circle cx="47" cy="42" r="4.5" fill="#2453e0"/></svg>';
        exit;
    }
}
