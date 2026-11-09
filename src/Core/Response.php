<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $path, int $status = 302): never
    {
        if (!preg_match('#^https?://#i', $path)) {
            $path = url($path);
        }

        header('Location: ' . $path, true, $status);
        exit;
    }

    public static function backPath(string $fallback = '/dashboard'): string
    {
        $referer = Request::referer();
        if ($referer === '') {
            return $fallback;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';

        if ($refererHost !== null && $refererHost !== $currentHost) {
            return $fallback;
        }

        $path = (string) (parse_url($referer, PHP_URL_PATH) ?: $fallback);
        $query = parse_url($referer, PHP_URL_QUERY);

        $basePath = (string) (parse_url((string) Config::get('app.url', ''), PHP_URL_PATH) ?: '');
        if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }

        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php')) ?: '/';
        }

        return $query ? $path . '?' . $query : $path;
    }

    public static function back(string $fallback = '/dashboard'): never
    {
        self::redirect(self::backPath($fallback));
    }

    /** @param array<string,mixed> $data */
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function download(string $filename, string $content, string $contentType = 'text/csv; charset=utf-8'): never
    {
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        echo $content;
        exit;
    }
}
