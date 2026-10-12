<?php
declare(strict_types=1);

final class Url
{
    /** Okunaklı sorgu dizesi: virgül, nokta ve iki nokta kodlanmaz (?depolama=256..1024&marka=xiaomi,apple) */
    public static function query(array $q): string
    {
        $out = [];
        foreach ($q as $k => $v) {
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            if (is_array($v)) {
                $v = implode(',', $v);
            }
            $out[] = rawurlencode((string) $k) . '=' . str_replace(['%2C', '%3A'], [',', ':'], rawurlencode((string) $v));
        }
        return implode('&', $out);
    }
}
