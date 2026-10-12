<?php /** @var array $errors @var array $sections */ ?>
<?php foreach ($errors as $err): ?><div class="alert"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endforeach ?>
<form method="post" action="<?= e(url('panel/kategori/yeni')) ?>">
  <?= csrf_field() ?>
  <?= View::partial('panel/kategori-alanlar', ['cat' => null, 'sections' => $sections, 'prefix' => '']) ?>
  <div class="danger"><span class="hint">Kategori “Genel” adlı boş bir grupla oluşturulur; özellikleri sonraki adımda eklersin.</span><button class="btn btn--sm" type="submit">Oluştur</button></div>
</form>
