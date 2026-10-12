<?php
declare(strict_types=1);

/**
 * Görsel işleme: Imagick varsa o, yoksa GD.
 * Her yükleme birkaç genişlikte WebP (destekleniyorsa AVIF de) ve küçük bir önizleme olarak üretilir;
 * özgün dosya saklanmaz (yeniden kodlama, görsele gizlenmiş içerikleri de temizler).
 */
final class Images
{
    public const THUMB = 160;
    private const MAX_PIXELS = 50_000_000;
    private const MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'image/heic', 'image/heif'];

    public static function engine(): ?string
    {
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            return 'imagick';
        }
        return function_exists('imagecreatetruecolor') ? 'gd' : null;
    }

    public static function supports(string $format): bool
    {
        static $cache = [];
        if (isset($cache[$format])) {
            return $cache[$format];
        }
        $ok = false;
        if (self::engine() === 'imagick') {
            try {
                $ok = in_array(strtoupper($format), \Imagick::queryFormats(strtoupper($format)), true);
            } catch (Throwable) {
            }
        }
        if (!$ok && function_exists('gd_info')) {
            $ok = match ($format) {
                'webp' => function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false),
                'avif' => function_exists('imageavif') && (gd_info()['AVIF Support'] ?? false),
                default => false,
            };
        }
        return $cache[$format] = $ok;
    }

    public static function widths(): array
    {
        $w = array_filter(array_map('intval', explode(',', Settings::get('gorsel_genislik'))), static fn($n) => $n >= 64 && $n <= 2400);
        sort($w);
        return array_values(array_unique($w)) ?: [320, 640, 800];
    }

    public static function maxBytes(): int
    {
        $ini = static function (string $k): int {
            $v = trim((string) ini_get($k));
            if ($v === '' || $v === '0' || $v === '-1') {
                return PHP_INT_MAX;
            }
            $n = (int) $v;
            return match (strtolower(substr($v, -1))) {
                'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n,
            };
        };
        return min(max(1, Settings::int('gorsel_mb')) * 1024 * 1024, $ini('upload_max_filesize'), $ini('post_max_size'));
    }

    /** $_FILES girdisini doğrular ve işler; images tablosuna eklenecek satırı döner */
    public static function fromUpload(array $file, int $itemId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ($file['error'] ?? 0) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Dosya çok büyük.',
                UPLOAD_ERR_NO_FILE => 'Dosya seçilmedi.',
                UPLOAD_ERR_PARTIAL => 'Yükleme yarıda kaldı, tekrar dene.',
                default => 'Yükleme başarısız oldu.',
            });
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp) && !(defined('KUNYE_TEST') && is_file($tmp))) {
            throw new RuntimeException('Geçersiz yükleme.');
        }
        return self::process($tmp, $itemId);
    }

    public static function process(string $path, int $itemId): array
    {
        $size = (int) filesize($path);
        if ($size <= 0 || $size > self::maxBytes()) {
            throw new RuntimeException('Dosya boyutu en fazla ' . round(self::maxBytes() / 1048576) . ' MB olabilir.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if (!in_array($mime, self::MIMES, true)) {
            throw new RuntimeException('Yalnızca JPG, PNG, WebP, GIF, AVIF ya da HEIC görseller yüklenebilir.');
        }
        $engine = self::engine();
        if ($engine === null) {
            throw new RuntimeException('Sunucuda görsel işleme eklentisi (GD ya da Imagick) yok.');
        }
        if (in_array($mime, ['image/heic', 'image/heif'], true) && !($engine === 'imagick' && self::supports('heic'))) {
            throw new RuntimeException('Bu sunucu HEIC dosyalarını açamıyor; görseli JPG olarak yükle.');
        }
        $info = @getimagesize($path);
        if ($info && $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new RuntimeException('Görsel çözünürlüğü çok yüksek.');
        }
        @set_time_limit(120);
        if (str_starts_with((string) ini_get('memory_limit'), '128') || (int) ini_get('memory_limit') < 256) {
            @ini_set('memory_limit', '512M');
        }

        $dir = date('Y/m');
        $abs = KUNYE_UPLOADS . '/' . $dir;
        if (!is_dir($abs) && !@mkdir($abs, 0755, true)) {
            throw new RuntimeException('uploads/ klasörüne yazılamıyor.');
        }
        $base = $dir . '/' . $itemId . '-' . bin2hex(random_bytes(5));
        $avif = Settings::bool('gorsel_avif') && self::supports('avif');
        if (!self::supports('webp')) {
            throw new RuntimeException('Sunucu WebP üretemiyor.');
        }
        $qw = max(30, min(100, Settings::int('kalite_webp')));
        $qa = max(20, min(100, Settings::int('kalite_avif')));

        $made = $engine === 'imagick'
            ? self::withImagick($path, $base, $avif, $qw, $qa)
            : self::withGd($path, $mime, $base, $avif, $qw, $qa);

        $bytes = 0;
        foreach (glob(KUNYE_UPLOADS . '/' . $base . '-*') ?: [] as $f) {
            $bytes += (int) filesize($f);
        }
        return [
            'item_id' => $itemId,
            'file' => $base,
            'w' => $made['w'],
            'h' => $made['h'],
            'widths' => implode(',', $made['widths']),
            'avif' => $avif ? 1 : 0,
            'orig_bytes' => $size,
            'bytes' => $bytes,
            'created_at' => time(),
        ];
    }

    private static function targets(int $w): array
    {
        $out = [];
        foreach (self::widths() as $tw) {
            if ($tw < $w) {
                $out[] = $tw;
            }
        }
        $max = max(self::widths());
        if ($w <= $max) {
            $out[] = $w; // büyütme yok: özgün genişlik en büyük boy olur
        } else {
            $out[] = $max;
        }
        return array_values(array_unique($out));
    }

    private static function withGd(string $path, string $mime, string $base, bool $avif, int $qw, int $qa): array
    {
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if (!$src) {
            throw new RuntimeException('Görsel okunamadı (bozuk ya da desteklenmeyen biçim).');
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $o = (int) ($exif['Orientation'] ?? 1);
            $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($rot) {
                $r = imagerotate($src, $rot, 0);
                if ($r) {
                    unset($src);
                    $src = $r;
                }
            }
        }
        if (!imageistruecolor($src)) {
            imagepalettetotruecolor($src);
        }
        imagealphablending($src, true);
        imagesavealpha($src, true);
        $w = imagesx($src);
        $h = imagesy($src);
        $widths = self::targets($w);
        $save = static function ($im, string $file) use ($avif, $qw, $qa): void {
            imagesavealpha($im, true);
            if (!imagewebp($im, KUNYE_UPLOADS . '/' . $file . '.webp', $qw)) {
                throw new RuntimeException('WebP yazılamadı.');
            }
            if ($avif) {
                @imageavif($im, KUNYE_UPLOADS . '/' . $file . '.avif', $qa, 6);
            }
        };
        foreach ($widths as $tw) {
            $th = max(1, (int) round($h * $tw / $w));
            $im = $tw === $w ? $src : self::gdResize($src, $tw, $th);
            $save($im, $base . '-' . $tw);
            if ($im !== $src) {
                unset($im);
            }
        }
        // önizleme: 160×160 kutuya sığdır
        $s = min(self::THUMB / $w, self::THUMB / $h, 1);
        $t = self::gdResize($src, max(1, (int) round($w * $s)), max(1, (int) round($h * $s)));
        imagewebp($t, KUNYE_UPLOADS . '/' . $base . '-t.webp', $qw);
        unset($t);
        unset($src);
        return ['w' => $w, 'h' => $h, 'widths' => $widths];
    }

    private static function gdResize($src, int $w, int $h)
    {
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        return $dst;
    }

    private static function withImagick(string $path, string $base, bool $avif, int $qw, int $qa): array
    {
        try {
            $im = new \Imagick();
            $im->readImage($path);
            if ($im->getNumberImages() > 1) {
                $im = $im->coalesceImages();
                $im->setIteratorIndex(0);
                $first = $im->getImage();
                $im->clear();
                $im = $first;
            }
            if (method_exists($im, 'autoOrient')) {
                $im->autoOrient();
            }
            $im->setImageColorspace(\Imagick::COLORSPACE_SRGB);
            $im->stripImage();
            $w = $im->getImageWidth();
            $h = $im->getImageHeight();
            $widths = self::targets($w);
            $write = static function (\Imagick $img, string $file, string $fmt, int $q): void {
                $c = clone $img;
                $c->setImageFormat($fmt);
                $c->setImageCompressionQuality($q);
                $c->writeImage(KUNYE_UPLOADS . '/' . $file . '.' . $fmt);
                $c->clear();
            };
            foreach ($widths as $tw) {
                $c = clone $im;
                if ($tw !== $w) {
                    $c->resizeImage($tw, 0, \Imagick::FILTER_LANCZOS, 1);
                }
                $write($c, $base . '-' . $tw, 'webp', $qw);
                if ($avif) {
                    try {
                        $write($c, $base . '-' . $tw, 'avif', $qa);
                    } catch (Throwable) {
                    }
                }
                $c->clear();
            }
            $t = clone $im;
            $t->thumbnailImage(self::THUMB, self::THUMB, true);
            $write($t, $base . '-t', 'webp', $qw);
            $t->clear();
            $im->clear();
            return ['w' => $w, 'h' => $h, 'widths' => $widths];
        } catch (\ImagickException $e) {
            throw new RuntimeException('Görsel okunamadı (bozuk ya da desteklenmeyen biçim).');
        }
    }

    public static function delete(array $img): void
    {
        Db::exec('DELETE FROM images WHERE id = ?', [$img['id']]);
        // aynı dosyaları kullanan başka kayıt yoksa (örnek veri paylaşır) dosyaları sil
        if (!Db::val('SELECT 1 FROM images WHERE file = ?', [$img['file']])) {
            foreach (glob(KUNYE_UPLOADS . '/' . $img['file'] . '-*') ?: [] as $f) {
                @unlink($f);
            }
        }
    }

    public static function thumb(?array $img): ?string
    {
        return $img ? upload_url($img['file'] . '-t.webp') : null;
    }

    /** <picture> çıktısı; $sizes tarayıcıya gösterim genişliğini söyler */
    public static function picture(array $img, string $alt, string $sizes = '(max-width: 760px) 92vw, 460px', bool $lazy = true, string $extra = ''): string
    {
        $widths = array_map('intval', explode(',', $img['widths']));
        $set = static fn(string $ext) => implode(', ', array_map(static fn($w) => upload_url($img['file'] . '-' . $w . '.' . $ext) . ' ' . $w . 'w', $widths));
        $fallbackW = $widths[0];
        foreach ($widths as $w) {
            if ($w <= 640) {
                $fallbackW = $w;
            }
        }
        $h = (int) round($img['h'] * $fallbackW / max(1, $img['w']));
        $html = '<picture>';
        if ((int) $img['avif'] === 1) {
            $html .= '<source type="image/avif" srcset="' . e($set('avif')) . '" sizes="' . e($sizes) . '">';
        }
        $html .= '<source type="image/webp" srcset="' . e($set('webp')) . '" sizes="' . e($sizes) . '">';
        $html .= '<img src="' . e(upload_url($img['file'] . '-' . $fallbackW . '.webp')) . '" width="' . $fallbackW . '" height="' . $h . '" alt="' . e($alt) . '"'
            . ($lazy ? ' loading="lazy" decoding="async"' : ' fetchpriority="high"') . $extra . '>';
        return $html . '</picture>';
    }
}
