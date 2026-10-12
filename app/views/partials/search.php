<?php
/** @var bool $small @var string $q @var array $popular */
$small = $small ?? false;
?>
<form class="search<?= $small ? ' search--sm' : '' ?>" role="search" action="<?= e(url('ara')) ?>" data-suggest="<?= e(url('api/oneri')) ?>">
  <label class="search__box">
    <?= icon('search', $small ? 'icon icon--sm' : 'icon') ?>
    <input class="search__input" type="search" name="q" value="<?= e($q ?? '') ?>"
      placeholder="<?= e($small ? 'Ara' : Settings::get('arama_metni')) ?>" autocomplete="off" aria-label="Ara"
      role="combobox" aria-expanded="false" aria-autocomplete="list">
    <?php if (!$small): ?><kbd>/</kbd><?php endif ?>
  </label>
  <div class="suggest" role="listbox"><?php if (!empty($popular)): ?>
    <div class="suggest__label">Popüler kategoriler</div>
    <?php foreach ($popular as $c): ?>
    <a class="suggest__item" href="<?= e(url($c['slug'])) ?>">
      <span class="suggest__icon"><?= icon($c['icon']) ?></span>
      <span class="suggest__text"><span><?= e($c['name']) ?></span><?php if ($c['section']): ?><small><?= e($c['section']) ?></small><?php endif ?></span>
      <span class="suggest__meta"><?= count_fmt((int) $c['item_count']) ?> öğe</span>
    </a>
    <?php endforeach ?>
  <?php endif ?></div>
</form>
