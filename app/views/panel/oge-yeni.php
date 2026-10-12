<?php /** @var array $cats @var array $errors @var int $catId */ ?>
<?php foreach ($errors as $err): ?><div class="alert"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endforeach ?>
<form method="post" action="<?= e(url('panel/oge/yeni')) ?>">
  <?= csrf_field() ?>
  <section class="card">
    <div class="card__head"><h2>Yeni öğe</h2></div>
    <div class="card__body fields">
      <label class="field"><span class="lbl">Ad</span><span class="input"><input name="ad" value="<?= e(old('ad')) ?>" required maxlength="120" autofocus></span></label>
      <label class="field"><span class="lbl">Kategori</span><span class="input"><select name="kategori" required>
        <option value="">Seç…</option>
        <?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $catId === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?>
      </select></span><span class="hint">Öğe bu kategorinin şablonuyla başlar.</span></label>
    </div>
    <div class="card__foot"><span class="hint" style="padding:8px 16px">Öğe taslak olarak oluşturulur.</span><button class="btn btn--sm" type="submit">Oluştur ve devam et</button></div>
  </section>
</form>
