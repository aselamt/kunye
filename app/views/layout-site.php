<?php
/** @var string $content */
$site = Settings::get('site_adi');
$user = Auth::user();
$home = $home ?? false;
$pageTitle = isset($title) && $title !== '' ? $title . ' · ' . $site : $site;
$desc = $description ?? Settings::get('aciklama');
?><!doctype html>
<html lang="tr" class="no-js">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($desc) ?>">
  <?php if (!empty($noindex)): ?><meta name="robots" content="noindex"><?php endif ?>
  <?php if (!empty($canonical)): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif ?>
  <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#121211" media="(prefers-color-scheme: dark)">
  <link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset('css/kunye.css')) ?>">
  <?php if (!empty($panelCss)): ?><link rel="stylesheet" href="<?= e(asset('css/panel.css')) ?>"><?php endif ?>
  <script src="<?= e(asset('js/js.js')) ?>"></script>
  <script src="<?= e(asset('js/kunye.js')) ?>" defer></script>
</head>
<body class="<?= e(trim(($home ? 'home ' : '') . ($bodyClass ?? ''))) ?>" data-base="<?= e(Request::base()) ?>" data-sprite="<?= e(asset('ikonlar.svg')) ?>">
<?php if ($home): ?>
<header class="home__top">
  <a class="logo" href="<?= e(url()) ?>"><?= e($site) ?><span>.</span></a>
  <?php $links = Settings::links(); if ($links): ?>
  <nav class="home__links hide-sm">
    <?php foreach ($links as $l): ?><a href="<?= e($l['href']) ?>"<?= $l['ext'] ? ' rel="noopener" target="_blank"' : '' ?>><?= e($l['label']) ?></a><?php endforeach ?>
  </nav>
  <?php endif ?>
  <?= View::partial('partials/user-nav', ['user' => $user, 'home' => true]) ?>
</header>
<?php else: ?>
<header class="topbar">
  <div class="wrap topbar__in">
    <a class="logo" href="<?= e(url()) ?>"><?= e($site) ?><span>.</span></a>
    <?= View::partial('partials/search', ['small' => true, 'q' => $q ?? '']) ?>
    <nav class="topbar__nav">
      <?= View::partial('partials/user-nav', ['user' => $user, 'home' => false]) ?>
    </nav>
  </div>
</header>
<?php endif ?>
<?= $content ?>
<?php if (!$home): ?>
<footer class="foot">
  <div class="wrap">
    <span><?= e($site) ?></span>
    <a href="<?= e(url('kategoriler')) ?>">Tüm kategoriler</a>
    <?php foreach (Settings::links() as $l): ?><a href="<?= e($l['href']) ?>"<?= $l['ext'] ? ' rel="noopener" target="_blank"' : '' ?>><?= e($l['label']) ?></a><?php endforeach ?>
  </div>
</footer>
<?php endif ?>
</body>
</html>
