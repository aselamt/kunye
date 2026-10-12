<?php
/**
 * Öğe formu. JS olmadan: düz form alanları, çoklu seçim için satır başına bir değer.
 * JS ile: seçim kutularında var olan değerler (kullanım sayısıyla) ve benzer değer uyarısı, çoklu seçimde jetonlar,
 * görsellerde sürükle-bırak yükleme ve sıralama.
 * @var array $item @var array $cat @var array $tpl @var array $hidden @var array $values @var array $errors @var array $images @var ?array $last
 */
$iid = (int) $item['id'];
$input = static function (array $p, mixed $v): string {
    $name = 'v[' . $p['id'] . ']';
    $val = Catalog::inputValue($p, $v);
    $unit = $p['unit'] !== '' ? '<span class="unit">' . e($p['unit']) . '</span>' : '';
    $combo = ' data-combo="' . e(url('api/secenekler/' . $p['id'])) . '"';
    switch ($p['type']) {
        case 'number':
            return '<span class="input"><input name="' . e($name) . '" value="' . e($val) . '" inputmode="decimal" aria-label="' . e($p['name']) . '">' . $unit . '</span>';
        case 'bool':
            $o = '';
            foreach (['1' => 'Var', '0' => 'Yok', '' => 'Bilinmiyor'] as $k => $l) {
                $o .= '<label><input type="radio" name="' . e($name) . '" value="' . $k . '"' . ((string) $val === (string) $k ? ' checked' : '') . '><span>' . $l . '</span></label>';
            }
            return '<div class="seg seg--sm">' . $o . '</div>';
        case 'multi':
            return '<textarea class="tokens-src" name="' . e($name) . '" rows="' . max(2, substr_count($val, "\n") + 1) . '" placeholder="Her satıra bir değer" aria-label="' . e($p['name']) . '"' . $combo . '>' . e($val) . '</textarea>';
        case 'single':
            return '<span class="input"><input name="' . e($name) . '" value="' . e($val) . '" placeholder="Seç ya da yaz" autocomplete="off" aria-label="' . e($p['name']) . '"' . $combo . '>' . $unit . '</span>';
        default:
            return '<span class="input"><input name="' . e($name) . '" value="' . e($val) . '" maxlength="500" aria-label="' . e($p['name']) . '">' . $unit . '</span>';
    }
};
// alt başlık özellikleri (ör. marka, yıl) "Temel bilgiler" kartında
$basic = array_filter($tpl['props'], static fn($p) => $p['in_eyebrow'] && $p['item_id'] === null && !isset($hidden[$p['id']]));
?>
<?php foreach ($errors as $err): ?><div class="alert"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endforeach ?>

