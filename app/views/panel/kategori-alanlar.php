<?php
/** Kategori bilgileri kartı. @var ?array $cat @var array $sections @var string $prefix ('' ya da 'kategori') */
$n = static fn(string $k) => $prefix === '' ? $k : $prefix . '[' . $k . ']';
$v = static fn(string $k, string $d = '') => $cat[$k] ?? (isset($_POST[$k]) && is_string($_POST[$k]) ? $_POST[$k] : $d);
$icon = $cat['icon'] ?? 'tag';
?>
<section class="card">
  <div class="card__head"><h2>Kategori</h2></div>
  <div class="card__body fields">
    <label class="field"><span class="lbl">Ad (çoğul)</span><span class="input"><input name="<?= $n('name') ?>" value="<?= e($v('name')) ?>" placeholder="Telefonlar" required maxlength="80"></span></label>
    <label class="field"><span class="lbl">Tekil ad</span><span class="input"><input name="<?= $n('singular') ?>" value="<?= e($v('singular')) ?>" placeholder="Telefon" maxlength="80"></span></label>
    <label class="field"><span class="lbl">Bölüm</span><span class="input"><select name="<?= $n('section_id') ?>">
      <option value="">— Yok —</option>
      <?php foreach ($sections as $s): ?><option value="<?= (int) $s['id'] ?>"<?= (int) ($cat['section_id'] ?? 0) === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach ?>
    </select></span></label>
    <label class="field"><span class="lbl">ya da yeni bölüm</span><span class="input"><input name="<?= $n('new_section') ?>" placeholder="ör. Ev, Bilgisayar" maxlength="60"></span></label>
    <label class="field field--full"><span class="lbl">Adres</span><span class="input"><span class="pre"><?= e(Request::host() . Request::base() . '/') ?></span><input name="<?= $n('slug') ?>" value="<?= e($v('slug')) ?>" placeholder="telefon" pattern="[a-z0-9\-]+" maxlength="60"></span>
      <?php if ($cat): ?><span class="hint">Adresi değiştirmek paylaşılmış bağlantıları bozar.</span><?php endif ?></label>
    <div class="field field--full">
      <span class="lbl">İkon</span>
      <div class="icons">
        <?php foreach (Templates::ICONS as $k => $label): ?>
        <label><input type="radio" name="<?= $n('icon') ?>" value="<?= $k ?>"<?= $k === $icon ? ' checked' : '' ?>><span title="<?= e($label) ?>"><?= icon($k) ?></span></label>
        <?php endforeach ?>
      </div>
    </div>
  </div>
</section>
