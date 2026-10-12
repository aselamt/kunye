<?php /** @var int $code @var string $title @var string $message */ ?>
<div class="wrap err">
  <b><?= $code ?></b>
  <h1><?= e($title) ?></h1>
  <p><?= e($message !== '' ? $message : match ($code) {
      404 => 'Aradığın sayfa taşınmış ya da hiç var olmamış olabilir.',
      403 => 'Bu sayfayı görmek için yetkin yok.',
      default => 'Lütfen biraz sonra tekrar dene.',
  }) ?></p>
  <a class="btn btn--ghost" href="<?= e(url()) ?>">Ana sayfaya dön</a>
</div>
