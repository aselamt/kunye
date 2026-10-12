<?php
declare(strict_types=1);

/** HTML kaçışı */
function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Uygulama içi adres: url('telefon', ['marka' => 'xiaomi']) → /alt-klasor/telefon?marka=xiaomi */
function url(string $path = '', array $query = []): string
{
    $u = Request::base() . '/' . ltrim($path, '/');
    $q = Url::query($query);
    return $q === '' ? $u : $u . '?' . $q;
}

/** Tam adres (şema + alan adı) */
function abs_url(string $path = '', array $query = []): string
{
    return Request::origin() . url($path, $query);
}

function asset(string $path): string
{
    $f = KUNYE_ROOT . '/assets/' . $path;
    $v = is_file($f) ? substr(base_convert((string) filemtime($f), 10, 36), -5) : KUNYE_VERSION;
    return Request::base() . '/assets/' . $path . '?v=' . $v;
}

function upload_url(string $file): string
{
    return Request::base() . '/uploads/' . $file;
}

/** SVG ikon (assets/ikonlar.svg içindeki sembol) */
function icon(string $name, string $class = 'icon'): string
{
    static $sprite = null;
    $sprite ??= asset('ikonlar.svg');
    $name = preg_replace('/[^a-z0-9-]/', '', $name) ?: 'tag';
    return '<svg class="' . e($class) . '" aria-hidden="true"><use href="' . e($sprite) . '#i-' . $name . '"/></svg>';
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Türkçe sayı: 6,9 · 1320 · 0,5 */
function num(float|int|null $n, int $maxDec = 3): string
{
    if ($n === null) {
        return '';
    }
    $s = number_format((float) $n, $maxDec, ',', '');
    if (str_contains($s, ',')) {
        $s = rtrim(rtrim($s, '0'), ',');
    }
    return $s === '-0' ? '0' : $s;
}

/** Sayaç: 1.204 */
function count_fmt(int $n): string
{
    return number_format($n, 0, ',', '.');
}

function date_tr(int $ts, bool $time = false): string
{
    static $ay = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    $s = date('j', $ts) . ' ' . $ay[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    return $time ? $s . ', ' . date('H:i', $ts) : $s;
}

function ago(int $ts): string
{
    $d = time() - $ts;
    return match (true) {
        $d < 60 => 'şimdi',
        $d < 3600 => intdiv($d, 60) . ' dk',
        $d < 86400 => intdiv($d, 3600) . ' sa',
        $d < 86400 * 30 => intdiv($d, 86400) . ' g',
        default => date_tr($ts),
    };
}

function initial(string $s): string
{
    return mb_strtoupper(mb_substr($s, 0, 1));
}

/** Panel ve formlarda kısa durum mesajları (oturumda bir kez gösterilir) */
function flash(?string $msg = null, string $type = 'ok'): ?array
{
    Session::start();
    if ($msg !== null) {
        $_SESSION['_flash'] = ['m' => $msg, 't' => $type];
        return null;
    }
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $f;
}

function old(string $key, string $default = ''): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : $default;
}

function plural_ozellik(int $n): string
{
    return $n . ' özellik';
}
