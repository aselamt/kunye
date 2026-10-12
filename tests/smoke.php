<?php
/**
 * künye — HTTP duman testi: geçici bir veri klasörüyle PHP'nin yerleşik sunucusunu başlatır,
 * kurulumu tarayıcı gibi yapar ve sayfaları, korumaları, önbelleği dener.
 * Çalıştır: php tests/smoke.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/kunye-smoke-' . bin2hex(random_bytes(4));
mkdir("$tmp/data", 0777, true);
mkdir("$tmp/uploads", 0777, true);
copy("$root/data/.htaccess", "$tmp/data/.htaccess");
$port = random_int(20000, 40000);
$env = array_merge(getenv(), ['KUNYE_DATA' => "$tmp/data", 'KUNYE_UPLOADS' => "$tmp/uploads"]);
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root, "$root/index.php"], [1 => ['file', "$tmp/server.log", 'a'], 2 => ['file', "$tmp/server.log", 'a']], $pipes, $root, $env);
usleep(400000);

$base = "http://127.0.0.1:$port";
$jar = [];
function req(string $method, string $path, array $data = [], array $headers = []): array
{
    global $base, $jar;
    $h = $headers;
    if ($jar) {
        $h[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($jar), $jar));
    }
    $opts = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 60];
    if ($method === 'POST') {
        $h[] = 'Content-Type: application/x-www-form-urlencoded';
        $opts['content'] = http_build_query($data);
    }
    $opts['header'] = implode("\r\n", $h);
    $body = @file_get_contents($base . $path, false, stream_context_create(['http' => $opts]));
    $raw = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
    $status = 0;
    $hdr = [];
    foreach ($raw as $line) {
        if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $m)) {
            $status = (int) $m[1];
        } elseif (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $hdr[strtolower(trim($k))][] = trim($v);
            if (strtolower(trim($k)) === 'set-cookie' && preg_match('/^([^=]+)=([^;]*)/', trim($v), $c)) {
                if ($c[2] === '' || str_contains(strtolower($v), 'expires=thu, 01 jan 1970') || str_contains($v, 'Max-Age=0')) {
                    unset($jar[$c[1]]);
                } else {
                    $jar[$c[1]] = $c[2];
                }
            }
        }
    }
    return [$status, $hdr, (string) $body];
}
function csrf(string $html): string
{
    preg_match('/name="_csrf" value="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
}

$ok = 0;
$fail = 0;
function check(string $name, bool $cond, string $info = ''): void
{
    global $ok, $fail;
    $cond ? $ok++ : $fail++;
    echo ($cond ? '  ✓ ' : '  ✗ ') . $name . ($cond || $info === '' ? '' : "  ($info)") . "\n";
}

echo "\nKurulum\n";
[$s, $h] = req('GET', '/');
check('kurulmamışken ana sayfa kuruluma yönlendirir', $s === 302 && str_ends_with($h['location'][0] ?? '', '/kurulum'), "$s");
[$s, , $b] = req('GET', '/kurulum');
check('kurulum ekranı açılır ve denetimler geçer', $s === 200 && !str_contains($b, 'class="icon bad"'), (string) $s);
[$s] = req('POST', '/kurulum', ['site' => 'künye', 'kullanici' => 'yonetici', 'sifre' => 'duman-testi-1', 'sifre2' => 'duman-testi-1']);
check('CSRF belirteci olmadan kurulum reddedilir', $s === 419, (string) $s);
[$s, $h] = req('POST', '/kurulum', ['_csrf' => csrf($b), 'site' => 'künye', 'kullanici' => 'yonetici', 'sifre' => 'duman-testi-1', 'sifre2' => 'duman-testi-1', 'ornek' => '1']);
check('kurulum tamamlanır ve panele yönlendirir', $s === 303 && str_ends_with($h['location'][0] ?? '', '/panel'), (string) $s);
$adminJar = $jar;
[$s] = req('GET', '/kurulum');
check('kurulum kendini kilitler', $s === 404, (string) $s);

echo "\nPanel (yönetici)\n";
foreach (['/panel/ogeler', '/panel/oge/1', '/panel/kategoriler', '/panel/kategori/1', '/panel/oneriler', '/panel/degerler', '/panel/kullanicilar', '/panel/ayarlar'] as $p) {
    [$s, , $b] = req('GET', $p);
    check("$p açılır", $s === 200 && !str_contains($b, 'ters gitti'), (string) $s);
}
[$s, , $b] = req('GET', '/panel/oge/1');
[$s, $h] = req('POST', '/panel/oge/1', ['_csrf' => csrf($b), 'ad' => 'Xiaomi 17 Ultra', 'durum' => 'published', 'adres' => 'xiaomi-17-ultra', 'v' => ['5' => '6,95']]);
check('öğe formu kaydedilir', $s === 303, (string) $s);

echo "\nZiyaretçi\n";
$jar = [];
foreach (['/', '/telefon', '/telefon/xiaomi-17-ultra', '/ara?q=ultra', '/kategoriler', '/akilli-saat', '/telefon?dahili-depolama=256..1024&marka=xiaomi', '/robots.txt', '/sitemap.xml'] as $p) {
    [$s, , $b] = req('GET', $p);
    check("$p açılır", $s === 200 && !str_contains($b, 'ters gitti'), (string) $s);
}
[$s, $h, $b] = req('GET', '/api/oneri?q=sarj');
$j = json_decode($b, true);
check('öneri API: "sarj" → "Şarj"', $s === 200 && in_array('Kablosuz Şarj', array_column($j['degerler'] ?? [], 'ad'), true));
[$s, $h] = req('GET', '/telefon');
check('ziyaretçi sayfası önbellekten gelir', ($h['x-kunye-cache'][0] ?? '') === 'hit');
[$s, $h] = req('GET', '/telefon?' . 'marka%5B%5D=xiaomi&marka%5B%5D=apple&dahili-depolama%5Bmin%5D=256&dahili-depolama%5Bmax%5D=');
check('JS\'siz filtre formu kanonik adrese yönlenir', $s === 302 && str_ends_with($h['location'][0] ?? '', '/telefon?marka=apple,xiaomi&dahili-depolama=256..'), $h['location'][0] ?? '');
[$s] = req('GET', '/panel/ogeler');
check('panel ziyaretçiyi girişe yollar', $s === 303);

echo "\nKorumalar\n";
foreach (['/data/kunye.sqlite', '/data/koruma.txt', '/app/lib/Db.php', '/tests/run.php', '/README.md', '/.htaccess', '/index.php/../app/bootstrap.php'] as $p) {
    [$s, , $b] = req('GET', $p);
    check("$p dışarıya kapalı", $s === 404 || $s === 403, (string) $s);
}
[$s, $h] = req('GET', '/');
check('güvenlik başlıkları', str_contains($h['content-security-policy'][0] ?? '', "default-src 'self'") && ($h['x-content-type-options'][0] ?? '') === 'nosniff');
[$s, , $b] = req('GET', '/giris?donus=//kotu.example/x');
$t = csrf($b);
[$s, $h] = req('POST', '/giris?donus=//kotu.example/x', ['_csrf' => $t, 'kim' => 'yonetici', 'sifre' => 'duman-testi-1']);
check('açık yönlendirme engellenir', $s === 303 && ($h['location'][0] ?? '') === '/', $h['location'][0] ?? '');
[$s] = req('POST', '/favori/1', []);
check('CSRF belirteci olmadan POST reddedilir', $s === 419, (string) $s);
$jar = [];
[$s, , $b] = req('GET', '/giris');
$t = csrf($b);
for ($i = 0; $i < 10; $i++) {
    req('POST', '/giris', ['_csrf' => $t, 'kim' => 'yonetici', 'sifre' => 'yanlis-' . $i]);
}
[$s, , $b] = req('POST', '/giris', ['_csrf' => $t, 'kim' => 'yonetici', 'sifre' => 'duman-testi-1']);
check('çok sayıda hatalı girişten sonra hız sınırı', str_contains($b, 'Çok fazla deneme'));

proc_terminate($proc);
proc_close($proc);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) {
    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($tmp);
echo "\n" . ($fail ? "✗ $ok geçti, $fail başarısız\n" : "✓ $ok denetim geçti\n");
exit($fail ? 1 : 0);
