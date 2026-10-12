<?php
declare(strict_types=1);

final class Settings
{
    public const DEFAULTS = [
        'site_adi' => 'künye',
        'arama_metni' => 'Ne arıyorsun?',
        'ana_baslik' => 'Her şeyin *tüm özellikleri*, tek aramada.',
        'duyuru' => '',
        'aciklama' => 'Her şeyin tüm özellikleri, tek tıkla filtrelenebilir.',
        'sayfa_basina' => '24',
        'ust_baglantilar' => '',
        'acik_kayit' => '1',
        'yeni_oge_onerisi' => '1',
        'kaynak_zorunlu' => '0',
        'gunluk_oneri' => '20',
        'gorsel_genislik' => '320,640,800',
        'gorsel_avif' => '1',
        'kalite_webp' => '80',
        'kalite_avif' => '55',
        'gorsel_mb' => '20',
        'surum' => '1',
        'dizin_zamani' => '0',
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = self::DEFAULTS;
            foreach (Db::pairs('SELECT k, v FROM settings') as $k => $v) {
                self::$cache[$k] = (string) $v;
            }
        }
        return self::$cache;
    }

    public static function get(string $k): string
    {
        return self::all()[$k] ?? '';
    }

    public static function int(string $k): int
    {
        return (int) self::get($k);
    }

    public static function bool(string $k): bool
    {
        return self::get($k) === '1';
    }

    public static function set(array $values): void
    {
        $st = Db::pdo()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v');
        foreach ($values as $k => $v) {
            $st->execute([$k, (string) $v]);
        }
        self::$cache = null;
    }

    public static function reset(): void
    {
        self::$cache = null;
    }

    /** İçerik değişti: sayfa önbelleğini geçersiz kıl */
    public static function bump(): void
    {
        Db::exec("INSERT INTO settings (k, v) VALUES ('surum', '2') ON CONFLICT(k) DO UPDATE SET v = CAST(v AS INTEGER) + 1");
        self::$cache = null;
    }

    /** Üst menü bağlantıları: "Hakkında=/hakkinda, GitHub=https://…" */
    public static function links(): array
    {
        $out = [];
        foreach (preg_split('/\s*,\s*/', self::get('ust_baglantilar'), -1, PREG_SPLIT_NO_EMPTY) as $pair) {
            [$label, $href] = array_pad(explode('=', $pair, 2), 2, '');
            $label = trim($label);
            $href = trim($href);
            if ($label === '' || $href === '') {
                continue;
            }
            if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
                $href = url($href);
            } elseif (!preg_match('#^https?://#i', $href)) {
                continue;
            }
            $out[] = ['label' => $label, 'href' => $href, 'ext' => !str_starts_with($href, Request::base() . '/')];
        }
        return $out;
    }
}
