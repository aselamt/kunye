<?php
declare(strict_types=1);

/**
 * Örnek veri: Telefonlar (30) ve Akıllı Saatler (8).
 * Değerler gösterim amaçlıdır; gerçek ürün bilgisi olarak kullanılmamalıdır.
 * Görseller GD ile çizilip normal yükleme hattından geçirilir (WebP/AVIF).
 */
final class Seeder
{
    private static array $props = [];

    public static function run(): void
    {
        mt_srand(2026);
        Db::tx(static function () {
            $mobil = Db::insert('sections', ['name' => 'Mobil', 'sort' => 1]);
            $phone = self::category($mobil, 'Telefonlar', 'Telefon', 'telefon', 'phone', 1, self::PHONE_TEMPLATE);
            $watch = self::category($mobil, 'Akıllı Saatler', 'Akıllı Saat', 'akilli-saat', 'watch', 2, self::WATCH_TEMPLATE);
            $phoneImgs = self::images('phone', ['#2b2d31', '#e9e4dc', '#3d6b4f', '#2f4b7c', '#c8b7e8', '#d9822b']);
            $watchImgs = self::images('watch', ['#2b2d31', '#b9b2a6', '#e46a3a']);
            foreach (self::phones() as $i => $p) {
                self::item($phone, $p, $phoneImgs[$i % count($phoneImgs)], $i);
            }
            foreach (self::watches() as $i => $w) {
                self::item($watch, $w, $watchImgs[$i % count($watchImgs)], $i);
            }
            // panelde örnek olsun diye iki bekleyen öneri
            $uid = Auth::create('ornek_kullanici', null, bin2hex(random_bytes(16)), 'user');
            $user = Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
            $cat = Catalog::category($phone);
            $item = Catalog::itemBySlug($phone, 'xiaomi-17-ultra');
            $props = Catalog::template($phone, (int) $item['id'])['props'];
            $byName = array_column($props, 'id', 'name');
            $cur = Catalog::values((int) $item['id'], $props);
            $feat = $cur[$byName['Kamera Özellikleri']] ?? [];
            $feat[] = 'Lazer Odaklama';
            Suggestions::create($user, $cat, $item, $props, [
                $byName['Kapasite'] => '6300',
                $byName['Dayanıklılık'] => 'Shield Glass 2.0',
                $byName['Kamera Özellikleri'] => $feat,
            ], '', 'Resmi sayfada batarya 6300 mAh yazıyor; ekran camı da Shield Glass 2.0.', 'https://example.com/xiaomi-17-ultra');
            $tpl = Catalog::template($phone)['props'];
            $t = array_column($tpl, 'id', 'name');
            Suggestions::create($user, $cat, null, $tpl, [
                $t['Marka'] => 'Nothing', $t['Çıkış Yılı'] => '2026', $t['Ekran Boyutu'] => '6,77', $t['Ekran Teknolojisi'] => 'Oled',
                $t['Kapasite'] => '5500', $t['Dahili Depolama'] => '256', $t['Bellek (RAM)'] => '12',
            ], 'Nothing Phone (4)', 'Tanıtıldı, özellikleri basın bülteninden.', '');
        });
        foreach (Db::col('SELECT id FROM categories') as $cid) {
            Catalog::touch((int) $cid);
        }
    }

