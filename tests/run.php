<?php
/**
 * künye — temel testler (harici kütüphane yok).
 * Çalıştır: php tests/run.php
 * Geçici bir klasörde boş bir veritabanı kurar, örnek veriyi yükler ve çekirdek davranışları dener.
 */
declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/kunye-test-' . bin2hex(random_bytes(4));
mkdir($tmp . '/data', 0777, true);
mkdir($tmp . '/uploads', 0777, true);
define('KUNYE_DATA', $tmp . '/data');
define('KUNYE_UPLOADS', $tmp . '/uploads');
define('KUNYE_TEST', true);
require dirname(__DIR__) . '/app/bootstrap.php';
Response::$testing = true;
$_SERVER['HTTP_HOST'] = 'ornek.test';
$_SERVER['SCRIPT_NAME'] = '/index.php';

$passed = 0;
$failed = [];
function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        echo "  ✓ $name\n";
    } catch (Throwable $e) {
        $failed[] = $name;
        echo "  ✗ $name\n    " . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
    }
}
function ok(mixed $cond, string $msg = 'beklenen koşul sağlanmadı'): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}
function eq(mixed $a, mixed $b): void
{
    if ($a !== $b) {
        throw new RuntimeException('beklenen ' . var_export($b, true) . ', gelen ' . var_export($a, true));
    }
}
function throws(callable $fn, string $class): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return;
        }
        throw new RuntimeException("$class bekleniyordu, " . $e::class . ' geldi: ' . $e->getMessage());
    }
    throw new RuntimeException("$class bekleniyordu, istisna yok");
}

echo "\nMetin\n";
test('Türkçe karakterlere duyarsız katlama', function () {
    eq(Text::fold('Kablosuz  Şarj'), 'kablosuz sarj');
    eq(Text::fold('İSTANBUL ılık ÇAĞ'), 'istanbul ilik cag');
    eq(Text::slug('Ekran / Gövde Oranı (%)'), 'ekran-govde-orani');
});
test('kelime düzeyinde benzerlik', function () {
    ok(!Text::similar('OLED', 'AMOLED'), 'OLED ile AMOLED farklı olmalı');
    ok(Text::similar('IPS LCD', 'IPS-LCD'));
    ok(Text::similar('Shield Glass 2.0', 'Xiaomi Shield Glass'));
    ok(!Text::similar('Wi-Fi 6', 'Wi-Fi 7'), 'Wi-Fi 6 ile 7 farklı olmalı');
    ok(!Text::similar('Snapdragon 8 Elite', 'Dimensity 9400'));
});
test('Türkçe sayı girdisi', function () {
    eq(Text::number('6,9'), 6.9);
    eq(Text::number('1.234,5'), 1234.5);
    eq(Text::number('2.000.000'), 2000000.0);
    eq(Text::number('6.9'), 6.9);
    eq(Text::number('1.024'), 1024.0);
    eq(Text::number('abc'), null);
    eq(num(6.9), '6,9');
    eq(num(1320.0), '1320');
});
test('arama vurgusu girdiyi kaçışlar', function () {
    eq(Text::mark('<b>Şarj</b>', ['sarj']), '&lt;b&gt;<mark>Şarj</mark>&lt;/b&gt;');
});

echo "\nGüvenlik\n";
test('açık yönlendirmeye karşı koruma', function () {
    Request::reset('', null);
    eq(Request::safeReturn('/telefon?marka=xiaomi', '/'), '/telefon?marka=xiaomi');
    foreach (['//evil.com', 'https://evil.com', '/\\evil.com', "/\tevil", 'javascript:alert(1)', '/a:b@evil', ''] as $bad) {
        eq(Request::safeReturn($bad, '/'), '/');
    }
    Request::reset('/katalog', null);
    eq(Request::safeReturn('/katalog/telefon', '/katalog/'), '/katalog/telefon');
    eq(Request::safeReturn('/telefon', '/katalog/'), '/katalog/');
    Request::reset('', null);
});
test('CSRF belirteci olmadan POST reddedilir', function () {
    $_SESSION = [];
    $_POST = [];
    throws(fn() => Csrf::check(), HttpError::class);
    $t = Csrf::token();
    $_POST['_csrf'] = $t;
    Csrf::check();
    $_SERVER['HTTP_ORIGIN'] = 'https://kotu.example';
    throws(fn() => Csrf::check(), HttpError::class);
    unset($_SERVER['HTTP_ORIGIN']);
});

