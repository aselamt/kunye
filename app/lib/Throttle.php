<?php
declare(strict_types=1);

/** Basit hız sınırı (giriş denemeleri, kayıt, öneri) */
final class Throttle
{
    public static function tooMany(string $key, int $max, int $window): bool
    {
        if (random_int(1, 50) === 1) {
            Db::exec('DELETE FROM throttle WHERE at < ?', [time() - 86400]);
        }
        return (int) Db::val('SELECT COUNT(*) FROM throttle WHERE k = ? AND at > ?', [$key, time() - $window]) >= $max;
    }

    public static function hit(string $key): void
    {
        Db::insert('throttle', ['k' => $key, 'at' => time()]);
    }

    public static function clear(string $key): void
    {
        Db::exec('DELETE FROM throttle WHERE k = ?', [$key]);
    }
}