    /** Şablon: [grup, ikon, [[ad, tip, birim, kısa ad, bayraklar], …]] — bayraklar: f filtre, l liste, k öne çıkan, e alt başlık */
    private const PHONE_TEMPLATE = [
        ['Genel', 'info', [['Marka', 'single', '', '', 'fe'], ['Çıkış Yılı', 'number', '', 'Yıl', 'fe']]],
        ['Ekran', 'phone', [
            ['Ekran Boyutu', 'number', 'inç', 'Ekran', 'flk'], ['Ekran Teknolojisi', 'single', '', 'Panel', 'f'],
            ['Çözünürlük', 'text', 'piksel', '', ''], ['Yenileme Hızı', 'number', 'Hz', '', 'f'],
            ['Ekran Özellikleri', 'multi', '', '', 'f'], ['Dayanıklılık', 'single', '', 'Ekran camı', 'f'],
        ]],
        ['Batarya', 'battery', [
            ['Kapasite', 'number', 'mAh', 'Batarya', 'flk'], ['Hızlı Şarj', 'bool', '', '', 'f'],
            ['Hızlı Şarj Gücü', 'number', 'W', 'Şarj gücü', 'f'], ['Kablosuz Şarj', 'bool', '', '', 'f'],
        ]],
        ['Kamera', 'camera', [
            ['Ana Kamera', 'number', 'MP', 'Ana kamera', 'fk'], ['Optik Sabitleme (OIS)', 'bool', '', 'OIS', 'f'],
            ['Kamera Özellikleri', 'multi', '', '', 'f'],
        ]],
        ['Donanım', 'cpu', [['Yonga Seti', 'single', '', '', 'f'], ['Bellek (RAM)', 'number', 'GB', 'RAM', 'fl']]],
        ['Depolama', 'drive', [['Dahili Depolama', 'number', 'GB', 'Depolama', 'flk'], ['Hafıza Kartı', 'bool', '', '', 'f']]],
        ['Bağlantı', 'wifi', [['5G', 'bool', '', '', 'f'], ['Wi-Fi', 'single', '', '', 'f'], ['Bluetooth', 'single', '', '', 'f'], ['NFC', 'bool', '', '', 'f']]],
        ['Tasarım', 'layers', [
            ['Boyutlar', 'text', 'mm', '', ''], ['Ağırlık', 'number', 'g', '', 'f'],
            ['Suya Dayanıklılık', 'single', '', '', 'f'], ['Renkler', 'multi', '', '', 'f'],
        ]],
        ['Yazılım', 'code', [['İşletim Sistemi', 'single', '', '', 'f'], ['Arayüz', 'single', '', '', 'f']]],
    ];

    private const WATCH_TEMPLATE = [
        ['Genel', 'info', [['Marka', 'single', '', '', 'fe'], ['Çıkış Yılı', 'number', '', 'Yıl', 'fe']]],
        ['Ekran', 'watch', [['Ekran Boyutu', 'number', 'inç', 'Ekran', 'flk'], ['Ekran Teknolojisi', 'single', '', 'Panel', 'f'], ['Her Zaman Açık Ekran', 'bool', '', '', 'f']]],
        ['Kasa', 'box', [['Kasa Boyutu', 'number', 'mm', 'Kasa', 'flk'], ['Kasa Malzemesi', 'single', '', '', 'f'], ['Suya Dayanıklılık', 'single', '', '', 'f']]],
        ['Batarya', 'battery', [['Pil Ömrü', 'number', 'saat', 'Batarya', 'flk']]],
        ['Sağlık', 'heart', [['Sensörler', 'multi', '', '', 'f'], ['EKG', 'bool', '', '', 'fk']]],
        ['Bağlantı', 'wifi', [['GPS', 'bool', '', '', 'f'], ['LTE', 'bool', '', '', 'f'], ['NFC', 'bool', '', '', 'f']]],
        ['Yazılım', 'code', [['İşletim Sistemi', 'single', '', '', 'f']]],
    ];

    private static function category(int $section, string $name, string $singular, string $slug, string $icon, int $sort, array $tpl): int
    {
        $id = Db::insert('categories', ['section_id' => $section, 'name' => $name, 'singular' => $singular, 'slug' => $slug, 'icon' => $icon,
            'sort' => $sort, 'search' => Text::fold("$name $singular Mobil")]);
        self::$props[$id] = [];
        foreach ($tpl as $gi => [$gname, $gicon, $props]) {
            $gid = Db::insert('prop_groups', ['category_id' => $id, 'name' => $gname, 'icon' => $gicon, 'sort' => $gi + 1]);
            foreach ($props as $pi => [$pname, $type, $unit, $short, $flags]) {
                $pid = Db::insert('props', [
                    'category_id' => $id, 'group_id' => $gid, 'name' => $pname, 'short' => $short, 'slug' => Catalog::uniquePropSlug($id, $pname),
                    'type' => $type, 'unit' => $unit, 'sort' => $pi + 1,
                    'filterable' => (int) str_contains($flags, 'f'), 'in_list' => (int) str_contains($flags, 'l'),
                    'is_key' => (int) str_contains($flags, 'k'), 'in_eyebrow' => (int) str_contains($flags, 'e'),
                ]);
                self::$props[$id][$pname] = Catalog::castProp(Db::one('SELECT * FROM props WHERE id = ?', [$pid]));
            }
        }
        return $id;
    }

