<?php
/** Liste satırı. @var array $it (Catalog::cards) @var array $favs @var array $hits (vurgulanacak özellik kimlikleri) @var array $terms */
$hits ??= [];
$terms ??= [];
$isFav = isset($favs[(int) $it['id']]);
$sub = [$it['category']['singular'] ?? ''];
foreach ($it['sub'] as $s) {
    // ada zaten dahil olan değerleri (ör. marka) tekrar etme
    if (!str_contains(Text::fold($it['name']), Text::fold($s))) {
        $sub[] = $s;
    }
}
?>
<article class="row">
  <div class="row__media"><?php if ($it['cover']): ?><?= Images::picture($it['cover'], '', '64px') ?><?php else: ?><span class="ph"><?= icon($it['category']['icon'] ?? 'box') ?></span><?php endif ?></div>
  <div>
    <h2 class="row__title"><a href="<?= e($it['url']) ?>"><?= $terms ? Text::mark($it['name'], $terms) : e($it['name']) ?></a></h2>
    <p class="row__sub"><?= e(implode(' · ', array_filter($sub))) ?></p>
    <?php if ($it['specs']): ?>
    <dl class="row__specs">
      <?php foreach ($it['specs'] as $s): ?><div<?= in_array($s['pid'], $hits, true) ? ' class="hit"' : '' ?>><dt><?= e($s['label']) ?></dt><dd><?= e($s['value']) ?></dd></div><?php endforeach ?>
    </dl>
    <?php endif ?>
  </div>
  <?= View::partial('partials/fav', ['id' => (int) $it['id'], 'on' => $isFav, 'cls' => 'icon-btn row__fav']) ?>
</article>
