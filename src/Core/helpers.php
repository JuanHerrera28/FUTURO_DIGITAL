<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Paginator;
use App\Core\Request;

if (!function_exists('e')) {
    /**
     * Escapa cualquier valor para mostrarlo en HTML.
     */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $base = (string) Config::get('app.url', '');

        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $base = $scheme . '://' . $host;
        }

        if ($path === '') {
            return rtrim($base, '/') . '/';
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $old = $GLOBALS['__old'] ?? [];

        return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
    }
}

if (!function_exists('error')) {
    function error(string $key): ?string
    {
        $errors = $GLOBALS['__errors'] ?? [];

        return is_array($errors) && isset($errors[$key]) ? (string) $errors[$key] : null;
    }
}

if (!function_exists('has_error')) {
    function has_error(string $key): bool
    {
        return error($key) !== null;
    }
}

if (!function_exists('auth_user')) {
    /** @return array<string,mixed>|null */
    function auth_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return Auth::can($permission);
    }
}

if (!function_exists('active')) {
    function active(string ...$prefixes): string
    {
        $path = Request::path();

        foreach ($prefixes as $prefix) {
            $prefix = '/' . trim($prefix, '/');
            if ($prefix === '/' && $path === '/') {
                return 'active';
            }
            if ($prefix !== '/' && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
                return 'active';
            }
        }

        return '';
    }
}

if (!function_exists('selected')) {
    function selected(mixed $value, mixed $expected): string
    {
        return (string) $value === (string) $expected ? 'selected' : '';
    }
}

if (!function_exists('checked')) {
    function checked(mixed $value): string
    {
        return in_array((string) $value, ['1', 'true', 'on', 'si', 'sí'], true) ? 'checked' : '';
    }
}

if (!function_exists('money')) {
    function money(mixed $value, int $decimals = 0): string
    {
        return '$ ' . number_format((float) $value, $decimals, ',', '.');
    }
}

if (!function_exists('fecha')) {
    function fecha(mixed $value): string
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return '—';
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? '—' : date('d/m/Y', $timestamp);
    }
}

if (!function_exists('fecha_hora')) {
    function fecha_hora(mixed $value): string
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return '—';
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? '—' : date('d/m/Y H:i', $timestamp);
    }
}

if (!function_exists('hora')) {
    function hora(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return substr((string) $value, 0, 5);
    }
}

if (!function_exists('porcentaje')) {
    function porcentaje(mixed $part, mixed $total, int $decimals = 1): float
    {
        $total = (float) $total;

        return $total <= 0 ? 0.0 : round(((float) $part / $total) * 100, $decimals);
    }
}

if (!function_exists('estado_label')) {
    function estado_label(?string $estado): string
    {
        if ($estado === null || $estado === '') {
            return '—';
        }

        return ucfirst(str_replace('_', ' ', $estado));
    }
}

if (!function_exists('badge_estado')) {
    function badge_estado(?string $estado): string
    {
        $estado = (string) $estado;

        $map = [
            'activo' => 'success', 'activa' => 'success', 'vigente' => 'success', 'registrado' => 'success',
            'pagado' => 'success', 'aprobada' => 'success', 'aprobado' => 'success', 'presente' => 'success',
            'inactivo' => 'secondary', 'inactiva' => 'secondary', 'cancelada' => 'secondary', 'cancelado' => 'secondary',
            'anulado' => 'danger', 'anulada' => 'danger', 'retirado' => 'danger', 'retirada' => 'danger',
            'perdida_inasistencia' => 'danger', 'rechazada' => 'danger', 'ausente' => 'danger',
            'pendiente' => 'warning', 'planificado' => 'warning', 'nueva' => 'warning', 'advertencia' => 'warning',
            'matricula_abierta' => 'primary', 'en_curso' => 'primary', 'abierta' => 'primary',
            'cerrado' => 'dark', 'finalizada' => 'dark', 'finalizado' => 'dark',
            'excusa' => 'info', 'tarde' => 'info', 'informativa' => 'info', 'critica' => 'danger',
            'atendida' => 'success', 'graduado' => 'success',
        ];

        $class = $map[$estado] ?? 'secondary';

        return '<span class="badge text-bg-' . $class . '">' . e(estado_label($estado)) . '</span>';
    }
}

if (!function_exists('generar_password')) {
    function generar_password(int $length = 10): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $symbols = '!@#$%*?';
        $all = $upper . $lower . $digits . $symbols;

        $password = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        while (count($password) < max(8, $length)) {
            $password[] = $all[random_int(0, strlen($all) - 1)];
        }

        for ($i = count($password) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$password[$i], $password[$j]] = [$password[$j], $password[$i]];
        }

        return implode('', $password);
    }
}

if (!function_exists('normalizar_documento')) {
    function normalizar_documento(?string $value): string
    {
        return mb_strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', (string) $value));
    }
}

if (!function_exists('dias_semana')) {
    /** @return array<int,string> */
    function dias_semana(): array
    {
        return [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
    }
}

if (!function_exists('iniciales')) {
    function iniciales(string $nombre): string
    {
        $parts = preg_split('/\s+/', trim($nombre)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_substr($part, 0, 1);
        }

        return mb_strtoupper($initials);
    }
}

if (!function_exists('str_limit')) {
    function str_limit(?string $value, int $limit = 60): string
    {
        $value = (string) $value;

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 1) . '…' : $value;
    }
}

if (!function_exists('query_url')) {
    /** @param array<string,mixed> $overrides */
    function query_url(array $overrides = []): string
    {
        $query = array_merge($_GET, $overrides);

        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                unset($query[$key]);
            }
        }

        $qs = http_build_query($query);

        return Request::path() . ($qs !== '' ? '?' . $qs : '');
    }
}

if (!function_exists('page_url')) {
    function page_url(int $page): string
    {
        return query_url(['page' => $page]);
    }
}

if (!function_exists('pagination')) {
    function pagination(Paginator $paginator): string
    {
        if (!$paginator->hasPages()) {
            return '<div class="text-muted small">' . $paginator->total . ' registro(s)</div>';
        }

        $html = '<nav aria-label="Paginación"><ul class="pagination pagination-sm mb-0">';
        $html .= '<li class="page-item ' . ($paginator->page <= 1 ? 'disabled' : '') . '"><a class="page-link" href="' . e(page_url(max(1, $paginator->page - 1))) . '">Anterior</a></li>';

        foreach ($paginator->window() as $page) {
            $html .= '<li class="page-item ' . ($page === $paginator->page ? 'active' : '') . '"><a class="page-link" href="' . e(page_url($page)) . '">' . $page . '</a></li>';
        }

        $html .= '<li class="page-item ' . ($paginator->page >= $paginator->totalPages() ? 'disabled' : '') . '"><a class="page-link" href="' . e(page_url(min($paginator->totalPages(), $paginator->page + 1))) . '">Siguiente</a></li>';
        $html .= '</ul></nav>';

        return $html;
    }
}

if (!function_exists('json_pretty')) {
    function json_pretty(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        if ($decoded === null) {
            return (string) $value;
        }

        return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