    private static function item(int $catId, array $data, array $img, int $i): void
    {
        $name = $data['_ad'];
        unset($data['_ad']);
        $created = time() - (86400 * (5 + $i * 2)) - mt_rand(0, 80000);
        $iid = Db::insert('items', [
            'category_id' => $catId, 'name' => $name, 'slug' => Catalog::uniqueItemSlug($catId, $name), 'status' => 'published',
            'fav_count' => mt_rand(0, 40), 'created_at' => $created, 'updated_at' => $created + 86400,
        ]);
        foreach ($data as $pname => $v) {
            $prop = self::$props[$catId][$pname] ?? null;
            if ($prop && $v !== null) {
                Catalog::setValue($iid, $prop, $v);
            }
        }
        Db::insert('images', ['item_id' => $iid, 'sort' => 0] + array_diff_key($img, ['item_id' => 1]));
        Catalog::refreshSearch($iid);
    }

    /** GD ile basit ürün çizimleri; görsel hattından geçirilir ve öğeler arasında paylaşılır */
    private static function images(string $kind, array $colors): array
    {
        $out = [];
        $tmp = tempnam(sys_get_temp_dir(), 'kny');
        foreach ($colors as $c) {
            [$w, $h] = $kind === 'phone' ? [900, 1500] : [900, 1200];
            $im = imagecreatetruecolor($w, $h);
            imagealphablending($im, false);
            imagesavealpha($im, true);
            imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
            imagealphablending($im, true);
            $rgb = sscanf($c, '#%02x%02x%02x');
            $body = imagecolorallocate($im, ...$rgb);
            $dark = imagecolorallocate($im, 18, 18, 20);
            $glass = imagecolorallocate($im, 38, 44, 58);
            $shine = imagecolorallocatealpha($im, 255, 255, 255, 105);
            if ($kind === 'phone') {
                self::round($im, 190, 40, 710, 1460, 80, $body);
                self::round($im, 210, 60, 690, 1440, 64, $dark);
                self::round($im, 222, 72, 678, 1428, 54, $glass);
                imagefilledpolygon($im, [222, 400, 678, 120, 678, 300, 222, 700], $shine);
                imagefilledellipse($im, 450, 110, 26, 26, $dark);
            } else {
                self::round($im, 330, 0, 570, 330, 40, $body);
                self::round($im, 330, 870, 570, 1200, 40, $body);
                self::round($im, 220, 260, 680, 940, 110, $dark);
                self::round($im, 250, 290, 650, 910, 90, $glass);
                imagefilledpolygon($im, [250, 600, 650, 330, 650, 450, 250, 760], $shine);
                self::round($im, 680, 520, 712, 640, 14, $body);
            }
            imagesavealpha($im, true);
            imagepng($im, $tmp);
            $out[] = Images::process($tmp, 0);
        }
        @unlink($tmp);
        return $out;
    }

