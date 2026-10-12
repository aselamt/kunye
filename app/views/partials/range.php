<?php
/** Sayı filtresi: dağılım çubukları + aralık çubuğu (JS) + en az/en çok kutuları. @var array $f */
$p = $f['prop'];
$vals = $f['values'];
$keys = array_map('floatval', array_keys($vals));
$n = count($keys);
$min = $f['filter']['min'] ?? null;
$max = $f['filter']['max'] ?? null;
$lo = 0;
$hi = max(0, $n - 1);
if ($f['active'] && $n) {
    $lo = $n - 1;
    foreach ($keys as $i => $k) {
        if ($min === null || $k >= $min - 1e-9) {
            $lo = $i;
            break;
        }
    }
    $hi = 0;
    foreach ($keys as $i => $k) {
        if ($max === null || $k <= $max + 1e-9) {
            $hi = $i;
        }
    }
}
$pct = static fn(int $i) => $n > 1 ? round($i / ($n - 1) * 100, 2) : ($i ? 100 : 0);
$unit = $p['unit'];
// dağılım: 30'dan az farklı değer varsa her değer bir çubuk, yoksa 24 kova
$bars = [];
if ($n) {
    $buckets = $n <= 30 ? $n : 24;
    $counts = array_values($vals);
    for ($b = 0; $b < $buckets; $b++) {
        $from = intdiv($b * $n, $buckets);
        $to = max($from, intdiv(($b + 1) * $n, $buckets) - 1);
        $bars[] = [array_sum(array_slice($counts, $from, $to - $from + 1)), $to >= $lo && $from <= $hi && $f['active']];
    }
}
$peak = $bars ? max(array_column($bars, 0)) : 1;
?>
<div class="range" data-range data-values="<?= e(implode(',', array_keys($vals))) ?>">
  <?php if ($n > 1): ?>
  <div class="hist" aria-hidden="true">
    <?php foreach ($bars as [$c, $on]): ?><span<?= $on ? ' class="on"' : '' ?> style="height:<?= max(6, (int) round($c / max(1, $peak) * 100)) ?>%"></span><?php endforeach ?>
  </div>
  <div class="range__track js-only" style="--from:<?= $pct($lo) ?>%;--to:<?= $pct($hi) ?>%">
    <span class="range__fill"></span>
    <input type="range" min="0" max="<?= $n - 1 ?>" step="1" value="<?= $lo ?>" aria-label="<?= e($p['name']) ?> en az" data-lo>
    <input type="range" min="0" max="<?= $n - 1 ?>" step="1" value="<?= $hi ?>" aria-label="<?= e($p['name']) ?> en çok" data-hi>
  </div>
  <div class="range__scale"><span><?= e(num($keys[0]) . ($unit !== '' ? ' ' . $unit : '')) ?></span><span><?= e(num($keys[$n - 1]) . ($unit !== '' ? ' ' . $unit : '')) ?></span></div>
  <?php endif ?>
  <div class="range__inputs">
    <label><input name="<?= e($p['slug']) ?>[min]" value="<?= $min !== null ? e(num($min)) : '' ?>" placeholder="<?= $n ? e(num($keys[0])) : 'en az' ?>" inputmode="decimal" aria-label="En az" data-min><?= e($unit) ?></label>
    <span>–</span>
    <label><input name="<?= e($p['slug']) ?>[max]" value="<?= $max !== null ? e(num($max)) : '' ?>" placeholder="<?= $n ? e(num($keys[$n - 1])) : 'en çok' ?>" inputmode="decimal" aria-label="En çok" data-max><?= e($unit) ?></label>
  </div>
</div>
