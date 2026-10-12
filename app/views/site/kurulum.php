<?php /** @var array $checks @var array $errors @var bool $ready */
$icons = ['ok' => 'yes', 'bad' => 'no', 'wn' => 'alert'];
$group = '';
?>
<form class="auth__card auth__card--wide" method="post" action="<?= e(url('kurulum')) ?>" novalidate>
  <ol class="steps"><li class="<?= $ready ? 'is-done' : 'is-on' ?>"><b><?= $ready ? '✓' : '1' ?></b>Sistem</li><li class="<?= $ready ? 'is-on' : '' ?>"><b>2</b>Site ve yönetici</li><li><b>3</b>Bitti</li></ol>
  <h1>Kurulum</h1>
  <p class="lead"><?= $ready ? 'Sunucun hazır. Site adını ver ve ilk yönetici hesabını oluştur.' : 'Önce kırmızı işaretli gereksinimleri gidermelisin; sonra bu sayfayı yenile.' ?></p>

  <?php foreach ($errors as $err): ?><div class="alert" role="alert"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endforeach ?>

  <ul class="checks">
    <?php foreach ($checks as [$g, $name, $sub, $st, $val]): ?>
      <?php if ($g !== $group): $group = $g; ?><li class="checks__h" style="display:block"><?= e($g) ?></li><?php endif ?>
      <li><?= icon($icons[$st], 'icon ' . $st) ?><span><?= $name /* güvenli: sabit metin */ ?><?php if ($sub !== ''): ?><small><?= e($sub) ?></small><?php endif ?></span><span class="checks__v"><?= e($val) ?></span></li>
    <?php endforeach ?>
  </ul>

  <?php if ($ready): ?>
  <?= csrf_field() ?>
  <div class="fields" style="margin-top:22px">
    <label class="field field--full"><span class="lbl">Site adı</span><span class="input"><input name="site" value="<?= e(old('site', 'künye')) ?>" required maxlength="60"></span></label>
    <label class="field"><span class="lbl">Yönetici kullanıcı adı</span><span class="input"><input name="kullanici" value="<?= e(old('kullanici', 'yonetici')) ?>" autocomplete="username" required></span></label>
    <label class="field"><span class="lbl">E-posta (isteğe bağlı)</span><span class="input"><input type="email" name="eposta" value="<?= e(old('eposta')) ?>" autocomplete="email"></span></label>
    <label class="field"><span class="lbl">Şifre</span><span class="input"><input type="password" name="sifre" autocomplete="new-password" required minlength="10"></span><span class="hint">En az 10 karakter</span></label>
    <label class="field"><span class="lbl">Şifre (tekrar)</span><span class="input"><input type="password" name="sifre2" autocomplete="new-password" required></span></label>
    <label class="check field--full"><input type="checkbox" name="ornek" value="1"<?= !$_POST || !empty($_POST['ornek']) ? ' checked' : '' ?>> Örnek veriyi yükle (Telefonlar ve Akıllı Saatler · 38 öğe) <span class="n"></span></label>
  </div>
  <button class="btn btn--block" type="submit"><?= icon('lock', 'icon icon--sm') ?> Kur ve kilitle</button>
  <p class="auth__alt">Kurulum bitince <code>data/kurulum.kilit</code> oluşur ve bu ekran kapanır.</p>
  <?php else: ?>
  <a class="btn btn--block" href="<?= e(url('kurulum')) ?>">Yeniden denetle</a>
  <?php endif ?>
</form>
