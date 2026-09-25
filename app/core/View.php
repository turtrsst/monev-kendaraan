<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Renderer view: template + layout.
 * Output escape menjadi tanggung jawab template via e() — kecuali
 * konten yang memang ditandai aman (|raw tidak ada; gunakan html_entity bila perlu).
 */
final class View
{
    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, $data + ['content' => $content]);
    }

    /** @param array<string,mixed> $data */
    public static function partial(string $template, array $data = []): string
    {
        $path = BASE_PATH . '/' . ltrim($template, '/');
        if (!str_ends_with($path, '.php')) {
            $path .= '.php';
        }
        if (!is_file($path)) {
            throw new \RuntimeException('View tidak ditemukan: ' . $template);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }

    /** Response HTML ringkas untuk controller. */
    public static function show(string $template, array $data = [], ?string $layout = 'app/views/layouts/app', int $status = 200): never
    {
        Response::html(self::render($template, $data, $layout), $status);
    }
}