echo "\nKurulum ve örnek veri\n";
Db::use(Db::open(Db::path()));
test('şema ve örnek veri', function () {
    Schema::create();
    Auth::create('yonetici', null, 'cok-gizli-sifre', 'admin');
    $t = microtime(true);
    Seeder::run();
    eq((int) Db::val('SELECT COUNT(*) FROM items'), 38);
    ok((int) Db::val('SELECT COUNT(*) FROM images') === 38);
    ok(microtime(true) - $t < 60, 'örnek veri çok yavaş');
});
test('görseller WebP (destekleniyorsa AVIF) olarak birkaç genişlikte üretilir', function () {
    $img = Db::one('SELECT * FROM images LIMIT 1');
    $widths = explode(',', $img['widths']);
    eq($widths, ['320', '640', '800']);
    foreach ($widths as $w) {
        ok(is_file(KUNYE_UPLOADS . '/' . $img['file'] . "-$w.webp"), "$w.webp yok");
        if ((int) $img['avif']) {
            ok(is_file(KUNYE_UPLOADS . '/' . $img['file'] . "-$w.avif"), "$w.avif yok");
        }
    }
    ok(is_file(KUNYE_UPLOADS . '/' . $img['file'] . '-t.webp'));
    $html = Images::picture($img, 'Ad "tırnak"');
    ok(str_contains($html, 'srcset=') && str_contains($html, '<picture>') && str_contains($html, '&quot;tırnak&quot;'));
});
test('yükleme doğrulaması: görsel olmayan dosya reddedilir', function () {
    $f = KUNYE_DATA . '/sahte.jpg';
    file_put_contents($f, "<?php echo 'x';");
    throws(fn() => Images::process($f, 1), RuntimeException::class);
    unlink($f);
});

