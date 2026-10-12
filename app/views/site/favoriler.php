<?php /** @var array $cards @var array $tabs @var ?int $active @var array $favs @var int $all */ ?>
<main class="wrap page">
  <nav class="crumbs" aria-label="Konum"><a href="<?= e(url()) ?>">Ana sayfa</a><span class="sep">/</span><span>Favorilerim</span></nav>
  <div class="list-head">
    <h1 class="title">Favorilerim</h1>
    <span class="count"><?= count_fmt(count($cards)) ?> öğe</span>
  </div>
  <?php if ($tabs): ?>
  <nav class="tabs" aria-label="Kategori">
    <a<?= $active === null ? ' class="is-on"' : '' ?> href="<?= e(url('favoriler')) ?>">Tümü <span class="n"><?= $all ?></span></a>
    <?php foreach ($tabs as $t): ?><a<?= $active === (int) $t['id'] ? ' class="is-on"' : '' ?> href="<?= e(url('favoriler', ['kategori' => $t['slug']])) ?>"><?= e($t['name']) ?> <span class="n"><?= (int) $t['n'] ?></span></a><?php endforeach ?>
  </nav>
  <?php endif ?>
  <?php if ($cards): ?>
  <div class="rows">
    <?php foreach ($cards as $it): ?><?= View::partial('partials/row', ['it' => $it, 'favs' => $favs]) ?><?php endforeach ?>
  </div>
  <p class="pfoot" style="margin-top:28px;font-size:13px;color:var(--faint)">Yıldıza tıklayınca öğe listeden çıkar. Favoriler yalnızca sana görünür.</p>
  <?php else: ?>
  <div class="empty"><?= icon('star') ?><h2>Henüz favorin yok</h2><p>Beğendiğin öğelerdeki yıldıza tıklayarak buraya ekleyebilirsin.</p><a class="btn btn--ghost" href="<?= e(url('kategoriler')) ?>">Kategorilere göz at</a></div>
  <?php endif ?>
</main>
