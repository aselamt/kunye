<?php
/** @var string $tab @var string $q @var array $list @var array $counts @var ?array $sel @var ?array $detail @var array $summaries @var array $covers */
$tabUrl = static fn(string $t) => url('panel/oneriler', ['durum' => $t !== 'pending' ? $t : null, 'q' => $q ?: null]);
$disp = static fn(array $p, mixed $v) => Catalog::display($p, $v);
$pending = $sel && $sel['status'] === 'pending';
?>
<nav class="tabs" aria-label="Durum">
  <?php foreach (PanelReviewController::TABS as $k => $label): ?>
  <a<?= $tab === $k ? ' class="is-on"' : '' ?> href="<?= e($tabUrl($k)) ?>"><?= e($label) ?> <span class="n"><?= (int) ($counts[$k] ?? 0) ?></span></a>
  <?php endforeach ?>
</nav>

<div class="inbox">
  <div class="qlist" role="list">
    <?php if (!$list): ?><div class="empty"><?= icon('inbox') ?><p><?= $tab === 'pending' ? 'Bekleyen öneri yok.' : 'Kayıt yok.' ?></p></div><?php endif ?>
    <?php foreach ($list as $s):
        [$label, $cls] = Suggestions::KINDS[$s['kind']];
        $sum = $summaries[$s['id']] ?? [];
        $title = $s['item_id'] ? ($s['item_name'] ?? '—') : $s['name'];
        $text = $s['kind'] === 'new' ? $s['cat_name'] . ' kategorisine yeni öğe · ' . plural_ozellik((int) $s['n'])
            : ((int) $s['n'] === 1 && $sum ? array_key_first($sum) . ' › ' . reset($sum) : (int) $s['n'] . ' değişiklik · ' . implode(', ', array_keys($sum)));
        $cover = $s['item_id'] ? ($covers[$s['item_id']] ?? null) : null;
        ?>
    <a class="qitem<?= $sel && (int) $sel['id'] === (int) $s['id'] ? ' is-on' : '' ?>" href="<?= e(url('panel/oneriler', ['id' => $s['id'], 'durum' => $tab !== 'pending' ? $tab : null, 'q' => $q ?: null])) ?>" role="listitem">
      <span class="qpic"><?php if ($cover): ?><img src="<?= e(Images::thumb($cover)) ?>" alt=""><?php else: ?><?= icon($s['kind'] === 'new' ? 'plus' : $s['cat_icon']) ?><?php endif ?></span>
      <div>
        <div class="qitem__top"><b><?= e($title) ?></b><time datetime="<?= date('c', (int) $s['created_at']) ?>"><?= e(ago((int) $s['created_at'])) ?></time></div>
        <p class="qitem__sum"><?= e($text) ?></p>
        <div class="qitem__meta"><span class="tag <?= $cls ?>"><?= e($label) ?></span><?= e($s['username']) ?></div>
      </div>
    </a>
    <?php endforeach ?>
  </div>

  <?php if ($sel && $detail):
      [$label, $cls] = Suggestions::KINDS[$sel['kind']];
      $title = $sel['item_id'] ? ($sel['item_name'] ?? '—') : $sel['name'];
      [$total, $rate] = $detail['stats'];
      $cover = $sel['item_id'] ? ($covers[$sel['item_id']] ?? (Catalog::cards([(int) $sel['item_id']])[(int) $sel['item_id']]['cover'] ?? null)) : null;
      ?>
  <form class="card" style="margin:0" method="post" action="<?= e(url('panel/oneri/' . $sel['id'])) ?>" aria-label="Seçili öneri" data-review>
    <?= csrf_field() ?>
    <button type="submit" disabled hidden aria-hidden="true" tabindex="-1"></button><!-- Enter tuşu yanlış düğmeyi tetiklemesin -->
    <div class="qhead">
      <span class="qpic"><?php if ($cover): ?><img src="<?= e(Images::thumb($cover)) ?>" alt=""><?php else: ?><?= icon($sel['kind'] === 'new' ? 'plus' : $sel['cat_icon']) ?><?php endif ?></span>
      <div>
        <h2><?= e($title) ?></h2>
        <p><?= e($sel['cat_name']) ?><?php if ($sel['item_id'] && $sel['item_slug']): ?> · <a href="<?= e(url($sel['cat_slug'] . '/' . $sel['item_slug'])) ?>" target="_blank" rel="noopener">sayfayı aç</a> · <a href="<?= e(url('panel/oge/' . $sel['item_id'])) ?>">düzenle</a><?php endif ?></p>
      </div>
      <span class="tag <?= $cls ?>"><?= e($label) ?></span>
    </div>

    <div class="qby">
      <span class="avatar" style="width:26px;height:26px;font-size:11px"><?= e(initial($sel['username'])) ?></span>
      <b><?= e($sel['username']) ?></b> önerdi · <?= e(date_tr((int) $sel['created_at'], true)) ?>
      <?php if ($rate !== null): ?><span class="trust"><?= $total ?> öneri · %<?= $rate ?> onaylandı</span><?php else: ?><span class="tag">ilk önerisi</span><?php endif ?>
    </div>
    <?php if ($sel['note'] !== '' || $sel['source'] !== ''): ?>
    <div class="note">
      <?php if ($sel['note'] !== ''): ?><p><?= nl2br(e($sel['note'])) ?></p><?php endif ?>
      <?php if ($sel['source'] !== ''): ?><a href="<?= e($sel['source']) ?>" target="_blank" rel="noopener nofollow ugc"><?= icon('external', 'icon icon--sm') ?><?= e(preg_replace('#^https?://(www\.)?#', '', mb_strimwidth($sel['source'], 0, 70, '…'))) ?></a><?php endif ?>
    </div>
    <?php endif ?>

    <div class="dhead">Değişiklikler (<?= count($detail['rows']) ?>)<?php if ($pending): ?><a href="#" class="js-only" data-check-all>Tümünü seç</a><?php endif ?></div>

    <?php foreach ($detail['rows'] as $r):
        $c = $r['change'];
        $p = $r['prop'];
        $cid = (int) $c['id'];
        $isOff = !$pending && $c['status'] === 'rejected';
        ?>
    <div class="drow<?= $isOff ? ' is-off' : '' ?>" data-drow>
      <label class="check"><input type="checkbox" name="ok[<?= $cid ?>]" value="1"<?= $pending || $c['status'] === 'approved' ? ' checked' : '' ?><?= $pending ? '' : ' disabled' ?> aria-label="Bu değişikliği onayla"></label>
      <div class="drow__prop"><small><?= icon($r['gicon']) ?><?= e($r['group']) ?></small><?= e($p['name']) ?></div>
      <div class="drow__vals">
        <?php if ($p['type'] === 'multi'): ?>
          <?php foreach ($r['added'] as $a): ?><span class="dnew">+ <?= e($a) ?></span><?php endforeach ?>
          <?php foreach ($r['removed'] as $a): ?><span class="dold"><?= e($a) ?></span><?php endforeach ?>
        <?php else: ?>
          <?php if (Catalog::isEmpty($r['old'])): ?><span class="dempty">boş</span><?php else: ?><span class="dold"><?= e($disp($p, $r['old'])) ?></span><?php endif ?>
          <?= icon('arrow') ?>
          <?php if (Catalog::isEmpty($r['new'])): ?><span class="dempty">silinsin</span><?php else: ?><span class="dnew"><?= e($disp($p, $r['new'])) ?></span><?php endif ?>
        <?php endif ?>
        <?php if (!$pending && $c['status'] === 'approved' && $r['final'] !== null && !Catalog::same($p, $r['final'], $r['new'])): ?>
          <span class="dwarn"><span><b>Düzeltilerek onaylandı:</b> <?= e($disp($p, $r['final'])) ?></span></span>
        <?php endif ?>
        <?php if ($pending): foreach ($r['warn'] as $w): ?>
        <div class="dwarn">
          <span><b>Yeni değer.</b> <?php if ($w['similar']): ?>Benzeri var: <?php foreach ($w['similar'] as $i => $sim): ?><?= $i ? ', ' : '' ?><i><?= e($sim['name']) ?></i> (<?= $sim['n'] ?> öğe)<?php endforeach ?><?php else: ?>“<?= e($w['value']) ?>” bu özellikte ilk kez kullanılacak.<?php endif ?></span>
          <?php if ($w['similar']): ?><a href="#" class="js-only" data-use="<?= e($w['similar'][0]['name']) ?>" data-for="<?= e($w['value']) ?>">Bunu kullan</a><?php endif ?>
        </div>
        <?php endforeach; endif ?>
      </div>
      <?php if ($pending): ?>
      <details class="dedit">
        <summary class="icon-btn icon-btn--sm" aria-label="Önerilen değeri düzenle" title="Önerilen değeri düzelt"><?= icon('pencil') ?></summary>
        <?php if ($p['type'] === 'multi'): ?>
        <textarea name="final[<?= $cid ?>]" aria-label="Düzeltilmiş değer (her satıra bir değer)"><?= e(Catalog::inputValue($p, $r['new'])) ?></textarea>
        <?php elseif ($p['type'] === 'bool'): ?>
        <span class="input input--sm"><select name="final[<?= $cid ?>]" aria-label="Düzeltilmiş değer"><option value="">Önerildiği gibi</option><option value="1">Var</option><option value="0">Yok</option></select></span>
        <?php else: ?>
        <span class="input input--sm"><input name="final[<?= $cid ?>]" placeholder="<?= e(Catalog::inputValue($p, $r['new'])) ?>" aria-label="Düzeltilmiş değer"<?= $p['type'] === 'single' ? ' data-combo="' . e(url('api/secenekler/' . $p['id'])) . '"' : '' ?>><?php if ($p['unit'] !== ''): ?><span class="unit"><?= e($p['unit']) ?></span><?php endif ?></span>
        <?php endif ?>
      </details>
      <?php endif ?>
    </div>
    <?php endforeach ?>

    <?php if ($pending): ?>
    <div class="qreply">
      <textarea name="yanit" maxlength="2000" placeholder="Kullanıcıya not (isteğe bağlı) — ör. “Ağırlık kaynakta geçmiyor, o yüzden eklemedim.”" aria-label="Kullanıcıya not"></textarea>
    </div>
    <div class="qactions">
      <button class="btn btn--ghost btn--danger btn--sm" type="submit" name="islem" value="reddet" data-confirm="Önerinin tamamı reddedilsin mi?">Reddet</button>
      <span class="spacer"></span>
      <?php if ($sel['kind'] === 'new'): ?>
      <span class="input input--sm" style="width:auto"><select name="yayin" aria-label="Yeni öğenin durumu"><option value="published">Yayınla</option><option value="draft">Taslak olarak ekle</option></select></span>
      <?php endif ?>
      <?php
      $next = null;
      foreach ($list as $i => $s) {
          if ((int) $s['id'] === (int) $sel['id']) {
              $next = $list[$i + 1] ?? null;
          }
      }
      if ($next): ?><a class="btn btn--ghost btn--sm" href="<?= e(url('panel/oneriler', ['id' => $next['id']])) ?>">Atla</a><?php endif ?>
      <button class="btn btn--sm" type="submit" name="islem" value="onayla" data-approve>Seçilenleri onayla</button>
      <span class="hint">Onaylanan değişiklikler hemen yayına girer ve öğenin geçmişine “<?= e($sel['username']) ?> önerisi” olarak kaydedilir.</span>
    </div>
    <?php else: ?>
    <div class="qactions"><span class="hint"><?= $sel['status'] === 'done' ? 'Onaylandı' : 'Reddedildi' ?><?= $sel['reviewed_at'] ? ' · ' . e(date_tr((int) $sel['reviewed_at'], true)) : '' ?><?= $sel['reply'] !== '' ? ' · Not: ' . e($sel['reply']) : '' ?></span></div>
    <?php endif ?>
  </form>
  <?php endif ?>
</div>
