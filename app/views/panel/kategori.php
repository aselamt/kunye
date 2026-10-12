<?php
/**
 * Şablon düzenleyici. Tek form; JS olmadan da tüm alanlar çalışır (sıra için "Sıra" kutuları).
 * @var array $cat @var array $tpl @var array $opts @var array $filled @var array $errors @var array $sections @var int $itemCount
 */
$active = [];
$archived = [];
foreach ($tpl['props'] as $p) {
    if ($p['item_id'] !== null) {
        continue;
    }
    $p['archived'] ? $archived[] = $p : $active[] = $p;
}
$tplGroups = array_filter($tpl['groups'], static fn($g) => $g['item_id'] === null);
$listProps = array_filter($active, static fn($p) => $p['in_list']);
$keyProps = array_filter($active, static fn($p) => $p['is_key']);
$eyeProps = array_filter($active, static fn($p) => $p['in_eyebrow']);
$flag = static function (string $name, bool $on, string $label = '', string $cls = 'switch') {
    return '<input type="hidden" name="' . e($name) . '" value="0"><input class="' . $cls . '" type="checkbox" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . ($label ? '' : ' aria-label="Filtrede göster"') . '>' . ($label ? ' ' . e($label) : '');
};
?>
<?php foreach ($errors as $err): ?><div class="alert"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endforeach ?>

