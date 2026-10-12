<?php
/**
 * @var array $cat @var array $item @var array $groups (yalnızca dolu gruplar; her grup 'props' => [pid => özellik]) @var array $rows
 * @var array $idx @var array $images @var array $keyfacts @var array $eyebrow @var bool $isFav @var bool $published
 */
$slug = $cat['slug'];
$catUrl = url($slug);
$valueLink = static function (array $p, array $r) use ($catUrl, $idx, $slug): array {
    return [url($slug, Facets::valueQuery($p, $r)), Facets::countValue($idx, $p, $r)];
};
?>
<main class="wrap page">
  <nav class="crumbs" aria-label="Konum">
    <a href="<?= e(url()) ?>">Ana sayfa</a><span class="sep">/</span>
    <a href="<?= e($catUrl) ?>"><?= e($cat['name']) ?></a>
    <?php if ($eyebrow): ?><span class="sep">/</span><a href="<?= e($eyebrow[0]['url']) ?>"><?= e($eyebrow[0]['text']) ?></a><?php endif ?>
  </nav>

  <?php if (!$published): ?><div class="flash flash--bad"><?= icon('eye') ?> Bu öğe taslak; yalnızca editörler görebilir.</div><?php endif ?>

  <section class="hero">
    <div>
      <div class="hero__img" data-gallery>
        <?php if ($images): ?><?= Images::picture($images[0], $item['name'], '(max-width: 760px) 92vw, 460px', false) ?>
        <?php else: ?><span class="ph"><?= icon($cat['icon']) ?></span><?php endif ?>
      </div>
      <?php if (count($images) > 1): ?>
      <div class="hero__thumbs">
        <?php foreach ($images as $i => $img): ?>
        <a class="<?= $i === 0 ? 'is-on' : '' ?>" href="<?= e(upload_url($img['file'] . '-' . max(array_map('intval', explode(',', $img['widths']))) . '.webp')) ?>"
           data-pic="<?= e(Images::picture($img, $item['name'], '(max-width: 760px) 92vw, 460px', false)) ?>" aria-label="Görsel <?= $i + 1 ?>">
          <img src="<?= e(Images::thumb($img)) ?>" width="46" height="46" alt="" loading="lazy">
        </a>
        <?php endforeach ?>
      </div>
      <?php endif ?>
    </div>

    <div>
      <p class="eyebrow"><a href="<?= e($catUrl) ?>"><?= e($cat['singular']) ?></a><?php foreach ($eyebrow as $eb): ?> · <a href="<?= e($eb['url']) ?>"><?= e($eb['text']) ?></a><?php endforeach ?></p>
      <h1><?= e($item['name']) ?></h1>

      <?php if ($keyfacts): ?>
      <ul class="keyfacts<?= count($keyfacts) % 2 ? ' keyfacts--3' : '' ?>">
        <?php foreach ($keyfacts as $k): ?>
        <li><?= icon($k['icon']) ?><div><b><?= e($k['value']) ?></b><span><?= e($k['label']) ?></span></div></li>
        <?php endforeach ?>
      </ul>
      <?php endif ?>

      <div class="actions">
        <?= View::partial('partials/fav', ['id' => (int) $item['id'], 'on' => $isFav, 'cls' => 'btn', 'label' => true]) ?>
        <a class="btn btn--ghost" href="<?= e(url("$slug/{$item['slug']}/duzelt")) ?>" rel="nofollow"><?= icon('pencil', 'icon icon--sm') ?> Düzeltme öner</a>
        <?php if (Auth::is('editor')): ?><a class="btn btn--ghost" href="<?= e(url('panel/oge/' . $item['id'])) ?>"><?= icon('sliders', 'icon icon--sm') ?> Düzenle</a><?php endif ?>
      </div>
      <p class="meta-line">Son güncelleme: <?= e(date_tr((int) $item['updated_at'])) ?></p>
    </div>
  </section>

  <?php if ($groups): ?>
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
        <?php foreach ($g['props'] as $pid => $p): $r = $rows[$pid]; ?>
        <div class="spec"><dt><?= e($p['name']) ?></dt><dd>
          <?php if ($p['type'] === 'multi'): ?>
          <ul class="vlist">
            <?php foreach ($r as $row): [$href, $n] = $valueLink($p, $row); ?>
            <li><?php if ($p['clickable']): ?><a class="v" href="<?= e($href) ?>" data-n="<?= count_fmt($n) ?>"><?= e($row['oname']) ?></a><?php else: ?><?= e($row['oname']) ?><?php endif ?></li>
            <?php endforeach ?>
          </ul>
          <?php else:
              $row = $r[0];
              [$href, $n] = $valueLink($p, $row);
              $text = Catalog::display($p, Catalog::fromRows($p['type'], $r));
              $cls = 'v' . ($p['type'] === 'bool' ? ($row['bool'] ? ' v--yes' : ' v--no') : '');
              $ic = $p['type'] === 'bool' ? icon($row['bool'] ? 'yes' : 'no') : '';
              if ($p['clickable']): ?>
          <a class="<?= $cls ?>" href="<?= e($href) ?>" data-n="<?= count_fmt($n) ?>"><?= $ic ?><?= e($text) ?></a>
              <?php else: ?>
          <span class="<?= $cls ?> v--plain"><?= $ic ?><?= e($text) ?></span>
              <?php endif;
          endif ?>
        </dd></div>
        <?php endforeach ?>
      </dl>
    </section>
    <?php endforeach ?>
  </div>
  <?php else: ?>
  <div class="empty"><?= icon('info') ?><h2>Henüz özellik girilmemiş</h2><p>Bildiğin bilgileri ekleyerek katkıda bulunabilirsin.</p></div>
  <?php endif ?>

  <div class="suggest-edit">
    <span>Bir bilgi eksik ya da hatalı mı?</span>
    <a class="btn btn--ghost" href="<?= e(url("$slug/{$item['slug']}/duzelt")) ?>" rel="nofollow"><?= icon('pencil', 'icon icon--sm') ?> Düzeltme öner</a>
  </div>
</main>
