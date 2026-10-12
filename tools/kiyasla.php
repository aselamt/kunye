<?php
/**
 * Geliştirme aracı: geçici bir veritabanına N öğe yazar ve liste/filtre/arama sürelerini ölçer.
 * Çalıştır: php tools/kiyasla.php 5000
 */
declare(strict_types=1);

$n = max(100, (int) ($argv[1] ?? 5000));
$tmp = sys_get_temp_dir() . '/kunye-kiyas-' . bin2hex(random_bytes(4));
mkdir("$tmp/data", 0777, true);
mkdir("$tmp/uploads", 0777, true);
define('KUNYE_DATA', "$tmp/data");
define('KUNYE_UPLOADS', "$tmp/uploads");
require dirname(__DIR__) . '/app/bootstrap.php';
$_SERVER['HTTP_HOST'] = 'kiyas.test';
$_SERVER['SCRIPT_NAME'] = '/index.php';

Db::use(Db::open(Db::path()));
Schema::create();
Seeder::run();
$cat = Catalog::categoryBySlug('telefon');
$tpl = Catalog::template((int) $cat['id'])['props'];
$seedIds = array_map('intval', Db::col('SELECT id FROM items WHERE category_id = ?', [$cat['id']]));

$t = microtime(true);
Db::tx(static function () use ($n, $cat, $tpl, $seedIds) {
    $now = time();
    for ($i = 0; $i < $n; $i++) {
        $src = $seedIds[$i % count($seedIds)];
        $name = 'Model ' . $i . ' ' . bin2hex(random_bytes(2));
        $id = Db::insert('items', ['category_id' => $cat['id'], 'name' => $name, 'slug' => Text::slug($name), 'status' => 'published',
            'search' => Text::fold($name), 'created_at' => $now - $i * 60, 'updated_at' => $now]);
        // örnek öğenin değerlerini kopyala, sayıları biraz oynat
        Db::exec('INSERT INTO vals (item_id, prop_id, num, option_id, bool, text, sort)
            SELECT ?, prop_id, CASE WHEN num IS NOT NULL THEN num + (? % 7) ELSE NULL END, option_id, bool, text, sort FROM vals WHERE item_id = ?', [$id, $i, $src]);
    }
});
printf("%d öğe yazıldı: %.1f sn\n", $n, microtime(true) - $t);
Catalog::touch((int) $cat['id']);
$cat = Catalog::category((int) $cat['id']);
$props = Catalog::propsBySlug((int) $cat['id']);
$filterable = array_values(array_filter($tpl, static fn($p) => $p['filterable'] && $p['type'] !== 'text'));

$t = microtime(true);
$idx = Facets::index($cat);
printf("Dizin oluşturma (ilk istek, önbellek yok): %.0f ms · %d değer satırı\n", (microtime(true) - $t) * 1000, (int) Db::val('SELECT COUNT(*) FROM vals'));
Facets::forget();
$t = microtime(true);
$idx = Facets::index($cat);
printf("Dizini önbellekten okuma: %.1f ms\n", (microtime(true) - $t) * 1000);

foreach ([[], ['marka' => 'samsung'], ['dahili-depolama' => '256..1024', 'marka' => 'apple,xiaomi'], ['kapasite' => '6000..', 'kablosuz-sarj' => 'var', 'ekran-ozellikleri' => 'ltpo']] as $q) {
    $t = microtime(true);
    $f = Facets::parse($props, $q, $idx);
    $r = Facets::run($cat, $idx, $f, $filterable, 'yeni');
    $cards = Catalog::cards(array_slice($r['ids'], 0, 24));
    printf("Liste %-60s %5d sonuç · %.1f ms\n", '?' . Url::query($q), count($r['ids']), (microtime(true) - $t) * 1000);
}
$t = microtime(true);
Search::suggest('model 12');
printf("Arama önerisi: %.1f ms\n", (microtime(true) - $t) * 1000);

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
Db::use(null);
foreach ($it as $f) {
    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($tmp);
