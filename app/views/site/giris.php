<?php /** @var string $mode giris|kayit @var array $errors @var string $return @var bool $open */
$reg = $mode === 'kayit';
?>
<form class="auth__card" method="post" action="<?= e(url($reg ? 'kayit' : 'giris', ['donus' => $return ?: null])) ?>" novalidate>
  <?php if ($open): ?>
  <div class="seg" role="tablist">
    <label><input type="radio" name="_m" <?= !$reg ? 'checked' : '' ?> data-href="<?= e(url('giris', ['donus' => $return ?: null])) ?>"><span><a href="<?= e(url('giris', ['donus' => $return ?: null])) ?>">Giriş yap</a></span></label>
    <label><input type="radio" name="_m" <?= $reg ? 'checked' : '' ?> data-href="<?= e(url('kayit', ['donus' => $return ?: null])) ?>"><span><a href="<?= e(url('kayit', ['donus' => $return ?: null])) ?>">Kaydol</a></span></label>
  </div>
  <?php endif ?>
  <h1><?= $reg ? 'Hesap oluştur' : 'Tekrar hoş geldin' ?></h1>
  <p class="lead"><?= $reg ? 'Favori ekleyip düzeltme ve yeni öğe önerebilirsin.' : 'Favorilerin ve önerilerin hesabında saklanır.' ?></p>

  <?php foreach ($errors as $err): ?><div class="alert" role="alert"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endforeach ?>

  <?= csrf_field() ?>
  <?php if ($reg): ?>
  <label class="field"><span class="lbl">Kullanıcı adı</span>
    <span class="input"><input name="kullanici" autocomplete="username" value="<?= e(old('kullanici')) ?>" required minlength="3" maxlength="32"></span>
    <span class="hint">Harf, rakam, nokta, tire ve alt çizgi</span></label>
  <label class="field"><span class="lbl">E-posta <small>(isteğe bağlı)</small></span>
    <span class="input"><input type="email" name="eposta" autocomplete="email" value="<?= e(old('eposta')) ?>"></span></label>
  <label class="field"><span class="lbl">Şifre</span>
    <span class="input"><input type="password" name="sifre" autocomplete="new-password" required minlength="8"></span>
    <span class="hint">En az 8 karakter</span></label>
  <?php else: ?>
  <label class="field"><span class="lbl">E-posta ya da kullanıcı adı</span>
    <span class="input"><input name="kim" autocomplete="username" value="<?= e(old('kim')) ?>" required autofocus></span></label>
  <label class="field"><span class="lbl">Şifre</span>
    <span class="input"><input type="password" name="sifre" autocomplete="current-password" required></span></label>
  <label class="check" style="margin-top:12px"><input type="checkbox" name="hatirla" value="1"> Beni hatırla <span class="n"></span></label>
  <?php endif ?>
  <input class="hp" name="web" tabindex="-1" autocomplete="off" aria-hidden="true">
  <button class="btn btn--block" type="submit"><?= $reg ? 'Kaydol' : 'Giriş yap' ?></button>
  <?php if ($open): ?>
  <p class="auth__alt"><?= $reg ? 'Zaten hesabın var mı?' : 'Hesabın yok mu?' ?> <a href="<?= e(url($reg ? 'giris' : 'kayit', ['donus' => $return ?: null])) ?>"><?= $reg ? 'Giriş yap' : 'Kaydol' ?></a></p>
  <?php endif ?>
  <?php if (!$reg): ?><p class="auth__alt">Şifreni unuttuysan bir yöneticiden sıfırlama bağlantısı iste.</p><?php endif ?>
</form>
