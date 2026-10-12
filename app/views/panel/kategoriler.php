<?php /** @var array $cats @var array $counts @var array $all */ ?>
<?php if ($cats): ?>
<div class="dtable-wrap">
  <table class="dtable">
    <thead><tr><th>Kategori</th><th>Bölüm</th><th>Adres</th><th class="num">Özellik</th><th class="num">Öğe</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($cats as $c): ?>
      <tr>
        <td><a class="cell-item" href="<?= e(url('panel/kategori/' . $c['id'])) ?>"><span class="qpic"><?= icon($c['icon']) ?></span><span><b><?= e($c['name']) ?></b><small><?= e($c['singular']) ?></small></span></a></td>
        <td class="muted"><?= e($c['section'] ?? '—') ?></td>
        <td class="muted">/<?= e($c['slug']) ?></td>
        <td class="num"><?= (int) ($counts[$c['id']] ?? 0) ?></td>
        <td class="num"><?= count_fmt((int) $c['item_count']) ?><?php if (($all[$c['id']] ?? 0) > $c['item_count']): ?> <small class="muted">(+<?= (int) $all[$c['id']] - (int) $c['item_count'] ?> taslak)</small><?php endif ?></td>
        <td class="act"><a class="icon-btn icon-btn--sm" href="<?= e(url($c['slug'])) ?>" aria-label="Görüntüle" title="Görüntüle"><?= icon('eye') ?></a><a class="icon-btn icon-btn--sm" href="<?= e(url('panel/kategori/' . $c['id'])) ?>" aria-label="Şablonu düzenle" title="Şablonu düzenle"><?= icon('pencil') ?></a></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>
<p class="pfoot">Bölümler (ör. “Mobil”, “Ev”) yalnızca gruplama içindir; her kategorinin kendi şablonu vardır.</p>
<?php else: ?>
<div class="empty"><?= icon('grid') ?><h2>Henüz kategori yok</h2><p>Her kategorinin kendi özellik şablonu olur: ikonlu gruplar ve tipli özellikler.</p><a class="btn" href="<?= e(url('panel/kategori/yeni')) ?>">İlk kategoriyi oluştur</a></div>
<?php endif ?>