    private static function round($im, int $x1, int $y1, int $x2, int $y2, int $r, int $col): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $col);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $col);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $col);
        }
    }

    private static function pick(array $pool, int $min, int $max): array
    {
        shuffle($pool);
        return array_slice($pool, 0, mt_rand($min, $max));
    }

    private static function phones(): array
    {
        // ad, marka, yıl, ekran, depolama, RAM, batarya, yonga, seviye (3 amiral, 2 üst, 1 orta)
        $list = [
            ['Xiaomi 17 Ultra', 'Xiaomi', 2026, 6.9, 512, 16, 6000, 'Snapdragon 8 Elite Gen 5', 3],
            ['Xiaomi 17 Pro', 'Xiaomi', 2026, 6.63, 256, 12, 6300, 'Snapdragon 8 Elite Gen 5', 3],
            ['Xiaomi 15T Pro', 'Xiaomi', 2025, 6.83, 512, 12, 5500, 'Dimensity 9400+', 2],
            ['Redmi Note 15 Pro+', 'Xiaomi', 2026, 6.83, 256, 12, 6500, 'Dimensity 7400', 1],
            ['Poco F8 Pro', 'Xiaomi', 2026, 6.59, 256, 12, 6500, 'Snapdragon 8 Elite', 2],
            ['Samsung Galaxy S26 Ultra', 'Samsung', 2026, 6.9, 512, 12, 5000, 'Snapdragon 8 Elite Gen 5', 3],
            ['Samsung Galaxy S26+', 'Samsung', 2026, 6.7, 256, 12, 4900, 'Exynos 2600', 3],
            ['Samsung Galaxy S26', 'Samsung', 2026, 6.3, 256, 12, 4300, 'Exynos 2600', 2],
            ['Samsung Galaxy A57', 'Samsung', 2026, 6.7, 256, 8, 5000, 'Exynos 1680', 1],
            ['Samsung Galaxy A27', 'Samsung', 2026, 6.7, 128, 6, 5000, 'Exynos 1380', 1],
            ['Samsung Galaxy Z Fold7', 'Samsung', 2025, 8.0, 512, 12, 4400, 'Snapdragon 8 Elite', 3],
            ['Apple iPhone 17 Pro Max', 'Apple', 2025, 6.9, 256, 12, 4832, 'A19 Pro', 3],
            ['Apple iPhone 17 Pro', 'Apple', 2025, 6.3, 256, 12, 3998, 'A19 Pro', 3],
            ['Apple iPhone 17', 'Apple', 2025, 6.3, 256, 8, 3692, 'A19', 2],
            ['Apple iPhone Air', 'Apple', 2025, 6.5, 256, 12, 3149, 'A19 Pro', 2],
            ['Vivo X300 Ultra', 'Vivo', 2026, 6.82, 512, 16, 6600, 'Snapdragon 8 Elite Gen 5', 3],
            ['Vivo X300 Pro', 'Vivo', 2025, 6.78, 512, 16, 6510, 'Dimensity 9500', 3],
            ['OnePlus 15', 'OnePlus', 2026, 6.78, 512, 16, 7300, 'Snapdragon 8 Elite Gen 5', 3],
            ['OnePlus 15R', 'OnePlus', 2026, 6.83, 256, 12, 7400, 'Snapdragon 8 Gen 5', 2],
            ['Oppo Find X9 Pro', 'Oppo', 2025, 6.78, 512, 16, 7500, 'Dimensity 9500', 3],
            ['Oppo Find N6', 'Oppo', 2026, 8.12, 512, 16, 6000, 'Snapdragon 8 Elite Gen 5', 3],
            ['Honor Magic8 Pro', 'Honor', 2025, 6.71, 512, 12, 6270, 'Snapdragon 8 Elite Gen 5', 3],
            ['Honor 400', 'Honor', 2025, 6.55, 256, 12, 6000, 'Snapdragon 7 Gen 4', 1],
            ['Huawei Pura 80 Ultra', 'Huawei', 2025, 6.8, 512, 16, 5700, 'Kirin 9020', 3],
            ['Huawei Mate 80 Pro', 'Huawei', 2025, 6.75, 512, 16, 5750, 'Kirin 9030', 3],
            ['Google Pixel 10 Pro', 'Google', 2025, 6.3, 256, 16, 4870, 'Tensor G5', 3],
            ['Google Pixel 10', 'Google', 2025, 6.3, 128, 12, 4970, 'Tensor G5', 2],
            ['Nothing Phone (3)', 'Nothing', 2025, 6.67, 256, 12, 5150, 'Snapdragon 8s Gen 4', 2],
            ['Motorola Edge 60 Pro', 'Motorola', 2025, 6.7, 256, 12, 6000, 'Dimensity 8350', 1],
            ['Realme GT 8 Pro', 'Realme', 2025, 6.79, 512, 16, 7000, 'Snapdragon 8 Elite Gen 5', 3],
        ];
        $ui = ['Xiaomi' => 'HyperOS 3', 'Samsung' => 'One UI 8', 'Apple' => null, 'Vivo' => 'OriginOS 6', 'OnePlus' => 'OxygenOS 16',
            'Oppo' => 'ColorOS 16', 'Honor' => 'MagicOS 10', 'Huawei' => null, 'Google' => 'Pixel UI', 'Nothing' => 'Nothing OS 4',
            'Motorola' => 'Hello UI', 'Realme' => 'Realme UI 7'];
        $glass = ['Xiaomi' => 'Xiaomi Shield Glass', 'Samsung' => 'Gorilla Armor 2', 'Apple' => 'Ceramic Shield 2', 'Huawei' => 'Kunlun Glass 2',
            'Honor' => 'NanoCrystal Shield'];
        $out = [];
        foreach ($list as [$name, $brand, $year, $screen, $storage, $ram, $bat, $chip, $tier]) {
            $apple = $brand === 'Apple';
            $os = $apple ? 'iOS 26' : ($brand === 'Huawei' ? 'HarmonyOS 5' : ($year === 2026 ? 'Android 16' : (mt_rand(0, 1) ? 'Android 16' : 'Android 15')));
            $tech = $apple ? 'Super Retina XDR OLED' : ($brand === 'Samsung' && $tier >= 2 ? 'Dynamic AMOLED 2X' : ($tier === 1 && mt_rand(0, 2) === 0 ? 'AMOLED' : 'OLED'));
            if ($name === 'Samsung Galaxy A27') {
                $tech = 'Super AMOLED';
            }
            $h = round(146 + ($screen - 6.1) * 21 + mt_rand(-10, 10) / 10, 1);
            $wdt = round(70 + ($screen - 6.1) * 9 + mt_rand(-5, 5) / 10, 1);
            $out[] = [
                '_ad' => $name,
                'Marka' => $brand, 'Çıkış Yılı' => $year, 'Ekran Boyutu' => $screen, 'Ekran Teknolojisi' => $tech,
                'Çözünürlük' => $tier === 3 ? (mt_rand(0, 1) ? '1440 × 3200' : '1320 × 2868') : '1080 × 2400',
                'Yenileme Hızı' => $tier === 1 && $brand === 'Samsung' ? 90 : ($brand === 'Realme' || $name === 'OnePlus 15' ? 165 : 120),
                'Ekran Özellikleri' => array_values(array_unique(array_merge($tier === 3 ? ['LTPO'] : [], self::pick(['HDR10+', 'Dolby Vision', 'Çoklu Dokunma', 'Always-on Display', '10 bit Renk Derinliği', 'Çerçevesiz Tasarım'], 2, 4)))),
                'Dayanıklılık' => $glass[$brand] ?? ($tier >= 2 ? 'Gorilla Glass Victus 2' : 'Gorilla Glass 7i'),
                'Kapasite' => $bat, 'Hızlı Şarj' => true,
                'Hızlı Şarj Gücü' => $apple ? 40 : [1 => 45, 2 => 67, 3 => [80, 90, 100, 120][mt_rand(0, 3)]][$tier],
                'Kablosuz Şarj' => $tier >= 2,
                'Ana Kamera' => $name === 'Samsung Galaxy S26 Ultra' ? 200 : ($apple ? 48 : ($tier === 1 ? [50, 108][mt_rand(0, 1)] : 50)),
                'Optik Sabitleme (OIS)' => $tier >= 2 || mt_rand(0, 1) === 1,
                'Kamera Özellikleri' => array_values(array_unique(array_merge(['Portre Modu', 'HDR', 'Gece Modu'], $tier === 3 ? ['Periskop Telefoto', '8K Video'] : [], self::pick(['Ultra Geniş Açı', 'Makro', 'Lazer Odaklama'], 1, 2)))),
                'Yonga Seti' => $chip, 'Bellek (RAM)' => $ram, 'Dahili Depolama' => $storage, 'Hafıza Kartı' => $tier === 1 && $brand !== 'Samsung' && mt_rand(0, 1) === 1,
                '5G' => true, 'Wi-Fi' => $tier === 3 ? 'Wi-Fi 7' : ($tier === 2 ? 'Wi-Fi 6E' : 'Wi-Fi 6'),
                'Bluetooth' => $tier === 3 ? '6.0' : ($tier === 2 ? '5.4' : '5.3'), 'NFC' => true,
                'Boyutlar' => num($h) . ' × ' . num($wdt) . ' × ' . num(round(7.4 + mt_rand(0, 14) / 10, 1)),
                'Ağırlık' => (int) round(165 + ($screen - 6.1) * 45 + ($bat - 5000) / 60 + mt_rand(-8, 8)),
                'Suya Dayanıklılık' => $tier === 1 ? (mt_rand(0, 1) ? 'IP65' : 'IP67') : (in_array($brand, ['Oppo', 'OnePlus', 'Realme', 'Vivo'], true) ? 'IP69' : 'IP68'),
                'Renkler' => self::pick(['Siyah', 'Beyaz', 'Mavi', 'Yeşil', 'Gri', 'Mor', 'Turuncu', 'Gümüş'], 2, 4),
                'İşletim Sistemi' => $os, 'Arayüz' => $ui[$brand],
            ];
        }
        return $out;
    }

    private static function watches(): array
    {
        $list = [
            ['Apple Watch Ultra 3', 'Apple', 2025, 1.98, 49, 'Titanyum', 42, 'watchOS 26'],
            ['Apple Watch Series 11', 'Apple', 2025, 1.96, 46, 'Alüminyum', 24, 'watchOS 26'],
            ['Samsung Galaxy Watch8 Classic', 'Samsung', 2025, 1.34, 46, 'Paslanmaz Çelik', 40, 'Wear OS 6'],
            ['Samsung Galaxy Watch8', 'Samsung', 2025, 1.47, 44, 'Alüminyum', 30, 'Wear OS 6'],
            ['Google Pixel Watch 4', 'Google', 2025, 1.4, 45, 'Alüminyum', 40, 'Wear OS 6'],
            ['Huawei Watch GT 6 Pro', 'Huawei', 2025, 1.47, 46, 'Titanyum', 504, 'HarmonyOS 5'],
            ['Xiaomi Watch S4', 'Xiaomi', 2025, 1.43, 47, 'Paslanmaz Çelik', 360, 'HyperOS 2'],
            ['Garmin Fenix 8', 'Garmin', 2024, 1.4, 47, 'Titanyum', 696, 'Garmin OS'],
        ];
        $out = [];
        foreach ($list as [$name, $brand, $year, $screen, $case, $mat, $hours, $os]) {
            $out[] = [
                '_ad' => $name, 'Marka' => $brand, 'Çıkış Yılı' => $year, 'Ekran Boyutu' => $screen,
                'Ekran Teknolojisi' => $brand === 'Apple' ? 'LTPO3 OLED' : 'AMOLED', 'Her Zaman Açık Ekran' => true,
                'Kasa Boyutu' => $case, 'Kasa Malzemesi' => $mat, 'Suya Dayanıklılık' => $brand === 'Apple' || $brand === 'Garmin' ? '10 ATM' : '5 ATM',
                'Pil Ömrü' => $hours,
                'Sensörler' => array_values(array_unique(array_merge(['Nabız', 'SpO2', 'İvmeölçer'], self::pick(['Cilt Sıcaklığı', 'Barometre', 'Pusula', 'Derinlik Ölçer'], 1, 3)))),
                'EKG' => in_array($brand, ['Apple', 'Samsung', 'Google'], true), 'GPS' => true, 'LTE' => $brand === 'Apple' || mt_rand(0, 1) === 1,
                'NFC' => true, 'İşletim Sistemi' => $os,
            ];
        }
        return $out;
    }
}
