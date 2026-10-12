<?php
/** @var string $content */
$site = Installer::installed() ? Settings::get('site_adi') : 'künye';
?><!doctype html>
<html lang="tr" class="no-js">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e(($title ?? '') !== '' ? $title . ' · ' . $site : $site) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/kunye.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/izgara.css')) ?>">
  <script src="<?= e(asset('js/js.js')) ?>"></script>
  <script src="<?= e(asset('js/kunye.js')) ?>" defer></script>
</head>
<body class="auth" data-base="<?= e(Request::base()) ?>">
<header class="auth__top"><a class="logo" href="<?= e(url()) ?>"><?= e($site) ?><span>.</span></a></header>
<main class="auth__main">
<?= $content ?>
</main>
</body>
</html>
