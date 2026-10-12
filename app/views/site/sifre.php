<?php /** @var array $user @var array $errors @var bool $done */ ?>
<form class="auth__card" method="post" novalidate>
  <h1>Yeni şifre</h1>
  <p class="lead"><b><?= e($user['username']) ?></b> hesabı için yeni bir şifre belirle.</p>
  <?php foreach ($errors as $err): ?><div class="alert" role="alert"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endforeach ?>
  <?= csrf_field() ?>
  <label class="field"><span class="lbl">Yeni şifre</span><span class="input"><input type="password" name="sifre" autocomplete="new-password" required minlength="8"></span><span class="hint">En az 8 karakter</span></label>
  <label class="field"><span class="lbl">Yeni şifre (tekrar)</span><span class="input"><input type="password" name="sifre2" autocomplete="new-password" required></span></label>
  <button class="btn btn--block" type="submit">Şifreyi kaydet</button>
</form>
