<?php
/** @var ?array $user @var bool $home */
$return = Request::path() === '' ? null : Request::uri();
?>
<a class="icon-btn" href="<?= e(url('favoriler')) ?>" title="Favorilerim" aria-label="Favorilerim"><?= icon('star') ?></a>
<?php if ($user === null): ?>
  <a class="btn btn--ghost<?= $home ? '' : ' hide-sm' ?>" href="<?= e(url('giris', ['donus' => $return])) ?>">Giriş yap</a>
  <?php if (!$home): ?><a class="icon-btn show-sm" href="<?= e(url('giris', ['donus' => $return])) ?>" title="Giriş yap" aria-label="Giriş yap"><?= icon('user') ?></a><?php endif ?>
<?php else: ?>
  <details class="umenu">
    <summary class="avatar" title="<?= e($user['username']) ?>" aria-label="Hesap menüsü"><?= e(initial($user['username'])) ?></summary>
    <div class="umenu__list">
      <div class="umenu__who"><b><?= e($user['username']) ?></b><?= e(Auth::ROLES[$user['role']]) ?></div>
      <?php if (Auth::is('editor')): ?><a href="<?= e(url('panel')) ?>"><?= icon('grid') ?>Panel</a><?php endif ?>
      <a href="<?= e(url('favoriler')) ?>"><?= icon('star') ?>Favorilerim</a>
      <form method="post" action="<?= e(url('cikis')) ?>">
        <?= csrf_field() ?>
        <button type="submit"><?= icon('logout') ?>Çıkış yap</button>
      </form>
    </div>
  </details>
<?php endif ?>