<form id="oge" method="post" action="<?= e(url('panel/oge/' . $iid)) ?>" enctype="multipart/form-data" data-item-form>
  <?= csrf_field() ?>
  <input type="hidden" name="_ankor" value="">

  <section class="card">
    <div class="card__head"><h2>Temel bilgiler</h2></div>
    <div class="card__body fields">
      <label class="field"><span class="lbl">Ad</span><span class="input"><input name="ad" value="<?= e($item['name']) ?>" required maxlength="120"></span></label>
      <label class="field"><span class="lbl">Kategori</span><span class="input"><select disabled><option><?= e($cat['name']) ?></option></select></span></label>
      <?php foreach ($basic as $p): ?>
      <label class="field"><span class="lbl"><?= e($p['name']) ?></span><?= $input($p, $values[$p['id']] ?? null) ?></label>
      <?php endforeach ?>
      <label class="field"><span class="lbl">Durum</span><span class="input"><select name="durum">
        <option value="published"<?= $item['status'] === 'published' ? ' selected' : '' ?>>Yayında</option>
        <option value="draft"<?= $item['status'] === 'draft' ? ' selected' : '' ?>>Taslak</option>
      </select></span></label>
      <label class="field field--full"><span class="lbl">Adres</span><span class="input"><span class="pre"><?= e(Request::host() . Request::base() . '/' . $cat['slug'] . '/') ?></span><input name="adres" value="<?= e($item['slug']) ?>" pattern="[a-z0-9\-]+" maxlength="80"></span></label>
    </div>
  </section>

  <section class="card" id="gorseller">
    <div class="card__head"><h2>Görseller</h2><span class="meta">Sürükleyerek sırala · ilk görsel kapak olur</span></div>
    <div class="card__body">
      <label class="drop" data-drop="<?= e(url('panel/oge/' . $iid . '/gorsel')) ?>">
        <?= icon('upload') ?>
        <span><b>Görsel sürükle</b> ya da <u>bilgisayardan seç</u></span>
        <span class="hint">JPG, PNG, WebP<?= Images::engine() === 'imagick' && Images::supports('heic') ? ', HEIC' : '' ?> · en fazla <?= round(Images::maxBytes() / 1048576) ?> MB · otomatik olarak WebP<?= Settings::bool('gorsel_avif') && Images::supports('avif') ? ' ve AVIF' : '' ?>'e dönüştürülür</span>
        <input type="file" name="gorseller[]" accept="image/*" multiple class="sr-only">
        <span class="hint no-js-only">Seçtiğin dosyalar “Kaydet” ile yüklenir.</span>
      </label>
      <div class="imgs" data-imgs data-order="<?= e(url('panel/oge/' . $iid . '/gorsel-sira')) ?>">
        <?php foreach ($images as $i => $img): ?><?= View::partial('panel/imgcard', ['img' => $img, 'first' => $i === 0]) ?><?php endforeach ?>
      </div>
    </div>
  </section>

  <div class="pbody__title">
    <h2>Özellikler</h2>
    <span class="hint"><?= e($cat['name']) ?> şablonundan geliyor · ondalık için virgül</span>
    <?php if (Auth::is('admin')): ?><a class="btn btn--link" href="<?= e(url('panel/kategori/' . $cat['id'])) ?>">Şablonu düzenle</a><?php endif ?>
  </div>

  <?php
  $cards = [];
  foreach ($tpl['groups'] as $gid => $g) {
      $visible = array_filter($g['props'], static fn($p) => !isset($hidden[$p['id']]) && !($p['in_eyebrow'] && $p['item_id'] === null));
      $hid = array_filter($g['props'], static fn($p) => isset($hidden[$p['id']]));
      $filled = count(array_filter($visible, static fn($p) => !Catalog::isEmpty($values[$p['id']] ?? null)));
      $cards[$gid] = [$g, $visible, $hid, $filled];
  }
  ?>
  <nav class="toc ptoc" aria-label="Özellik grupları">
    <ul>
      <?php foreach ($cards as $gid => [$g, $visible, , $filled]): if (!$visible && $g['item_id'] === null) continue; ?>
      <li><a href="#g-<?= $gid ?>"><?= icon($g['icon'], 'icon icon--sm') ?><?= e($g['name']) ?> <span class="done<?= $filled < count($visible) ? ' done--part' : '' ?>"><?= $filled ?>/<?= count($visible) ?></span></a></li>
      <?php endforeach ?>
    </ul>
  </nav>

  <?php foreach ($cards as $gid => [$g, $visible, $hid, $filled]): if (!$visible && !$hid && $g['item_id'] === null) continue; ?>
  <details class="card" id="g-<?= $gid ?>" open>
    <summary class="card__head">
      <span class="group__icon"><?= icon($g['icon']) ?></span>
      <h3><?= e($g['name']) ?></h3>
      <?php if ($g['item_id'] !== null): ?><span class="tag">bu öğeye özel</span><?php endif ?>
      <span class="meta done<?= $filled < count($visible) ? ' done--part' : '' ?>"><?= $filled ?>/<?= count($visible) ?> dolu</span>
      <?= icon('chevron', 'icon icon--sm chev') ?>
    </summary>
    <?php foreach ($visible as $pid => $p): $v = $values[$pid] ?? null; ?>
    <div class="arow<?= Catalog::isEmpty($v) ? ' is-empty' : '' ?>">
      <div class="arow__label"><?= e($p['name']) ?><?= $p['required'] ? ' <b title="Zorunlu" style="color:var(--bad)">*</b>' : '' ?><small><?= e(Catalog::TYPE_HINT[$p['type']]) ?><?= $p['item_id'] !== null ? ' · bu öğeye özel' : '' ?></small></div>
      <?= $input($p, $v) ?>
      <?php if ($p['item_id'] === null): ?>
      <button class="icon-btn icon-btn--sm arow__x" type="submit" name="gizle" value="<?= $pid ?>" title="Bu öğede gizle" aria-label="<?= e($p['name']) ?> özelliğini bu öğede gizle" formnovalidate><?= icon('close') ?></button>
      <?php else: ?>
      <button class="icon-btn icon-btn--sm arow__x" type="submit" name="sil_ozel" value="<?= $pid ?>" title="Öğeye özel özelliği sil" aria-label="Sil" data-confirm="Bu öğeye özel özellik ve değeri silinsin mi?" formnovalidate><?= icon('trash') ?></button>
      <?php endif ?>
    </div>
    <?php endforeach ?>
    <div class="card__foot">
      <details class="addprop">
        <summary class="btn btn--link"><?= icon('plus', 'icon icon--sm') ?>Bu öğeye özel özellik ekle</summary>
        <div class="addprop__row">
          <span class="input input--sm"><input name="yeni[<?= $gid ?>][ad]" placeholder="Özellik adı" maxlength="80" aria-label="Özellik adı"></span>
          <span class="input input--sm"><select name="yeni[<?= $gid ?>][tip]" aria-label="Tip"><?php foreach (Catalog::TYPES as $k => $l): ?><option value="<?= $k ?>"<?= $k === 'text' ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></span>
          <span class="input input--sm"><input name="yeni[<?= $gid ?>][birim]" placeholder="Birim" maxlength="20" aria-label="Birim"></span>
          <span class="input input--sm"><input name="yeni[<?= $gid ?>][deger]" placeholder="Değer" maxlength="500" aria-label="Değer"></span>
          <button class="btn btn--sm" type="submit" data-anchor="g-<?= $gid ?>">Ekle</button>
        </div>
      </details>
      <?php if ($hid): ?>
      <span class="hint">Bu öğede gizlenen <?= count($hid) ?> özellik:
        <?php foreach ($hid as $hp): ?><button class="btn--link btn--sm" type="submit" name="goster" value="<?= (int) $hp['id'] ?>" formnovalidate style="height:auto;padding:2px 4px"><?= e($hp['name']) ?> ↺</button><?php endforeach ?>
      </span>
      <?php endif ?>
    </div>
  </details>
  <?php endforeach ?>

  <details class="card addgroup">
    <summary class="card__head"><span class="group__icon"><?= icon('plus') ?></span><h3>Bu öğeye özel grup ekle</h3><?= icon('chevron', 'icon icon--sm chev') ?></summary>
    <div class="card__body fields">
      <label class="field"><span class="lbl">Grup adı</span><span class="input"><input name="yeni_grup[ad]" maxlength="60"></span></label>
      <label class="field"><span class="lbl">İkon</span><span class="input"><select name="yeni_grup[ikon]"><?php foreach (Templates::ICONS as $k => $l): ?><option value="<?= $k ?>"<?= $k === 'layers' ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></span></label>
      <label class="field"><span class="lbl">İlk özellik</span><span class="input"><input name="yeni_grup[ozellik]" maxlength="80"></span></label>
      <label class="field"><span class="lbl">Tip</span><span class="input"><select name="yeni_grup[tip]"><?php foreach (Catalog::TYPES as $k => $l): ?><option value="<?= $k ?>"<?= $k === 'text' ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></span></label>
      <label class="field"><span class="lbl">Birim</span><span class="input"><input name="yeni_grup[birim]" maxlength="20"></span></label>
      <label class="field"><span class="lbl">Değer</span><span class="input"><input name="yeni_grup[deger]" maxlength="500"></span></label>
    </div>
  </details>

  <div class="danger">
    <button class="btn btn--ghost btn--danger btn--sm" type="submit" formaction="<?= e(url('panel/oge/' . $iid . '/sil')) ?>" formnovalidate data-confirm="“<?= e($item['name']) ?>” ve tüm görselleri kalıcı olarak silinsin mi?"><?= icon('trash', 'icon icon--sm') ?>Öğeyi sil</button>
    <button class="btn btn--sm" type="submit">Kaydet</button>
  </div>
</form>

<p class="pfoot">
  <?php if ($last): ?>Son düzenleme: <?= e($last['username'] ?? 'sistem') ?> · <?= e(date_tr((int) $last['created_at'])) ?> · <?php endif ?>
  <a href="<?= e(url('panel/oge/' . $iid . '/gecmis')) ?>">Değişiklik geçmişi</a>
</p>
