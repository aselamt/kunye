<?php /** @var array $s @var int $cacheFiles @var int $cacheBytes @var int $dbBytes */
$mb = static fn(int $b) => number_format($b / 1048576, 1, ',', '.') . ' MB';
$sw = static fn(string $k, string $label) => '<input type="hidden" name="' . $k . '" value="0"><input class="switch" type="checkbox" name="' . $k . '" value="1"' . ($s[$k] === '1' ? ' checked' : '') . ' aria-label="' . e($label) . '">';
$engine = Images::engine();
?>
<form id="ayarlar" method="post" action="<?= e(url('panel/ayarlar')) ?>">
  <?= csrf_field() ?>
  <section class="card">
    <div class="card__head"><h2>Site</h2></div>
    <div class="card__body fields">
      <label class="field"><span class="lbl">Site adı</span><span class="input"><input name="site_adi" value="<?= e($s['site_adi']) ?>" maxlength="60"></span></label>
      <label class="field"><span class="lbl">Arama kutusu metni</span><span class="input"><input name="arama_metni" value="<?= e($s['arama_metni']) ?>" maxlength="80"></span></label>
      <label class="field field--full"><span class="lbl">Açıklama (arama motorları için)</span><span class="input"><input name="aciklama" value="<?= e($s['aciklama']) ?>" maxlength="300"></span></label>
      <label class="field"><span class="lbl">Sayfa başına öğe</span><span class="input"><input name="sayfa_basina" value="<?= e($s['sayfa_basina']) ?>" inputmode="numeric"></span></label>
      <label class="field"><span class="lbl">Üst menü bağlantıları</span><span class="input"><input name="ust_baglantilar" value="<?= e($s['ust_baglantilar']) ?>" placeholder="Hakkında=/hakkinda, GitHub=https://github.com/…"></span><span class="hint">Ad=adres, virgülle ayır</span></label>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h2>Üyelik ve öneriler</h2></div>
    <div class="card__body">
      <div class="kv"><span>Açık kayıt<small>Herkes kullanıcı hesabı açabilir</small></span><?= $sw('acik_kayit', 'Açık kayıt') ?></div>
      <div class="kv"><span>Yeni öğe önerileri<small>Kullanıcılar katalogda olmayan öğeleri önerebilir</small></span><?= $sw('yeni_oge_onerisi', 'Yeni öğe önerileri') ?></div>
      <div class="kv"><span>Kaynak bağlantısı zorunlu<small>Düzeltme önerisi gönderirken</small></span><?= $sw('kaynak_zorunlu', 'Kaynak zorunlu') ?></div>
      <div class="kv"><span>Günlük öneri sınırı<small>Kullanıcı başına, 24 saatte</small></span><span class="input input--sm" style="width:90px"><input name="gunluk_oneri" value="<?= e($s['gunluk_oneri']) ?>" inputmode="numeric" aria-label="Günlük öneri sınırı"></span></div>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h2>Görseller</h2><span class="meta"><?= $engine === 'imagick' ? 'Imagick' : ($engine === 'gd' ? 'Imagick yok · GD' : 'Görsel eklentisi yok') ?></span></div>
    <div class="card__body">
      <div class="kv"><span>Üretilen genişlikler<small>Gösterim en fazla 800 px; özgünden büyük boy üretilmez</small></span><span class="input input--sm" style="width:180px"><input name="gorsel_genislik" value="<?= e(str_replace(',', ', ', $s['gorsel_genislik'])) ?>" aria-label="Genişlikler"></span></div>
      <div class="kv"><span>AVIF de üret<small><?= Images::supports('avif') ? 'Sunucu destekliyor · dosya başına biraz daha uzun sürer' : 'Bu sunucu AVIF üretemiyor; yalnızca WebP üretilir' ?></small></span><?= $sw('gorsel_avif', 'AVIF') ?></div>
      <div class="kv"><span>Kalite<small>WebP / AVIF</small></span><span class="input input--sm" style="width:110px"><input name="kalite" value="<?= e($s['kalite_webp'] . ' / ' . $s['kalite_avif']) ?>" aria-label="Kalite"></span></div>
      <div class="kv"><span>En büyük dosya<small>PHP sınırı: <?= e((string) ini_get('upload_max_filesize')) ?></small></span><span class="input input--sm" style="width:90px"><input name="gorsel_mb" value="<?= e($s['gorsel_mb']) ?>" inputmode="numeric" aria-label="MB"><span class="unit">MB</span></span></div>
    </div>
  </section>
</form>

<section class="card">
  <div class="card__head"><h2>Bakım</h2></div>
  <div class="card__body">
    <div class="kv"><span>Önbellek<small><?= count_fmt($cacheFiles) ?> dosya · <?= $mb($cacheBytes) ?> · sayfalar ve filtre dizinleri</small></span>
      <form method="post" action="<?= e(url('panel/bakim/onbellek')) ?>"><?= csrf_field() ?><button class="btn btn--ghost btn--sm"><?= icon('refresh', 'icon icon--sm') ?>Temizle</button></form></div>
    <div class="kv"><span>Arama dizini<small>Son yenileme: <?= (int) $s['dizin_zamani'] ? e(date_tr((int) $s['dizin_zamani'], true)) : 'hiç' ?></small></span>
      <form method="post" action="<?= e(url('panel/bakim/dizin')) ?>"><?= csrf_field() ?><button class="btn btn--ghost btn--sm"><?= icon('refresh', 'icon icon--sm') ?>Yeniden oluştur</button></form></div>
    <div class="kv"><span>Veritabanı yedeği<small>data/kunye.sqlite · <?= $mb($dbBytes) ?> · görseller ayrıca uploads/ klasöründedir</small></span>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('panel/yedek')) ?>"><?= icon('download', 'icon icon--sm') ?>İndir</a></div>
    <div class="kv"><span>Sürüm<small>künye <?= e(KUNYE_VERSION) ?> · PHP <?= e(PHP_VERSION) ?> · SQLite <?= e((string) Db::val('select sqlite_version()')) ?></small></span><span></span></div>
  </div>
</section>