<form id="sablon" method="post" action="<?= e(url('panel/kategori/' . $cat['id'])) ?>" data-template>
  <?= csrf_field() ?>
  <input type="hidden" name="_ankor" value="">
  <?= View::partial('panel/kategori-alanlar', ['cat' => $cat, 'sections' => $sections, 'prefix' => 'kategori']) ?>

  <section class="card">
    <div class="card__head"><h2>Görünüm</h2></div>
    <div class="card__body fields">
      <?php foreach ([['Listede gösterilen özellikler', $listProps, 'En fazla 4 · özellik satırındaki ⋯ menüsünden seçilir'], ['Öne çıkan özellikler (detay sayfasının üstü)', $keyProps, 'En fazla 4'], ['Alt başlık (ör. marka, yıl)', $eyeProps, 'Detay sayfasında adın üstünde ve aramada kullanılır']] as [$label, $list, $hint]): ?>
      <div class="field field--full">
        <span class="lbl"><?= e($label) ?></span>
        <div class="tokens">
          <?php foreach ($list as $p): ?><span class="token" style="padding-right:10px"><?= e($p['name']) ?></span><?php endforeach ?>
          <?php if (!$list): ?><span class="hint" style="padding:0 6px">Seçili özellik yok</span><?php endif ?>
        </div>
        <span class="hint"><?= e($hint) ?></span>
      </div>
      <?php endforeach ?>
    </div>
  </section>

  <div class="pbody__title">
    <h2>Özellik grupları</h2>
    <span class="hint">Sürükleyerek sırala · her öğe bu şablonla başlar</span>
  </div>

  <div data-groups>
  <?php foreach ($tplGroups as $gid => $g):
      $props = array_filter($g['props'], static fn($p) => !$p['archived'] && $p['item_id'] === null); ?>
  <details class="card" id="g-<?= $gid ?>" data-group open>
    <summary class="card__head">
      <span class="grip js-only" data-grip title="Sürükle"><?= icon('grip', 'icon icon--sm') ?></span>
      <span class="group__icon"><?= icon($g['icon']) ?></span>
      <h3><?= e($g['name']) ?></h3>
      <span class="meta"><?= plural_ozellik(count($props)) ?></span>
      <?= icon('chevron', 'icon icon--sm chev') ?>
    </summary>
    <div class="gedit">
      <span class="input input--sm"><input name="grup[<?= $gid ?>][name]" value="<?= e($g['name']) ?>" aria-label="Grup adı" maxlength="60"></span>
      <span class="input input--sm"><select name="grup[<?= $gid ?>][icon]" aria-label="Grup ikonu"><?php foreach (Templates::ICONS as $k => $l): ?><option value="<?= $k ?>"<?= $k === $g['icon'] ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></span>
      <span class="input input--sm" style="min-width:0;width:90px"><input name="grup[<?= $gid ?>][sort]" value="<?= (int) $g['sort'] ?>" inputmode="numeric" aria-label="Grup sırası" data-gsort></span>
      <?php if (!$g['props']): ?><label class="check"><input type="checkbox" name="grup[<?= $gid ?>][delete]" value="1"> Grubu sil</label><?php endif ?>
    </div>

    <div class="ttable" data-props>
      <div class="trow trow--head"><span></span><span>Özellik</span><span>Tip</span><span>Birim</span><span>Filtre</span><span></span></div>
      <?php foreach ($props as $pid => $p): $o = $opts[$pid] ?? []; ?>
      <div class="tprop" data-prop>
        <input type="checkbox" class="tmore" id="m<?= $pid ?>">
        <div class="trow">
          <span class="grip" data-grip title="Sürükle"><?= icon('grip', 'icon grip') ?></span>
          <span class="input input--sm"><input name="oz[<?= $pid ?>][name]" value="<?= e($p['name']) ?>" aria-label="Özellik adı" maxlength="80"></span>
          <span class="input input--sm"><select name="oz[<?= $pid ?>][type]" aria-label="Tip"><?php foreach (Catalog::TYPES as $k => $l): ?><option value="<?= $k ?>"<?= $k === $p['type'] ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></span>
          <span class="input input--sm"><input name="oz[<?= $pid ?>][unit]" value="<?= e($p['unit']) ?>" placeholder="—" aria-label="Birim" maxlength="20"></span>
          <span><?= $flag("oz[$pid][filterable]", (bool) $p['filterable']) ?></span>
          <label for="m<?= $pid ?>" class="icon-btn icon-btn--sm" title="Diğer ayarlar" aria-label="Diğer ayarlar"><?= icon('more') ?></label>
        </div>
        <div class="tdetail">
          <div class="fields">
            <label class="field"><span class="lbl">Kısa ad</span><span class="input input--sm"><input name="oz[<?= $pid ?>][short]" value="<?= e($p['short']) ?>" placeholder="<?= e($p['name']) ?>" maxlength="40"></span><span class="hint">Liste ve öne çıkanlarda</span></label>
            <label class="field"><span class="lbl">Adres anahtarı</span><span class="input input--sm"><input name="oz[<?= $pid ?>][slug]" value="<?= e($p['slug']) ?>" pattern="[a-z0-9\-]+" maxlength="60"></span><span class="hint">?<?= e($p['slug']) ?>=…</span></label>
            <label class="field"><span class="lbl">Grup · sıra</span><span style="display:flex;gap:6px"><span class="input input--sm" style="flex:1"><select name="oz[<?= $pid ?>][group]"><?php foreach ($tplGroups as $tg): ?><option value="<?= (int) $tg['id'] ?>"<?= (int) $tg['id'] === $p['group_id'] ? ' selected' : '' ?>><?= e($tg['name']) ?></option><?php endforeach ?></select></span><span class="input input--sm" style="width:64px"><input name="oz[<?= $pid ?>][sort]" value="<?= $p['sort'] ?>" inputmode="numeric" aria-label="Sıra" data-psort></span></span></label>
          </div>
          <?php if ($o): ?>
          <span class="lbl">Değerler (<?= count($o) ?>)</span>
          <div class="vals">
            <?php foreach (array_slice($o, 0, 14) as $ov): ?><span class="val"><?= e($ov['name']) ?> <span class="n"><?= (int) $ov['n'] ?></span>&nbsp;</span><?php endforeach ?>
            <a class="btn btn--link btn--sm" href="<?= e(url('panel/degerler', ['ozellik' => $pid])) ?>">Değerleri yönet →</a>
          </div>
          <?php elseif (isset($filled[$pid])): ?>
          <span class="lbl"><?= (int) $filled[$pid] ?> öğede dolu · <a class="btn--link" href="<?= e(url('panel/degerler', ['ozellik' => $pid])) ?>" style="height:auto;padding:0">değerleri yönet</a></span>
          <?php endif ?>
          <span class="lbl">Ayarlar</span>
          <div class="tdetail__opts">
            <label><?= $flag("oz[$pid][clickable]", (bool) $p['clickable'], 'Detay sayfasında tıklanabilir') ?></label>
            <label><?= $flag("oz[$pid][in_list]", (bool) $p['in_list'], 'Listede göster') ?></label>
            <label><?= $flag("oz[$pid][is_key]", (bool) $p['is_key'], 'Öne çıkan') ?></label>
            <label><?= $flag("oz[$pid][in_eyebrow]", (bool) $p['in_eyebrow'], 'Alt başlıkta göster') ?></label>
            <label><?= $flag("oz[$pid][required]", (bool) $p['required'], 'Zorunlu') ?></label>
            <label><input type="hidden" name="oz[<?= $pid ?>][archive]" value="0"><input class="switch" type="checkbox" name="oz[<?= $pid ?>][archive]" value="1"> Arşivle (değerler silinmez)</label>
          </div>
        </div>
      </div>
      <?php endforeach ?>
    </div>

    <div class="card__foot">
      <details class="addprop">
        <summary class="btn btn--link"><?= icon('plus', 'icon icon--sm') ?>Özellik ekle</summary>
        <div class="addprop__row">
          <span class="input input--sm"><input name="yeni_oz[<?= $gid ?>][name]" placeholder="Özellik adı" maxlength="80" aria-label="Yeni özellik adı"></span>
          <span class="input input--sm"><select name="yeni_oz[<?= $gid ?>][type]" aria-label="Tip"><?php foreach (Catalog::TYPES as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach ?></select></span>
          <span class="input input--sm"><input name="yeni_oz[<?= $gid ?>][unit]" placeholder="Birim" maxlength="20" aria-label="Birim"></span>
          <button class="btn btn--sm" type="submit" data-anchor="g-<?= $gid ?>">Ekle</button>
        </div>
      </details>
    </div>
  </details>
  <?php endforeach ?>
  </div>

  <details class="card addgroup">
    <summary class="card__head"><span class="group__icon"><?= icon('plus') ?></span><h3>Yeni grup</h3><?= icon('chevron', 'icon icon--sm chev') ?></summary>
    <div class="card__body fields">
      <label class="field"><span class="lbl">Grup adı</span><span class="input"><input name="yeni_grup[name]" maxlength="60" placeholder="ör. Kamera"></span></label>
      <label class="field"><span class="lbl">İkon</span><span class="input"><select name="yeni_grup[icon]"><?php foreach (Templates::ICONS as $k => $l): ?><option value="<?= $k ?>"<?= $k === 'layers' ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></span></label>
    </div>
  </details>

  <?php if ($archived): ?>
  <section class="card">
    <div class="card__head"><h2>Arşivlenmiş özellikler</h2><span class="meta">Öğelerde görünmez, değerleri saklanır</span></div>
    <?php foreach ($archived as $p): ?>
    <div class="arch">
      <b><?= e($p['name']) ?> <small><?= e(Catalog::TYPE_HINT[$p['type']]) ?> · <?= (int) ($filled[$p['id']] ?? 0) ?> öğede dolu</small></b>
      <label class="check"><input type="hidden" name="oz[<?= $p['id'] ?>][archive]" value="1"><input type="checkbox" name="oz[<?= $p['id'] ?>][archive]" value="0"> Geri getir</label>
      <label class="check"><input type="checkbox" name="oz[<?= $p['id'] ?>][purge]" value="1"> Kalıcı sil</label>
    </div>
    <?php endforeach ?>
  </section>
  <?php endif ?>

  <div class="danger">
    <?php if ($itemCount === 0): ?>
    <button class="btn btn--ghost btn--danger btn--sm" type="submit" formaction="<?= e(url('panel/kategori/' . $cat['id'] . '/sil')) ?>" formnovalidate data-confirm="“<?= e($cat['name']) ?>” kategorisi silinsin mi?"><?= icon('trash', 'icon icon--sm') ?>Kategoriyi sil</button>
    <?php else: ?><span class="hint">Kategoride <?= count_fmt($itemCount) ?> öğe var.</span><?php endif ?>
    <button class="btn btn--sm" type="submit">Kaydet</button>
  </div>
</form>
<p class="pfoot">Şablondaki değişiklikler bu kategorideki tüm öğelere uygulanır. Öğelerdeki değerler silinmez; tip değişikliğinde dönüştürülür.</p>
