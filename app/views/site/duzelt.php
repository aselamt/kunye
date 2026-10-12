<?php
/**
 * Düzeltme / yeni öğe önerisi. Her değer yerinde düzenlenebilir bir form alanıdır (JS gerekmez);
 * JS değişen satırları işaretler, alttaki çubuğu ve gözden geçirme penceresini açar.
 * @var array $cat @var ?array $item @var array $groups @var array $values @var array $errors @var array $post @var ?array $cover @var string $sub
 */
$isNew = $item === null;
$slug = $cat['slug'];
$back = $isNew ? url($slug) : url($slug . '/' . $item['slug']);
$posted = $post['v'] ?? null;
?>
<main class="wrap page page-edit">
  <nav class="crumbs" aria-label="Konum">
    <a href="<?= e(url()) ?>">Ana sayfa</a><span class="sep">/</span>
    <a href="<?= e(url($slug)) ?>"><?= e($cat['name']) ?></a><span class="sep">/</span>
    <?php if (!$isNew): ?><a href="<?= e($back) ?>"><?= e($item['name']) ?></a><span class="sep">/</span><?php endif ?>
    <span><?= $isNew ? 'Yeni öğe öner' : 'Düzeltme öner' ?></span>
  </nav>

  <form method="post" action="<?= e(Request::uri()) ?>" data-propose novalidate>
    <?= csrf_field() ?>
    <div class="mini-hero">
      <span class="mini-hero__pic"><?php if ($cover): ?><img src="<?= e(Images::thumb($cover)) ?>" alt=""><?php else: ?><span class="ph"><?= icon($cat['icon']) ?></span><?php endif ?></span>
      <div style="flex:1;min-width:0">
        <?php if ($isNew): ?>
        <label class="ein ein-name"><span class="sr-only">Öğenin adı</span><span class="input"><input name="ad" value="<?= e($post['ad'] ?? '') ?>" placeholder="<?= e($cat['singular']) ?> adı" required maxlength="120"></span></label>
        <p><?= e($cat['singular']) ?> · yeni öğe</p>
        <?php else: ?>
        <h1><?= e($item['name']) ?></h1>
        <p><?= e($sub) ?></p>
        <?php endif ?>
      </div>
    </div>

    <div class="edit-banner">
      <?= icon('pencil') ?>
      <p><b><?= $isNew ? 'Yeni öğe.' : 'Düzeltme modu.' ?></b> <span><?= $isNew ? 'Bildiğin değerleri doldur; boş bıraktıkların sonra eklenebilir. Önerin bir editör onayladıktan sonra yayına girer.' : 'Değiştirmek istediğin değere tıkla. Önerin bir editör onayladıktan sonra yayına girer.' ?></span></p>
      <a class="btn btn--ghost btn--sm" href="<?= e($back) ?>">Vazgeç</a>
    </div>

    <?php foreach ($errors as $err): ?><div class="alert" role="alert"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endforeach ?>

    <nav class="toc" aria-label="Özellik grupları">
      <ul>
        <?php foreach ($groups as $gid => $g): ?><li><a href="#g<?= $gid ?>"><?= icon($g['icon'], 'icon icon--sm') ?><?= e($g['name']) ?></a></li><?php endforeach ?>
      </ul>
    </nav>

    <div class="groups">
      <?php foreach ($groups as $gid => $g): ?>
      <section class="group" id="g<?= $gid ?>">
        <h2 class="group__head"><span class="group__icon"><?= icon($g['icon']) ?></span><?= e($g['name']) ?></h2>
        <dl class="specs">
          <?php foreach ($g['props'] as $pid => $p):
              $cur = $values[$pid] ?? null;
              $val = $posted !== null && array_key_exists($pid, $posted) ? $posted[$pid] : Catalog::inputValue($p, $cur);
              $val = is_array($val) ? implode("\n", $val) : (string) $val;
              $name = 'v[' . $pid . ']';
              $orig = Catalog::display($p, $cur);
              ?>
          <div class="spec" data-ein data-group="<?= e($g['name']) ?>" data-orig-text="<?= e($orig) ?>">
            <dt><label for="v<?= $pid ?>"><?= e($p['name']) ?></label></dt>
            <dd class="ein">
              <div class="ein__row">
              <?php switch ($p['type']):
                  case 'number': ?>
                <span class="input input--sm"><input id="v<?= $pid ?>" name="<?= e($name) ?>" value="<?= e($val) ?>" inputmode="decimal" placeholder="bilgi yok · ekle" data-orig="<?= e(Catalog::inputValue($p, $cur)) ?>"><?php if ($p['unit'] !== ''): ?><span class="unit"><?= e($p['unit']) ?></span><?php endif ?></span>
                  <?php break;
                  case 'bool': ?>
                <div class="seg seg--sm" data-orig="<?= e(Catalog::inputValue($p, $cur)) ?>">
                  <label><input type="radio" id="v<?= $pid ?>" name="<?= e($name) ?>" value="1"<?= $val === '1' ? ' checked' : '' ?>><span>Var</span></label>
                  <label><input type="radio" name="<?= e($name) ?>" value="0"<?= $val === '0' ? ' checked' : '' ?>><span>Yok</span></label>
                  <label><input type="radio" name="<?= e($name) ?>" value=""<?= $val === '' ? ' checked' : '' ?>><span>Bilinmiyor</span></label>
                </div>
                  <?php break;
                  case 'multi': ?>
                <textarea id="v<?= $pid ?>" name="<?= e($name) ?>" rows="<?= max(1, substr_count($val, "\n") + 1) ?>" placeholder="bilgi yok · her satıra bir değer" data-orig="<?= e(Catalog::inputValue($p, $cur)) ?>" data-combo="<?= e(url('api/secenekler/' . $pid)) ?>"><?= e($val) ?></textarea>
                  <?php break;
                  default: ?>
                <span class="input input--sm"><input id="v<?= $pid ?>" name="<?= e($name) ?>" value="<?= e($val) ?>" placeholder="bilgi yok · ekle" data-orig="<?= e(Catalog::inputValue($p, $cur)) ?>"<?= $p['type'] === 'single' ? ' data-combo="' . e(url('api/secenekler/' . $pid)) . '"' : '' ?> maxlength="<?= $p['type'] === 'text' ? 500 : 120 ?>"><?php if ($p['unit'] !== ''): ?><span class="unit"><?= e($p['unit']) ?></span><?php endif ?></span>
              <?php endswitch ?>
                <button class="icon-btn icon-btn--sm ein__undo" type="button" title="Geri al" aria-label="Geri al" data-undo><?= icon('close') ?></button>
              </div>
              <span class="chg__old">önceki: <?= $orig !== '' ? e($orig) : 'bilgi yok' ?></span>
            </dd>
          </div>
          <?php endforeach ?>
        </dl>
      </section>
      <?php endforeach ?>
    </div>

    <div class="sbar js-only" hidden data-sbar>
      <span class="sbar__n" data-sbar-n>0</span>
      <span>değişiklik</span>
      <button type="button" class="btn btn--sm" data-review>İncele ve gönder</button>
    </div>

    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="rv-title" data-sheet>
      <div class="sheet__bg js-only" data-close></div>
      <div class="sheet__panel">
        <div class="sheet__head">
          <h2 id="rv-title"><?= $isNew ? 'Önerini gönder' : 'Önerini gözden geçir' ?></h2>
          <button type="button" class="icon-btn js-only" aria-label="Kapat" data-close><?= icon('close') ?></button>
        </div>
        <ul class="rlist js-only" data-rlist></ul>
        <label class="field">
          <span class="lbl">Kaynak bağlantısı<?= Settings::bool('kaynak_zorunlu') ? '' : ' (isteğe bağlı)' ?></span>
          <span class="input"><input type="url" name="kaynak" value="<?= e($post['kaynak'] ?? '') ?>" placeholder="https://…" inputmode="url" maxlength="500"<?= Settings::bool('kaynak_zorunlu') ? ' required' : '' ?>></span>
          <span class="hint">Kaynak gösterilen öneriler daha hızlı onaylanır.</span>
        </label>
        <label class="field">
          <span class="lbl">Editöre not</span>
          <textarea name="not" maxlength="2000" placeholder="Ör. “Resmi sayfada 6300 mAh yazıyor.”"><?= e($post['not'] ?? '') ?></textarea>
        </label>
        <div class="sheet__foot">
          <span class="hint">Önerin onay sırasına girer.</span>
          <button type="button" class="btn btn--ghost btn--sm js-only" data-close>Düzenlemeye dön</button>
          <button class="btn btn--sm" type="submit">Gönder</button>
        </div>
      </div>
    </div>
  </form>
</main>
