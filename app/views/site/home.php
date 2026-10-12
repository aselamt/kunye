<?php /** @var array $popular @var array $links */ ?>
<main class="home__main">
  <div class="home__center">
    <?= View::partial('partials/search', ['small' => false, 'q' => '', 'popular' => $popular]) ?>
    <?php if ($links): ?>
    <nav class="home__cats" aria-label="Kategoriler">
      <?php foreach ($links as $c): ?><a href="<?= e(url($c['slug'])) ?>"><?= e($c['name']) ?></a><?php endforeach ?>
      <a href="<?= e(url('kategoriler')) ?>">Tüm kategoriler</a>
    </nav>
    <?php endif ?>
  </div>
</main>
