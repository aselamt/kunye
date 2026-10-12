<?php /** @var array $popular @var array $links @var int $itemTotal @var int $catTotal */
// başlıkta *yıldızlar arasındaki* ifade vurgulanır
$heading = preg_replace('/\*(.+?)\*/u', '<em>$1</em>', e(Settings::get('ana_baslik')));
?>
<main class="home__main">
  <div class="home__center">
    <div class="home__hero">
      <?php if ($itemTotal): ?><a class="pill" href="<?= e(url('kategoriler')) ?>"><?= count_fmt($itemTotal) ?> öğe · <?= $catTotal ?> kategori <span class="pill__go" aria-hidden="true">›</span></a><?php endif ?>
      <?php if (trim(Settings::get('ana_baslik')) !== ''): ?><h1><?= $heading ?></h1><?php endif ?>
    </div>
    <?= View::partial('partials/search', ['small' => false, 'q' => '', 'popular' => $popular]) ?>
    <?php if ($links): ?>
    <nav class="home__cats" aria-label="Kategoriler">
      <?php foreach ($links as $c): ?><a href="<?= e(url($c['slug'])) ?>"><?= e($c['name']) ?></a><?php endforeach ?>
      <a href="<?= e(url('kategoriler')) ?>">Tüm kategoriler</a>
    </nav>
    <?php endif ?>
  </div>
</main>
