<?php
/**
 * @var array $cat @var array $facets @var array $cards @var int $total @var int $page @var int $pages
 * @var array $query @var string $sort @var array $favs @var string $heading @var ?array $crumb
 */
$slug = $cat['slug'];
$activeCount = count(array_filter($facets, static fn($f) => $f['active']));
$without = static function (string $key) use ($query): array {
    $q = $query;
    unset($q[$key], $q['sayfa']);
    return $q;
};
$hits = array_map(static fn($f) => $f['prop']['id'], array_filter($facets, static fn($f) => $f['active']));
?>
<main class="wrap page" id="sonuc">
  <nav class="crumbs" aria-label="Konum">
    <a href="<?= e(url()) ?>">Ana sayfa</a><span class="sep">/</span>
    <a href="<?= e(url($slug)) ?>"><?= e($cat['name']) ?></a>
    <?php if ($crumb): ?><span class="sep">/</span><span><?= e($crumb) ?></span><?php endif ?>
  </nav>
  <div class="list-head">
    <h1 class="title"><?= e($heading) ?></h1>
    <span class="count<?= $activeCount ? ' count--filtered' : '' ?>"><?= count_fmt($total) ?> öğe</span>
  </div>

  <input type="checkbox" id="ff" class="sr-only">
  <div class="mbar">
    <label for="ff" class="btn btn--ghost"><?= icon('sliders', 'icon icon--sm') ?> Filtreler<?php if ($activeCount): ?> <span class="f__badge"><?= $activeCount ?></span><?php endif ?></label>
  </div>

  <div class="layout">
    <aside class="filters" aria-label="Filtreler">
      <form id="filtre" method="get" action="<?= e(url($slug)) ?>" data-filters>
        <label class="fsearch js-only">
          <?= icon('search', 'icon icon--sm') ?>
          <input type="search" placeholder="Özellik ara…" aria-label="Özellik ara" data-fsearch>
        </label>
        <?php
        $i = 0;
        $restOpen = false;
        foreach ($facets as $pid => $f):
            $p = $f['prop'];
            if ($i === 10 && count($facets) > 12):
                $restOpen = true; ?>
        <details class="f-rest"><summary class="filters__more">+ <?= count($facets) - 10 ?> özellik daha</summary>
            <?php endif;
            $open = $f['active'] || $i < 6;
            $i++;
            ?>
        <details class="f" data-f="<?= e($p['name']) ?>"<?= $open ? ' open' : '' ?>>
          <summary><?= e($p['name']) ?><?php if ($p['unit'] !== '' && $p['type'] === 'number'): ?> <span class="f__unit"><?= e($p['unit']) ?></span><?php endif ?>
            <?php if ($f['active']): ?><span class="f__badge"><?= $p['type'] === 'single' || $p['type'] === 'multi' ? count(array_filter($f['options'], static fn($o) => $o['on'])) : 1 ?></span><?php endif ?></summary>
          <div class="f__body">
          <?php if ($p['type'] === 'number'):
              echo View::partial('partials/range', ['f' => $f]);
          elseif ($p['type'] === 'bool'): ?>
            <div class="seg">
              <label><input type="radio" name="<?= e($p['slug']) ?>" value=""<?= !$f['active'] ? ' checked' : '' ?>><span>Fark etmez</span></label>
              <label><input type="radio" name="<?= e($p['slug']) ?>" value="var"<?= $f['active'] && $f['filter']['val'] ? ' checked' : '' ?>><span>Var <small><?= $f['yes'] ?></small></span></label>
              <label><input type="radio" name="<?= e($p['slug']) ?>" value="yok"<?= $f['active'] && !$f['filter']['val'] ? ' checked' : '' ?>><span>Yok <small><?= $f['no'] ?></small></span></label>
            </div>
          <?php elseif ($p['type'] === 'text'): ?>
            <div class="range__inputs"><label><input name="<?= e($p['slug']) ?>" value="<?= e($f['filter']['val']) ?>" aria-label="<?= e($p['name']) ?>"></label></div>
          <?php else:
              $opts = $f['options'];
              $first = array_slice($opts, 0, 6);
              $rest = array_slice($opts, 6);
              if (count($opts) > 8): ?>
            <input class="f__search js-only" type="search" placeholder="<?= e($p['name']) ?> ara…" aria-label="<?= e($p['name']) ?> ara" data-osearch>
              <?php endif;
              foreach ($first as $o): ?>
            <label class="check"><input type="checkbox" name="<?= e($p['slug']) ?>[]" value="<?= e($o['slug']) ?>"<?= $o['on'] ? ' checked' : '' ?>> <?= e($o['name']) ?> <span class="n"><?= count_fmt($o['n']) ?></span></label>
              <?php endforeach;
              if ($rest): ?>
            <details class="f__opts-more"><summary class="more">Tümünü göster (<?= count($opts) ?>)</summary>
              <?php foreach ($rest as $o): ?>
              <label class="check"><input type="checkbox" name="<?= e($p['slug']) ?>[]" value="<?= e($o['slug']) ?>"<?= $o['on'] ? ' checked' : '' ?>> <?= e($o['name']) ?> <span class="n"><?= count_fmt($o['n']) ?></span></label>
              <?php endforeach ?>
            </details>
              <?php endif;
          endif ?>
          </div>
        </details>
        <?php endforeach;
        if ($restOpen): ?></details><?php endif ?>
        <?php if ($sort !== 'yeni'): ?><input type="hidden" name="sirala" value="<?= e($sort) ?>"><?php endif ?>
        <div class="filters__apply no-js-only"><button class="btn" type="submit">Uygula</button></div>
      </form>
    </aside>

    <section aria-label="Sonuçlar" class="results">
      <div class="toolbar">
        <?php foreach ($facets as $f): if (!$f['active']) continue; ?>
        <span class="chip"><?= e($f['prop']['label']) ?>: <?= e(Facets::chip($f)) ?>
          <a class="chip__x" href="<?= e(url($slug, $without($f['prop']['slug']))) ?>" aria-label="Filtreyi kaldır"><?= icon('close', 'icon icon--sm') ?></a>
        </span>
        <?php endforeach ?>
        <?php if ($activeCount): ?><a class="clear" href="<?= e(url($slug, $sort !== 'yeni' ? ['sirala' => $sort] : [])) ?>">Temizle</a><?php endif ?>
        <label class="sort">Sırala:
          <select name="sirala" form="filtre" data-sort>
            <?php foreach (Facets::SORTS as $k => $label): ?><option value="<?= e($k) ?>"<?= $k === $sort ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
          </select>
          <button class="btn btn--ghost btn--sm no-js-only" type="submit" form="filtre">Sırala</button>
        </label>
      </div>

      <?php if ($cards): ?>
      <div class="rows" data-rows>
        <?php foreach ($cards as $it): ?><?= View::partial('partials/row', ['it' => $it, 'favs' => $favs, 'hits' => $hits]) ?><?php endforeach ?>
      </div>
      <?php if ($pages > 1): ?>
      <nav class="pager" aria-label="Sayfalar">
        <?php if ($page > 1): ?><a href="<?= e(url($slug, ['sayfa' => $page > 2 ? $page - 1 : null] + $query)) ?>" rel="prev" aria-label="Önceki sayfa">←</a><?php endif ?>
        <span class="is-on"><?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?><a href="<?= e(url($slug, ['sayfa' => $page + 1] + $query)) ?>" rel="next" data-more>Daha fazla göster →</a><?php endif ?>
      </nav>
      <?php endif ?>
      <?php else: ?>
      <div class="empty">
        <?= icon('search') ?>
        <h2>Sonuç yok</h2>
        <p>Bu filtrelerle eşleşen öğe bulunamadı.</p>
        <a class="btn btn--ghost" href="<?= e(url($slug)) ?>">Filtreleri temizle</a>
      </div>
      <?php endif ?>
    </section>
  </div>
</main>
