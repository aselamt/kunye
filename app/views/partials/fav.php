<?php
/** Favori düğmesi. Ziyaretçiye giriş bağlantısı (önbelleğe alınabilir), üyeye form. @var int $id @var bool $on @var string $cls @var bool $label */
$label ??= false;
$text = $on ? 'Favorilerden çıkar' : 'Favorilere ekle';
if (Auth::user() === null): ?>
<a class="<?= e($cls) ?>" href="<?= e(url('giris', ['donus' => Request::uri()])) ?>" aria-label="Favorilere ekle" title="Favorilere eklemek için giriş yap"><?= icon('star', $label ? 'icon icon--sm' : 'icon') ?><?= $label ? ' Favorilere ekle' : '' ?></a>
<?php else: ?>
<form method="post" action="<?= e(url('favori/' . $id)) ?>" class="fav-form" data-fav>
  <?= csrf_field() ?>
  <input type="hidden" name="donus" value="<?= e(Request::uri()) ?>">
  <button class="<?= e($cls . ($on ? ' is-fav' : '')) ?>" type="submit" aria-pressed="<?= $on ? 'true' : 'false' ?>" aria-label="<?= e($text) ?>" title="<?= e($text) ?>"><?= icon('star', $label ? 'icon icon--sm' : 'icon') ?><?php if ($label): ?> <span><?= e($text) ?></span><?php endif ?></button>
</form>
<?php endif ?>
