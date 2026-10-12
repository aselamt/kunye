<?php
declare(strict_types=1);

final class Text
{
    private const FOLD = [
        'İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g',
        'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o', 'Ç' => 'c', 'ç' => 'c',
        'Â' => 'a', 'â' => 'a', 'Î' => 'i', 'î' => 'i', 'Û' => 'u', 'û' => 'u',
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'å' => 'a', 'ã' => 'a', 'Á' => 'a', 'À' => 'a', 'Ä' => 'a', 'Å' => 'a', 'Ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'Í' => 'i', 'Ì' => 'i', 'Ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'Ó' => 'o', 'Ò' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ø' => 'o',
        'ú' => 'u', 'ù' => 'u', 'Ú' => 'u', 'Ù' => 'u', 'ñ' => 'n', 'Ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
        'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'Æ' => 'ae', 'Œ' => 'oe',
    ];

    /** Türkçe karakterlere duyarsız, küçük harfli, tek boşluklu biçim: "Kablosuz Şarj" → "kablosuz sarj" */
    public static function fold(string $s): string
    {
        $s = mb_strtolower(strtr($s, self::FOLD), 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    public static function slug(string $s, int $max = 80): string
    {
        $s = preg_replace('/[^a-z0-9]+/', '-', self::fold($s)) ?? '';
        $s = trim(substr(trim($s, '-'), 0, $max), '-');
        return $s;
    }

    /** Arama terimleri */
    public static function terms(string $q): array
    {
        $q = self::fold($q);
        $t = preg_split('/[\s,;]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_slice(array_values(array_unique($t)), 0, 6);
    }

    /** LIKE için kaçış */
    public static function like(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }

    /** Kelimeler (harf/rakam dizileri) */
    public static function words(string $s): array
    {
        return preg_split('/[^a-z0-9]+/', self::fold($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Kelime düzeyinde benzerlik. Harf düzeyinde bulanık eşleşme yapılmaz, bu yüzden
     * "OLED" ile "AMOLED" farklıdır; "IPS LCD" / "IPS-LCD", "Shield Glass 2.0" / "Xiaomi Shield Glass" benzerdir.
     */
    public static function similar(string $a, string $b): bool
    {
        $wa = self::words($a);
        $wb = self::words($b);
        if (!$wa || !$wb) {
            return false;
        }
        if (implode('', $wa) === implode('', $wb)) {
            return true; // yalnızca boşluk/noktalama/harf büyüklüğü farkı
        }
        $sa = array_unique($wa);
        $sb = array_unique($wb);
        if ($sa == $sb) {
            return true;
        }
        if (!array_diff($sa, $sb) || !array_diff($sb, $sa)) {
            return true; // biri diğerinin kelimelerini tümüyle içeriyor
        }
        // sayıları ayırıp yalnızca bir tarafta sayı varsa kelimeleri karşılaştır
        $isNum = static fn($w) => ctype_digit($w);
        $na = array_filter($sa, $isNum);
        $nb = array_filter($sb, $isNum);
        if (($na === []) !== ($nb === [])) {
            $aa = array_diff($sa, $na);
            $ab = array_diff($sb, $nb);
            $small = count($aa) <= count($ab) ? $aa : $ab;
            $big = $small === $aa ? $ab : $aa;
            if (count($small) >= 2 && !array_diff($small, $big)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Türkçe sayı girdisi: "6,9" → 6.9 · "1.234,5" → 1234.5 · "6.9" → 6.9 · "1.024" → 1024 · "2.000.000" → 2000000
     */
    public static function number(string $s): ?float
    {
        $s = trim(str_replace([' ', "\u{00A0}", '%'], '', $s));
        if ($s === '') {
            return null;
        }
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (substr_count($s, '.') > 1 || preg_match('/^-?\d{1,3}\.\d{3}$/', $s)) {
            $s = str_replace('.', '', $s); // Türkçe binlik ayırıcı: 1.024 → 1024 (ondalık için virgül)
        }
        if (!is_numeric($s)) {
            return null;
        }
        $f = (float) $s;
        return is_finite($f) ? $f : null;
    }

    /** URL'deki sayı: nokta ondalık ayırıcıdır */
    public static function urlNum(float $f): string
    {
        $s = rtrim(rtrim(sprintf('%.6F', $f), '0'), '.');
        return $s === '-0' ? '0' : $s;
    }

    public static function clean(string $s, int $max = 200): string
    {
        $s = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s));
        $s = (string) preg_replace('/\s+/u', ' ', $s);
        return mb_substr($s, 0, $max);
    }

    /** Arama sonucunda eşleşen parçayı vurgula (HTML döner, girdiyi kaçışlar) */
    public static function mark(string $text, array $terms): string
    {
        if (!$terms) {
            return e($text);
        }
        $folded = self::foldKeepLength($text);
        $ranges = [];
        foreach ($terms as $t) {
            $len = mb_strlen($t);
            $off = 0;
            while ($len > 0 && ($p = mb_strpos($folded, $t, $off)) !== false) {
                $ranges[] = [$p, $p + $len];
                $off = $p + $len;
            }
        }
        if (!$ranges) {
            return e($text);
        }
        sort($ranges);
        $out = '';
        $pos = 0;
        foreach ($ranges as [$s, $en]) {
            if ($s < $pos) {
                $s = $pos;
            }
            if ($en <= $s) {
                continue;
            }
            $out .= e(mb_substr($text, $pos, $s - $pos)) . '<mark>' . e(mb_substr($text, $s, $en - $s)) . '</mark>';
            $pos = $en;
        }
        return $out . e(mb_substr($text, $pos));
    }

    /** Karakter sayısını koruyan katlama (vurgulama konumları için) */
    private static function foldKeepLength(string $s): string
    {
        $out = '';
        foreach (mb_str_split($s) as $ch) {
            $f = mb_strtolower(strtr($ch, self::FOLD), 'UTF-8');
            $out .= mb_strlen($f) === 1 ? $f : $ch;
        }
        return $out;
    }
}
