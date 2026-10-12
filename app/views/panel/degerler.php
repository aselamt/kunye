<?php
/** @var array $cats @var int $catId @var array $props @var ?array $prop @var array $rows @var array $tabs @var string $view @var string $q @var int $max */
$choice = $prop && in_array($prop['type'], ['single', 'multi'], true);
$fmt = static fn(array $r) => (string) $r['name'] . ($prop && $prop['unit'] !== '' && !$choice ? ' ' . $prop['unit'] : '');
?>
<form class="ptools" method="get" action="<?= e(url('panel/degerler')) ?>" data-autosubmit>
  <span class="input"><select name="kategori" aria-label="Kategori" data-reset="ozellik">
    <?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $catId === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?>
  </select></span>
  <span class="input"><select name="ozellik" aria-label="Özellik">
    <?php foreach ($props as $p): ?><option value="<?= (int) $p['id'] ?>"<?= $prop && (int) $prop['id'] === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['gname'] . ' › ' . $p['name']) ?></option><?php endforeach ?>
  </select></span>
  <label class="fsearch"><?= icon('search', 'icon icon--sm') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Değer ara…" aria-label="Değer ara"></label>
  <?php if ($view !== ''): ?><input type="hidden" name="gorunum" value="<?= e($view) ?>"><?php endif ?>
  <button class="btn btn--ghost btn--sm no-js-only" type="submit">Göster</button>
</form>

<?php if (!$prop): ?>
<div class="empty"><?= icon('tag') ?><h2>Özellik yok</h2><p>Bu kategoride değer listelenecek bir özellik bulunmuyor.</p></div>
<?php else: ?>
<nav class="tabs" aria-label="Görünüm">
  <?php foreach ($tabs as $k => [$label, $n]): ?>
  <a<?= $view === $k ? ' class="is-on"' : '' ?> href="<?= e(url('panel/degerler', ['ozellik' => $prop['id'], 'gorunum' => $k ?: null])) ?>"><?= e($label) ?> <span class="n"><?= $n ?></span></a>
  <?php endforeach ?>
</nav>

<form method="post" action="<?= e(url('panel/degerler', ['gorunum' => $view ?: null])) ?>" data-values>
  <?= csrf_field() ?>
    <button type="submit" disabled hidden aria-hidden="true" tabindex="-1"></button><!-- Enter tuşu yanlış düğmeyi tetiklemesin -->
  <input type="hidden" name="ozellik" value="<?= (int) $prop['id'] ?>">
  <?php if ($rows): ?>
  <div class="dtable-wrap">
    <table class="dtable vtable">
      <thead><tr><th></th><th>Değer</th><th class="num">Kullanım</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $k = (string) $r['k']; ?>
        <tr>
          <td><label class="check"><input type="checkbox" name="sec[]" value="<?= e($k) ?>" data-name="<?= e($fmt($r)) ?>" data-n="<?= $r['n'] ?>" aria-label="Seç"></label></td>
          <td>
            <details class="ren">
              <summary><b style="font-weight:550"><?= e($fmt($r)) ?></b><?= icon('pencil') ?></summary>
              <div class="vname">
                <span class="input"><input name="ad[<?= e($k) ?>]" value="<?= e((string) $r['name']) ?>" aria-label="Yeni ad" maxlength="<?= $prop['type'] === 'text' ? 500 : 120 ?>"></span>
                <button class="btn btn--sm" type="submit" name="adlandir" value="<?= e($k) ?>">Kaydet</button>
              </div>
            </details>
            <?php if ($r['similar']): ?>
            <div class="sim">Benzer:
              <?php foreach (array_slice($r['similar'], 0, 3) as $i => $s): ?><?= $i ? ' · ' : '' ?><i><?= e($s['name']) ?></i> (<?= $s['n'] ?>) <button class="btn--link" style="height:auto;padding:0 2px" type="submit" name="benzer" value="<?= e($k . '>' . $s['k']) ?>" title="“<?= e($r['name']) ?>” değerini “<?= e($s['name']) ?>” ile birleştir">birleştir</button><?php endforeach ?>
            </div>
            <?php endif ?>
          </td>
          <td class="num"><div class="usage"><span class="usage__bar"><span style="width:<?= (int) round($r['n'] / max(1, $max) * 100) ?>%"></span></span><?= count_fmt($r['n']) ?></div></td>
          <td class="act"><?php if ($r['n'] && $prop['clickable']): ?><a class="icon-btn icon-btn--sm" href="<?= e(url($cats[$catId]['slug'], [$prop['slug'] => $choice ? Db::val('SELECT slug FROM options WHERE id = ?', [(int) $k]) : $k])) ?>" target="_blank" rel="noopener" aria-label="Öğeleri gör" title="Öğeleri gör"><?= icon('list') ?></a><?php endif ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="empty"><?= icon('tag') ?><p>Bu görünümde değer yok.</p></div>
  <?php endif ?>

  <p class="pfoot">Benzerlik kelime düzeyinde aranır: “IPS LCD” ile “IPS-LCD” benzer sayılır, “OLED” ile “AMOLED” farklı sayılır. Büyük/küçük harf ve Türkçe karakter farkları zaten tek değer olarak saklanır. Yeniden adlandırma ve birleştirme tüm öğelere uygulanır ve geçmişe kaydedilir.
  <?php if ($choice && ($tabs['kullanilmayan'][1] ?? 0) > 0): ?><button class="btn--link" type="submit" name="temizle" value="1" style="height:auto;padding:0">Kullanılmayan <?= $tabs['kullanilmayan'][1] ?> değeri sil</button><?php endif ?></p>

  <?php if ($rows && $prop['type'] !== 'bool'): ?>
  <div class="mbar2" data-mbar>
    <span><b data-mbar-n>Seçili değerleri</b> <span data-mbar-items></span></span>
    <span class="input"><select name="hedef" aria-label="Hedef değer" data-target>
      <option value="">İlk seçilen değer olarak birleştir</option>
      <?php foreach ($rows as $r): ?><option value="<?= e((string) $r['k']) ?>">“<?= e($fmt($r)) ?>” olarak birleştir</option><?php endforeach ?>
    </select></span>
    <button class="btn btn--sm" type="submit" name="birlestir" value="1" data-confirm="Seçilen değerler birleştirilsin mi? Bu işlem geri alınamaz."><?= icon('merge', 'icon icon--sm') ?>Birleştir</button>
  </div>
  <?php endif ?>
</form>
<?php endif ?>
