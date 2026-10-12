<?php /** @var array $cards @var int $total @var int $page @var int $pages @var string $q @var int $catId @var string $status @var array $editors */
$qs = static fn(int $p) => url('panel/ogeler', ['q' => $q, 'kategori' => $catId ?: null, 'durum' => $status ?: null, 'sayfa' => $p > 1 ? $p : null]);
?>
<form class="ptools" method="get" action="<?= e(url('panel/ogeler')) ?>" data-autosubmit>
  <label class="fsearch"><?= icon('search', 'icon icon--sm') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Öğe ara…" aria-label="Öğe ara"></label>
  <span class="input"><select name="kategori" aria-label="Kategori"><option value="">Tüm kategoriler</option>
    <?php foreach (Catalog::categories() as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $catId === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?>
  </select></span>
  <span class="input"><select name="durum" aria-label="Durum"><option value="">Tüm durumlar</option>
    <option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Yayında</option>
    <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Taslak</option>
  </select></span>
  <button class="btn btn--ghost btn--sm no-js-only" type="submit">Süz</button>
  <span class="hint"><?= count_fmt($total) ?> öğe</span>
</form>

<?php if ($cards): ?>
<div class="dtable-wrap">
  <table class="dtable">
    <thead><tr><th>Öğe</th><th>Durum</th><th>Düzenleyen</th><th>Güncellendi</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($cards as $it): ?>
      <tr>
        <td><a class="cell-item" href="<?= e(url('panel/oge/' . $it['id'])) ?>"><span class="qpic"><?php if ($it['cover']): ?><img src="<?= e(Images::thumb($it['cover'])) ?>" alt="" loading="lazy"><?php else: ?><?= icon($it['category']['icon'] ?? 'box') ?><?php endif ?></span><span><b><?= e($it['name']) ?></b><small><?= e(implode(' · ', array_merge([$it['category']['name'] ?? ''], array_slice($it['sub'], 0, 1)))) ?></small></span></a></td>
        <td><span class="status<?= $it['status'] === 'published' ? '' : ' status--muted' ?>"><?= $it['status'] === 'published' ? 'Yayında' : 'Taslak' ?></span></td>
        <td class="muted"><?= e($editors[$it['updated_by']] ?? '—') ?></td>
        <td class="muted"><?= e(date_tr((int) $it['updated_at'])) ?></td>
        <td class="act"><a class="icon-btn icon-btn--sm" href="<?= e($it['url']) ?>" aria-label="Görüntüle" title="Görüntüle"><?= icon('eye') ?></a><a class="icon-btn icon-btn--sm" href="<?= e(url('panel/oge/' . $it['id'])) ?>" aria-label="Düzenle" title="Düzenle"><?= icon('pencil') ?></a></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>
<?php if ($pages > 1): ?>
<nav class="pager" aria-label="Sayfalar">
  <?php if ($page > 1): ?><a href="<?= e($qs($page - 1)) ?>" aria-label="Önceki">←</a><?php endif ?>
  <span class="is-on"><?= $page ?> / <?= $pages ?></span>
  <?php if ($page < $pages): ?><a href="<?= e($qs($page + 1)) ?>" aria-label="Sonraki">→</a><?php endif ?>
</nav>
<?php endif ?>
<?php else: ?>
<div class="empty"><?= icon('box') ?><h2>Öğe bulunamadı</h2><p><?= Catalog::categories() ? 'Filtreleri değiştir ya da yeni bir öğe ekle.' : 'Önce bir kategori ve şablon oluşturmalısın.' ?></p>
  <a class="btn" href="<?= e(Catalog::categories() ? url('panel/oge/yeni') : url('panel/kategori/yeni')) ?>"><?= Catalog::categories() ? 'Yeni öğe' : 'Yeni kategori' ?></a></div>
<?php endif ?>
