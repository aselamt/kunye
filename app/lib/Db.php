<?php
declare(strict_types=1);

/** İnce PDO SQLite sarmalayıcı */
final class Db
{
    private static ?PDO $pdo = null;
    private static int $depth = 0;

    public static function path(): string
    {
        return KUNYE_DATA . '/kunye.sqlite';
    }

    public static function pdo(): PDO
    {
        return self::$pdo ??= self::open(self::path());
    }

    public static function use(?PDO $pdo): void
    {
        self::$pdo = $pdo;
        self::$depth = 0;
    }

    public static function open(string $file): PDO
    {
        $pdo = new PDO('sqlite:' . $file, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        $pdo->exec('PRAGMA busy_timeout = 8000');
        try {
            // WAL: okuyucular yazarı beklemez. Desteklenmeyen dosya sistemlerinde varsayılana döner.
            $mode = $pdo->query('PRAGMA journal_mode = WAL')->fetchColumn();
            if (strtolower((string) $mode) !== 'wal') {
                $pdo->exec('PRAGMA journal_mode = DELETE');
            }
        } catch (PDOException) {
            $pdo->exec('PRAGMA journal_mode = DELETE');
        }
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA temp_store = MEMORY');
        $pdo->exec('PRAGMA cache_size = -8000');
        return $pdo;
    }

    public static function q(string $sql, array $p = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $list = array_is_list($p);
        foreach ($p as $k => $v) {
            if (is_bool($v)) {
                $v = (int) $v;
            }
            $st->bindValue($list ? $k + 1 : ':' . ltrim((string) $k, ':'), $v, self::type($v));
        }
        $st->execute();
        return $st;
    }

    private static function type(mixed $v): int
    {
        return match (true) {
            is_int($v), is_bool($v) => PDO::PARAM_INT,
            $v === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    public static function all(string $sql, array $p = []): array
    {
        return self::q($sql, $p)->fetchAll();
    }

    public static function one(string $sql, array $p = []): ?array
    {
        $r = self::q($sql, $p)->fetch();
        return $r === false ? null : $r;
    }

    public static function val(string $sql, array $p = []): mixed
    {
        $r = self::q($sql, $p)->fetchColumn();
        return $r === false ? null : $r;
    }

    /** İlk sütunun listesi */
    public static function col(string $sql, array $p = []): array
    {
        return self::q($sql, $p)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** İlk sütun anahtar, ikinci sütun değer */
    public static function pairs(string $sql, array $p = []): array
    {
        return self::q($sql, $p)->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function exec(string $sql, array $p = []): int
    {
        return self::q($sql, $p)->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ')';
        self::q($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $p = []): int
    {
        $set = implode(',', array_map(static fn($c) => "$c = ?", array_keys($data)));
        return self::exec("UPDATE $table SET $set WHERE $where", [...array_values($data), ...$p]);
    }

    /** Placeholder listesi: in([1,2,3]) → '?,?,?' */
    public static function in(array $values): string
    {
        return $values ? rtrim(str_repeat('?,', count($values)), ',') : 'NULL';
    }

    /** İç içe çağrılabilen işlem */
    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        if (self::$depth === 0) {
            $pdo->exec('BEGIN IMMEDIATE');
        }
        self::$depth++;
        try {
            $r = $fn();
            self::$depth--;
            if (self::$depth === 0) {
                $pdo->exec('COMMIT');
            }
            return $r;
        } catch (Throwable $e) {
            self::$depth--;
            if (self::$depth === 0) {
                $pdo->exec('ROLLBACK');
            }
            throw $e;
        }
    }
}
