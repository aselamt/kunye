<?php
declare(strict_types=1);

final class View
{
    /** Şablonu çizer; $layout verilirse içerik $content olarak yerleşime aktarılır */
    public static function render(string $view, array $vars = [], ?string $layout = 'site'): string
    {
        $content = self::partial($view, $vars);
        if ($layout === null) {
            return $content;
        }
        return self::partial('layout-' . $layout, $vars + ['content' => $content]);
    }

    public static function partial(string $view, array $vars = []): string
    {
        $file = KUNYE_APP . '/views/' . $view . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Görünüm bulunamadı: $view");
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** Çiz, gönder; önbelleğe alınabilir sayfaları sakla */
    public static function send(string $view, array $vars = [], ?string $layout = 'site', int $code = 200): never
    {
        $html = self::render($view, $vars, $layout);
        if ($code === 200) {
            PageCache::store($html);
        }
        Response::html($html, $code);
    }
}
