<?php
declare(strict_types=1);

final class Installer
{
    public static function lockFile(): string
    {
        return KUNYE_DATA . '/kurulum.kilit';
    }

    public static function installed(): bool
    {
        return is_file(self::lockFile());
    }

    /** Sistem denetimleri: [['grup','ad','ayrinti','durum' (ok|wn|bad),'deger'], …] */
    public static function checks(): array
    {
        $c = [];
        $ok = static fn(bool $b) => $b ? 'ok' : 'bad';
        $c[] = ['Gereksinimler', 'PHP 8.2 ya da üstü', '', $ok(PHP_VERSION_ID >= 80200), PHP_VERSION];
        $sqlite = extension_loaded('pdo_sqlite');
        $ver = '';
        if ($sqlite) {
            try {
                $ver = (string) (new PDO('sqlite::memory:'))->query('select sqlite_version()')->fetchColumn();
            } catch (Throwable) {
                $sqlite = false;
            }
        }
        $c[] = ['Gereksinimler', 'PDO SQLite', 'Veritabanı', $sqlite && version_compare($ver, '3.24', '>=') ? 'ok' : 'bad', $sqlite ? $ver : 'yok'];
        $missing = array_filter(['mbstring', 'fileinfo', 'json'], static fn($e) => !extension_loaded($e));
        $c[] = ['Gereksinimler', 'mbstring · fileinfo · json', $missing ? 'Eksik: ' . implode(', ', $missing) : '', $ok(!$missing), $missing ? 'eksik' : 'var'];
        $engine = Images::engine();
        $fmts = [];
        if ($engine) {
            foreach (['webp', 'avif'] as $f) {
                if (Images::supports($f)) {
                    $fmts[] = strtoupper($f) === 'WEBP' ? 'WebP' : 'AVIF';
                }
            }
        }
        $c[] = ['Gereksinimler', 'Görsel işleme',
            match ($engine) {
                'imagick' => 'Imagick kullanılacak',
                'gd' => 'Imagick bulunamadı, GD kullanılacak',
                default => 'GD ya da Imagick eklentisini hPanel’den aç',
            } . (in_array('AVIF', $fmts, true) ? '' : ' · AVIF desteklenmiyor, yalnızca WebP üretilecek'),
            $engine && in_array('WebP', $fmts, true) ? (in_array('AVIF', $fmts, true) ? 'ok' : 'wn') : 'bad',
            $engine ? strtoupper($engine === 'gd' ? 'GD' : 'Imagick') . ($fmts ? ' · ' . implode(' · ', $fmts) : '') : 'yok'];
        $pretty = !str_contains($_SERVER['REQUEST_URI'] ?? '', 'index.php');
        $c[] = ['Gereksinimler', 'mod_rewrite', $pretty ? 'Güzel adresler çalışıyor' : 'Bu sayfa /kurulum adresiyle açılmıyorsa .htaccess dosyasını yüklediğinden emin ol', $pretty ? 'ok' : 'wn', $pretty ? 'var' : '?'];

        foreach (['data/' => [KUNYE_DATA, 'veritabanı, önbellek, günlük'], 'uploads/' => [KUNYE_UPLOADS, 'görseller']] as $label => [$dir, $what]) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $w = is_dir($dir) && is_writable($dir);
            $c[] = ['Yazma izinleri', '<code>' . $label . '</code>', $w ? $what : 'Klasör izinlerini 755 yap (Dosya Yöneticisi → İzinler)', $ok($w), $w ? 'yazılabilir' : 'yazılamıyor'];
        }
        [$state, $note] = self::protectionTest();
        $c[] = ['Yazma izinleri', '<code>data/</code> koruması', $note, $state, $state === 'ok' ? 'kapalı' : ($state === 'bad' ? 'AÇIK' : '?')];
        return $c;
    }

    /** data/ klasörünün dışarıdan okunamadığını kendi adresine istek atarak doğrular */
    public static function protectionTest(): array
    {
        if (!is_file(KUNYE_DATA . '/.htaccess')) {
            return ['bad', 'data/.htaccess dosyası eksik; paketteki dosyayı yükle'];
        }
        if (PHP_SAPI === 'cli-server') {
            return ['ok', 'Yerel geliştirme sunucusu yalnızca assets/ ve uploads/ klasörlerini sunar'];
        }
        $url = Request::origin() . Request::base() . '/data/koruma.txt';
        $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true, 'follow_location' => 0], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $body = @file_get_contents($url, false, $ctx);
        $status = 0;
        $headers = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
        foreach ($headers as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int) $m[1];
            }
        }
        if ($body === false && $status === 0) {
            return ['wn', 'Sunucu kendi adresine bağlanamadı; tarayıcıda /data/koruma.txt açılmamalı (403/404)'];
        }
        if ($status === 200 && str_contains((string) $body, 'koruma-testi')) {
            return ['bad', 'Dışarıdan okunabiliyor! .htaccess dosyalarının yüklendiğinden emin ol'];
        }
        return ['ok', 'Dışarıdan erişim ' . ($status ?: 'engellendi') . ' döndü'];
    }

    public static function ready(array $checks): bool
    {
        foreach ($checks as $c) {
            if ($c[3] === 'bad') {
                return false;
            }
        }
        return true;
    }

    /** Kurulumu yapar; hata listesi döner (boşsa başarılı) */
    public static function install(array $in, bool $sample): array
    {
        $errors = [];
        $site = Text::clean((string) ($in['site'] ?? ''), 60);
        $user = trim((string) ($in['kullanici'] ?? ''));
        $email = trim((string) ($in['eposta'] ?? ''));
        $pass = (string) ($in['sifre'] ?? '');
        if ($site === '') {
            $errors[] = 'Site adını yaz.';
        }
        if (!Auth::validUsername($user)) {
            $errors[] = 'Kullanıcı adı 3–32 karakter olmalı; harf, rakam, nokta, tire ve alt çizgi kullanılabilir.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-posta adresi geçersiz.';
        }
        if (mb_strlen($pass) < 10) {
            $errors[] = 'Şifre en az 10 karakter olmalı.';
        }
        if ($pass !== (string) ($in['sifre2'] ?? '')) {
            $errors[] = 'Şifreler aynı değil.';
        }
        if (!self::ready(self::checks())) {
            $errors[] = 'Önce kırmızı işaretli gereksinimleri gider.';
        }
        if ($errors) {
            return $errors;
        }
        foreach (['cache', 'sessions', 'logs'] as $d) {
            @mkdir(KUNYE_DATA . '/' . $d, 0755, true);
        }
        $pdo = Db::open(Db::path());
        Db::use($pdo);
        if (Db::val("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'users'") && Db::val('SELECT 1 FROM users LIMIT 1')) {
            return ['Bu klasörde önceki bir kurulumun veritabanı var. Kilidi kaldırdıysan data/kurulum.kilit dosyasını geri yükle; sıfırdan kurmak için data/kunye.sqlite dosyasını sil.'];
        }
        Schema::create();
        Settings::set(['site_adi' => $site]);
        Db::tx(static function () use ($user, $email, $pass) {
            Auth::create($user, $email ?: null, $pass, 'admin');
        });
        if ($sample) {
            @set_time_limit(300);
            Seeder::run();
        }
        file_put_contents(self::lockFile(), 'Kuruldu: ' . date('c') . "\nBu dosyayı silmek kurulum ekranını yeniden açar; önerilmez.\n");
        return [];
    }
}
