<?php
/** @var string $content @var string $nav @var string $crumbs (HTML) @var string $actions (HTML) */
$user = Auth::user();
$pending = Suggestions::pendingCount();
$items = [
    ['panel/ogeler', 'box', 'Öğeler', 'editor', 'ogeler'],
    ['panel/kategoriler', 'grid', 'Kategoriler', 'admin', 'kategoriler'],
    ['panel/oneriler', 'inbox', 'Öneriler', 'editor', 'oneriler'],
    ['panel/degerler', 'tag', 'Değerler', 'editor', 'degerler'],
    null,
    ['panel/kullanicilar', 'users', 'Kullanıcılar', 'admin', 'kullanicilar'],
    ['panel/ayarlar', 'sliders', 'Ayarlar', 'admin', 'ayarlar'],
];
$f = flash();
?><!doctype html>
<html lang="tr" class="no-js">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e(($title ?? 'Panel') . ' · ' . Settings::get('site_adi')) ?></title>
  <link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset('css/kunye.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/panel.css')) ?>">
  <script src="<?= e(asset('js/js.js')) ?>"></script>
  <script src="<?= e(asset('js/kunye.js')) ?>" defer></script>
  <script src="<?= e(asset('js/panel.js')) ?>" defer></script>
</head>
<body data-base="<?= e(Request::base()) ?>" data-sprite="<?= e(asset('ikonlar.svg')) ?>" data-csrf="<?= e(Csrf::token()) ?>">
<div class="panel">
  <aside class="pside">
    <a class="pside__brand" href="<?= e(url()) ?>"><span class="logo"><?= e(Settings::get('site_adi')) ?><span>.</span></span><small>panel</small></a>
    <nav class="pnav">
      <?php foreach ($items as $it):
          if ($it === null) {
              if (Auth::is('admin')) echo '<div class="pnav__sep"></div>';
              continue;
          }
          [$href, $ic, $label, $role, $key] = $it;
          if (!Auth::is($role)) continue; ?>
      <a<?= ($nav ?? '') === $key ? ' class="is-on"' : '' ?> href="<?= e(url($href)) ?>"><?= icon($ic) ?><?= e($label) ?><?php if ($key === 'oneriler' && $pending): ?> <span class="n"><?= $pending ?></span><?php endif ?></a>
      <?php endforeach ?>
    </nav>
    <div class="pside__user">
      <span class="avatar"><?= e(initial($user['username'])) ?></span>
      <div><?= e($user['username']) ?><small><?= e(Auth::ROLES[$user['role']]) ?></small></div>
      <form method="post" action="<?= e(url('cikis')) ?>" style="margin-left:auto"><?= csrf_field() ?><button class="icon-btn icon-btn--sm" title="Çıkış yap" aria-label="Çıkış yap"><?= icon('logout') ?></button></form>
    </div>
  </aside>

  <div class="pmain">
    <header class="phead">
      <nav class="crumbs" aria-label="Konum"><?= $crumbs ?? '' ?></nav>
      <div class="phead__actions"><?= $actions ?? '' ?></div>
    </header>
    <main class="pbody<?= !empty($wide) ? ' pbody--wide' : '' ?>">
      <?php if ($f): ?><div class="flash<?= $f['t'] === 'bad' ? ' flash--bad' : '' ?>" role="status"><?= icon($f['t'] === 'bad' ? 'alert' : 'check') ?><span><?= nl2br(e($f['m'])) ?></span></div><?php endif ?>
<?= $content ?>
    </main>
  </div>
</div>
</body>
</html>
