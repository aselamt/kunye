<?php /** @var array $rows @var array $item */
$labels = ['olustur' => 'Oluşturma', 'duzenle' => 'Düzenleme', 'gorsel' => 'Görsel', 'oneri' => 'Öneri', 'oneri-yeni' => 'Yeni öğe önerisi', 'sil' => 'Silme'];
?>
<?php if ($rows): ?>
<div class="dtable-wrap">
  <table class="dtable">
    <thead><tr><th>Tarih</th><th>Kim</th><th>İşlem</th><th>Ayrıntı</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td class="muted"><?= e(date_tr((int) $r['created_at'], true)) ?></td><td><?= e($r['username'] ?? 'sistem') ?></td><td><span class="tag"><?= e($labels[$r['action']] ?? $r['action']) ?></span></td><td><?= e($r['detail']) ?></td></tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="empty"><?= icon('history') ?><h2>Kayıt yok</h2><p>Bu öğede henüz bir değişiklik kaydedilmedi.</p></div>
<?php endif ?>
