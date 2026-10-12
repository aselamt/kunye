<?php /** @var array $sections [ad => [kategori…]] */ ?>
<main class="wrap page">
  <nav class="crumbs" aria-label="Konum"><a href="<?= e(url()) ?>">Ana sayfa</a><span class="sep">/</span><span>Kategoriler</span></nav>
  <div class="list-head"><h1 class="title">Tüm kategoriler</h1></div>
  <?php if (!$sections): ?>
  <div class="empty"><?= icon('grid') ?><h2>Henüz kategori yok</h2><p>Yönetici panelinden ilk kategoriyi oluşturabilirsin.</p></div>
  <?php endif ?>
  <div class="secs">
    <?php foreach ($sections as $name => $cats): ?>
    <section class="rsec">
      <h2 class="rsec__h"><?= e($name) ?></h2>
      <div class="cats">
        <?php foreach ($cats as $c): ?>
        <a class="catc" href="<?= e(url($c['slug'])) ?>"><span class="suggest__icon"><?= icon($c['icon']) ?></span><span class="catc__t"><span><?= e($c['name']) ?></span><small><?= count_fmt((int) $c['item_count']) ?> öğe</small></span></a>
        <?php endforeach ?>
      </div>
    </section>
    <?php endforeach ?>
  </div>
</main>
