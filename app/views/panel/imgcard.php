<?php
/** Görsel kartı (öğe formunda). Düğmeler ana formu farklı adrese gönderir: iç içe form gerekmez. @var array $img @var bool $first */
$widths = array_map('intval', explode(',', $img['widths']));
$kb = static fn(int $b) => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
?>
<figure class="imgc" style="margin:0" data-img="<?= (int) $img['id'] ?>" draggable="true">
  <div class="imgc__pic">
    <img src="<?= e(Images::thumb($img)) ?>" alt="" loading="lazy">
    <?php if ($first): ?><span class="imgc__badge">Kapak</span><?php endif ?>
    <div class="imgc__tools">
      <?php if (!$first): ?><button class="icon-btn icon-btn--sm" type="submit" form="oge" formaction="<?= e(url('panel/gorsel/' . $img['id'] . '/kapak')) ?>" formnovalidate aria-label="Kapak yap" title="Kapak yap"><?= icon('star') ?></button><?php endif ?>
      <button class="icon-btn icon-btn--sm" type="submit" form="oge" formaction="<?= e(url('panel/gorsel/' . $img['id'] . '/sil')) ?>" formnovalidate aria-label="Sil" title="Sil" data-confirm="Görsel silinsin mi?" data-img-del="<?= e(url('panel/gorsel/' . $img['id'] . '/sil')) ?>"><?= icon('trash') ?></button>
    </div>
  </div>
  <figcaption class="imgc__meta"><s><?= e($kb((int) $img['orig_bytes'])) ?></s> → <b><?= e($kb((int) $img['bytes'])) ?></b><br><?= count($widths) ?> boy · <?= min($widths) ?>–<?= max($widths) ?> px<?= (int) $img['avif'] ? ' · AVIF' : '' ?></figcaption>
</figure>
