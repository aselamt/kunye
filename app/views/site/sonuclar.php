<?php
/** @var string $q @var array $terms @var array $cats @var array $vals @var array $cards @var int $total @var int $page @var int $pages @var array $favs */
$nothing = !$cats && !$vals && !$cards;
?>
<main class="wrap page">
  <nav class="crumbs" aria-label="Konum"><a href="<?= e(url()) ?>">Ana sayfa</a><span class="sep">/</span><span>Arama</span></nav>
  <div class="list-head">
    <h1 class="title"><?= $q === '' ? 'Arama' : '“' . e($q) . '” için sonuçlar' ?></h1>
    <?php if (!$nothing): ?><span class="count"><?= count($cats) ?> kategori · <?= count_fmt($total) ?> öğe · <?= count($vals) ?> değer</span><?php endif ?>
  </div>

  <?php if ($nothing): ?>
  <div class="empty">
    <?= icon('search') ?>
    <h2><?= $q === '' ? 'Ne aradığını yaz' : 'Sonuç bulunamadı' ?></h2>
    <p><?= $q === '' ? 'Bir ürün, kategori ya da özellik adı yazabilirsin.' : 'Yazımı kontrol et ya da daha genel bir ifade dene.' ?></p>
    <a class="btn btn--ghost" href="<?= e(url('kategoriler')) ?>">Kategorilere göz at</a>
  </div>
  <?php endif ?>

  <?php if ($cats && $page === 1): ?>
  <section class="rsec">
    <h2 class="rsec__h">Kategoriler</h2>
    <div class="cats">
      <?php foreach ($cats as $c): ?>
      <a class="catc" href="<?= e(url($c['slug'])) ?>"><span class="suggest__icon"><?= icon($c['icon']) ?></span><span class="catc__t"><span><?= Text::mark($c['name'], $terms) ?></span><small><?= e(trim(($c['section'] ?? '') . ' · ' . count_fmt((int) $c['item_count']) . ' öğe', ' ·')) ?></small></span></a>
      <?php endforeach ?>
    </div>
  </section>
  <?php endif ?>

  <?php if ($vals && $page === 1): ?>
  <section class="rsec">
    <h2 class="rsec__h">Özellik değerleri</h2>
    <div class="vals vals--lg">
      <?php foreach ($vals as $v): ?>
      <a class="vchip" href="<?= e(url($v['cslug'], [$v['pslug'] => $v['slug']])) ?>"><?= Text::mark($v['name'], $terms) ?> <small><?= e($v['cat'] . ' · ' . $v['prop'] . ' · ' . count_fmt((int) $v['n'])) ?></small></a>
      <?php endforeach ?>
    </div>
  </section>
  <?php endif ?>

  <?php if ($cards): ?>
  <section class="rsec">
    <h2 class="rsec__h">Öğeler <span style="text-transform:none;letter-spacing:0;font-weight:400"><?= count_fmt($total) ?></span></h2>
    <div class="rows">
      <?php foreach ($cards as $it): ?><?= View::partial('partials/row', ['it' => $it, 'favs' => $favs, 'terms' => $terms]) ?><?php endforeach ?>
    </div>
    <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Sayfalar">
      <?php for ($p = 1; $p <= $pages; $p++):
          if ($p !== 1 && $p !== $pages && abs($p - $page) > 2) {
              if ($p === 2 || $p === $pages - 1) echo '<span>…</span>';
              continue;
          } ?>
        <?php if ($p === $page): ?><span class="is-on"><?= $p ?></span><?php else: ?><a href="<?= e(url('ara', ['q' => $q, 'sayfa' => $p > 1 ? $p : null])) ?>"><?= $p ?></a><?php endif ?>
      <?php endfor ?>
    </nav>
    <?php endif ?>
  </section>
  <?php endif ?>
</main>