echo "\nListeleme ve filtreler\n";
$cat = Catalog::categoryBySlug('telefon');
$props = Catalog::propsBySlug((int) $cat['id']);
test('adres kanonikleştirme', function () use ($props) {
    eq(Facets::canonical($props, ['marka' => ['xiaomi', 'apple'], 'dahili-depolama' => ['min' => '256', 'max' => '1.024']]),
        ['marka' => 'apple,xiaomi', 'dahili-depolama' => '256..1024']);
    eq(Facets::canonical($props, ['dahili-depolama' => '256..1024', 'marka' => 'xiaomi']), null);
    eq(Facets::canonical($props, ['ekran-boyutu' => ['min' => '6,5', 'max' => '']]), ['ekran-boyutu' => '6.5..']);
    eq(Facets::canonical($props, ['bilinmeyen' => 'x', 'sayfa' => '1']), []);
    eq(Facets::parseRange('512'), [512.0, 512.0]);
});
test('tam değer filtresi SQL ile aynı sonucu verir', function () use ($cat, $props) {
    $idx = Facets::index($cat);
    $f = Facets::parse($props, ['dahili-depolama' => '512'], $idx);
    $r = Facets::run($cat, $idx, $f, [], 'yeni');
    $sql = (int) Db::val("SELECT COUNT(*) FROM vals v JOIN items i ON i.id = v.item_id WHERE v.prop_id = ? AND v.num = 512 AND i.status = 'published'", [$props['dahili-depolama']['id']]);
    eq(count($r['ids']), $sql);
    ok($sql > 0);
});
test('tek seçimde VEYA, sayılar diğer filtrelere göre', function () use ($cat, $props) {
    $idx = Facets::index($cat);
    $tpl = array_values(array_filter(Catalog::template((int) $cat['id'])['props'], fn($p) => $p['filterable']));
    $one = Facets::run($cat, $idx, Facets::parse($props, ['marka' => 'samsung'], $idx), $tpl, 'yeni');
    $two = Facets::run($cat, $idx, Facets::parse($props, ['marka' => 'apple,samsung'], $idx), $tpl, 'yeni');
    ok(count($two['ids']) > count($one['ids']));
    // seçili markanın kutusunda diğer markalar da sayılmaya devam eder
    $opts = $one['facets'][$props['marka']['id']]['options'];
    ok(count($opts) > 1, 'diğer markalar görünmeli');
    eq($opts[0]['on'], true);
});
test('aralık filtresi ve var/yok', function () use ($cat, $props) {
    $idx = Facets::index($cat);
    $r = Facets::run($cat, $idx, Facets::parse($props, ['kapasite' => '6000..', 'kablosuz-sarj' => 'var'], $idx), [], 'az');
    $sql = (int) Db::val("SELECT COUNT(*) FROM items i WHERE i.status = 'published'
        AND EXISTS (SELECT 1 FROM vals v WHERE v.item_id = i.id AND v.prop_id = ? AND v.num >= 6000)
        AND EXISTS (SELECT 1 FROM vals v WHERE v.item_id = i.id AND v.prop_id = ? AND v.bool = 1)", [$props['kapasite']['id'], $props['kablosuz-sarj']['id']]);
    eq(count($r['ids']), $sql);
});
test('dizin önbelleği sürümle geçersiz olur', function () use ($cat) {
    $v = (int) $cat['version'];
    Catalog::touch((int) $cat['id']);
    $c2 = Catalog::category((int) $cat['id']);
    eq((int) $c2['version'], $v + 1);
    eq(Facets::index($c2)['v'], $v + 1);
});

echo "\nArama\n";
test('"sarj" yazınca "Şarj" bulunur', function () {
    $s = Search::suggest('sarj');
    ok(in_array('Kablosuz Şarj', array_column($s['degerler'], 'ad'), true), 'Kablosuz Şarj önerilmeli');
});
test('öğe, kategori ve değer önerileri', function () {
    $s = Search::suggest('ultra');
    ok(count($s['ogeler']) >= 4);
    $s = Search::suggest('akilli');
    eq($s['kategoriler'][0]['ad'], 'Akıllı Saatler');
    $s = Search::suggest('oled');
    ok(in_array('OLED', array_column($s['degerler'], 'ad'), true));
});

echo "\nÖneriler\n";
$editor = Db::one("SELECT * FROM users WHERE role = 'admin'");
Auth::setUser($editor);
test('düzeltme önerisi tek tek onaylanır, düzeltilerek onaylanabilir', function () use ($editor) {
    $s = Db::one("SELECT * FROM suggestions WHERE kind != 'new'");
    $changes = Db::all('SELECT c.*, p.name FROM changes c JOIN props p ON p.id = c.prop_id WHERE suggestion_id = ?', [$s['id']]);
    eq(count($changes), 3);
    $dec = [];
    foreach ($changes as $c) {
        $dec[$c['id']] = ['ok' => $c['name'] !== 'Kamera Özellikleri', 'final' => $c['name'] === 'Dayanıklılık' ? 'Xiaomi Shield Glass 2.0' : null];
    }
    Suggestions::review($s, $dec, 'Teşekkürler', $editor);
    $item = Catalog::item((int) $s['item_id']);
    $vals = Catalog::values((int) $item['id'], Catalog::template((int) $item['category_id'], (int) $item['id'])['props']);
    ok(in_array(6300.0, $vals, true), 'kapasite 6300 olmalı');
    ok(in_array('Xiaomi Shield Glass 2.0', $vals, true), 'editörün düzelttiği değer kullanılmalı');
    eq(Db::val('SELECT status FROM suggestions WHERE id = ?', [$s['id']]), 'done');
    eq((int) Db::val("SELECT COUNT(*) FROM changes WHERE suggestion_id = ? AND status = 'rejected'", [$s['id']]), 1);
    ok(Db::val("SELECT 1 FROM history WHERE item_id = ? AND action = 'oneri'", [$item['id']]));
});
test('yeni öğe önerisi onaylanınca öğe oluşur', function () use ($editor) {
    $s = Db::one("SELECT * FROM suggestions WHERE kind = 'new'");
    $dec = array_fill_keys(array_map('intval', Db::col('SELECT id FROM changes WHERE suggestion_id = ?', [$s['id']])), ['ok' => true, 'final' => null]);
    $id = Suggestions::review($s, $dec, '', $editor);
    $item = Catalog::item((int) $id);
    eq($item['name'], 'Nothing Phone (4)');
    eq($item['status'], 'published');
    // "Oled" önerisi var olan "OLED" seçeneğine bağlanır (büyük/küçük harf tek değer)
    $p = Catalog::propsBySlug((int) $item['category_id'])['ekran-teknolojisi'];
    eq(Catalog::values((int) $item['id'], [$p['id'] => $p])[$p['id']], 'OLED');
});
test('değişiklik olmayan öneri reddedilir; benzer değer bulunur', function () use ($editor) {
    $cat = Catalog::categoryBySlug('telefon');
    $item = Catalog::itemBySlug((int) $cat['id'], 'oneplus-15');
    $props = Catalog::template((int) $cat['id'], (int) $item['id'])['props'];
    throws(fn() => Suggestions::create($editor, $cat, $item, $props, [], '', '', ''), InvalidArgumentException::class);
    throws(fn() => Suggestions::create($editor, $cat, $item, $props, [], '', '', 'javascript:alert(1)'), InvalidArgumentException::class);
    $p = Catalog::propsBySlug((int) $cat['id'])['dayaniklilik'];
    $sim = array_column(Suggestions::similar($p, 'Gorilla Glass Victus'), 'name');
    ok(in_array('Gorilla Glass Victus 2', $sim, true));
});

echo "\nVeri temizliği ve şablon\n";
test('değer yeniden adlandırma ve birleştirme', function () {
    $cat = Catalog::categoryBySlug('telefon');
    $p = Catalog::propsBySlug((int) $cat['id'])['wi-fi'];
    $before = (int) Db::val('SELECT COUNT(*) FROM vals WHERE prop_id = ?', [$p['id']]);
    $a = (int) Db::val("SELECT id FROM options WHERE prop_id = ? AND name = 'Wi-Fi 6E'", [$p['id']]);
    $b = (int) Db::val("SELECT id FROM options WHERE prop_id = ? AND name = 'Wi-Fi 6'", [$p['id']]);
    Values::rename($p, (string) $a, 'WiFi 6E');
    eq(Db::val('SELECT name FROM options WHERE id = ?', [$a]), 'WiFi 6E');
    $list = Values::list($p);
    Values::merge($p, [(string) $a], (string) $b);
    ok(!Db::val('SELECT 1 FROM options WHERE id = ?', [$a]));
    eq((int) Db::val('SELECT COUNT(*) FROM vals WHERE prop_id = ?', [$p['id']]), $before);
    ok(count($list) >= 2);
});
test('şablon: tip değişikliği değerleri dönüştürür, arşiv değerleri silmez', function () {
    $cat = Catalog::categoryBySlug('telefon');
    $p = Catalog::propsBySlug((int) $cat['id'])['bluetooth'];
    $n = (int) Db::val('SELECT COUNT(*) FROM vals WHERE prop_id = ?', [$p['id']]);
    Templates::save((int) $cat['id'], [], [], [$p['id'] => ['type' => 'number']], []);
    eq(Db::val('SELECT type FROM props WHERE id = ?', [$p['id']]), 'number');
    eq((int) Db::val('SELECT COUNT(*) FROM vals WHERE prop_id = ? AND num IS NOT NULL', [$p['id']]), $n);
    Templates::save((int) $cat['id'], [], [], [$p['id'] => ['type' => 'single']], []);
    eq((int) Db::val('SELECT COUNT(*) FROM vals WHERE prop_id = ? AND option_id IS NOT NULL', [$p['id']]), $n);
    // dönüştürülemeyen değerler varsa tip değişmez
    $p2 = Catalog::propsBySlug((int) $cat['id'])['yonga-seti'];
    $notes = Templates::save((int) $cat['id'], [], [], [$p2['id'] => ['type' => 'number']], []);
    ok($notes && Db::val('SELECT type FROM props WHERE id = ?', [$p2['id']]) === 'single');
    Templates::save((int) $cat['id'], [], [], [$p['id'] => ['archive' => '1']], []);
    ok(!isset(Catalog::propsBySlug((int) $cat['id'])['bluetooth']));
    eq((int) Db::val('SELECT COUNT(*) FROM vals WHERE prop_id = ?', [$p['id']]), $n);
});
test('öğeye özel özellik ve gizleme', function () {
    $cat = Catalog::categoryBySlug('telefon');
    $item = Catalog::itemBySlug((int) $cat['id'], 'oppo-find-n6');
    $nfc = Catalog::propsBySlug((int) $cat['id'])['nfc'];
    Db::exec('INSERT INTO hidden (item_id, prop_id) VALUES (?, ?)', [$item['id'], $nfc['id']]);
    Catalog::touch((int) $cat['id']);
    $idx = Facets::index(Catalog::category((int) $cat['id']));
    ok(!isset($idx['bool'][$nfc['id']][(int) $item['id']]), 'gizli özellik dizinde olmamalı');
    $g = (int) Db::val('SELECT id FROM prop_groups WHERE category_id = ? AND item_id IS NULL LIMIT 1', [$cat['id']]);
    $pid = Db::insert('props', ['category_id' => $cat['id'], 'group_id' => $g, 'item_id' => $item['id'], 'name' => 'Menteşe', 'slug' => Catalog::uniquePropSlug((int) $cat['id'], 'Menteşe'), 'type' => 'text']);
    ok(isset(Catalog::template((int) $cat['id'], (int) $item['id'])['props'][$pid]));
    ok(!isset(Catalog::template((int) $cat['id'])['props'][$pid]), 'öğeye özel özellik şablonda görünmemeli');
});
test('ayrılmış adresler kategori olarak kullanılamaz', function () {
    throws(fn() => Templates::saveCategory(null, ['name' => 'Panel', 'slug' => 'panel']), InvalidArgumentException::class);
    $id = Templates::saveCategory(null, ['name' => 'Kulaklıklar', 'singular' => 'Kulaklık', 'icon' => 'headphones']);
    eq(Catalog::category($id)['slug'], 'kulaklik');
});

echo "\nOturum\n";
test('şifre özeti ve giriş', function () {
    $u = Db::one("SELECT * FROM users WHERE username = 'yonetici'");
    ok(str_starts_with($u['pass'], '$2y$') || str_starts_with($u['pass'], '$argon'));
    ok(Auth::attempt('yonetici', 'cok-gizli-sifre') !== null);
    ok(Auth::attempt('yonetici', 'yanlis') === null);
    ok(Auth::attempt('YONETICI', 'cok-gizli-sifre') !== null, 'kullanıcı adı büyük/küçük harfe duyarsız');
});

// temizlik
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
Db::use(null);
foreach ($it as $f) {
    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($tmp);

echo "\n" . ($failed ? "✗ $passed geçti, " . count($failed) . " başarısız\n" : "✓ $passed test geçti\n");
exit($failed ? 1 : 0);
