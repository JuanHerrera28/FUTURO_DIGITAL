<?php

declare(strict_types=1);

$user = auth_user();
$flashes = $flashes ?? [];
$path = App\Core\Request::path();

$nav = [
    'General' => [
        ['label' => 'Panel', 'url' => '/dashboard', 'icon' => 'speedometer2', 'perm' => 'dashboard.view'],
        ['label' => 'Mi portal', 'url' => '/portal', 'icon' => 'person-circle', 'perm' => 'portal.view'],
        ['label' => 'Alertas', 'url' => '/alerts', 'icon' => 'bell', 'perm' => 'alerts.view'],
    ],
    'Gestión académica' => [
        ['label' => 'Estudiantes', 'url' => '/students', 'icon' => 'people', 'perm' => 'students.view'],
        ['label' => 'Docentes', 'url' => '/teachers', 'icon' => 'person-badge', 'perm' => 'teachers.view'],
        ['label' => 'Programas', 'url' => '/programs', 'icon' => 'diagram-3', 'perm' => 'programs.view'],
        ['label' => 'Cursos', 'url' => '/courses', 'icon' => 'journal-bookmark', 'perm' => 'courses.view'],
        ['label' => 'Periodos', 'url' => '/periods', 'icon' => 'calendar3', 'perm' => 'periods.view'],
        ['label' => 'Aulas', 'url' => '/classrooms', 'icon' => 'door-open', 'perm' => 'classrooms.view'],
        ['label' => 'Grupos', 'url' => '/groups', 'icon' => 'collection', 'perm' => 'groups.view'],
    ],
    'Matrícula y evaluación' => [
        ['label' => 'Matrículas', 'url' => '/enrollments', 'icon' => 'person-plus', 'perm' => 'enrollment.view'],
        ['label' => 'Calificaciones', 'url' => '/grades', 'icon' => 'clipboard-check', 'perm' => 'grades.view'],
        ['label' => 'Asistencia', 'url' => '/attendance', 'icon' => 'calendar-check', 'perm' => 'attendance.view'],
    ],
    'Financiero' => [
        ['label' => 'Pagos y cartera', 'url' => '/payments', 'icon' => 'cash-coin', 'perm' => 'payments.view'],
    ],
    'Análisis' => [
        ['label' => 'Informes', 'url' => '/reports', 'icon' => 'graph-up-arrow', 'perm' => 'reports.view'],
    ],
    'Administración' => [
        ['label' => 'Usuarios', 'url' => '/users', 'icon' => 'person-gear', 'perm' => 'users.manage'],
        ['label' => 'Roles y permisos', 'url' => '/roles', 'icon' => 'shield-lock', 'perm' => 'roles.manage'],
        ['label' => 'Parámetros', 'url' => '/params', 'icon' => 'sliders', 'perm' => 'params.manage'],
        ['label' => 'Auditoría', 'url' => '/audit', 'icon' => 'clock-history', 'perm' => 'audit.view'],
    ],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(App\Core\Csrf::token()) ?>">
    <title><?= e($title ?? 'Futuro Digital') ?> · <?= e(App\Core\Param::string('nombre_sistema', 'Futuro Digital')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="app-body">
<div class="app-shell">
    <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
        <div class="sidebar-brand">
            <div class="brand-mark">FD</div>
            <div class="brand-text">
                <span class="brand-name">Futuro Digital</span>
                <span class="brand-sub">Sistema académico</span>
            </div>
            <button type="button" class="btn-close btn-close-white d-lg-none ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Cerrar"></button>
        </div>

        <nav class="sidebar-nav" id="sidebarLabel">
            <?php foreach ($nav as $group => $items): ?>
                <?php $visible = array_values(array_filter($items, static fn (array $item): bool => can($item['perm']))); ?>
                <?php if ($visible === []) { continue; } ?>
                <div class="nav-section"><?= e($group) ?></div>
                <?php foreach ($visible as $item): ?>
                    <a class="nav-link <?= active($item['url']) ?>" href="<?= e(url($item['url'])) ?>">
                        <i class="bi bi-<?= e($item['icon']) ?>"></i>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-foot">
            <div class="small text-white-50">Zona horaria</div>
            <div class="small text-white-75"><?= e(date('d/m/Y H:i')) ?> · Colombia</div>
        </div>
    </aside>

    <div class="app-main">
        <header class="topbar">
            <button class="btn btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Abrir menú">
                <i class="bi bi-list fs-4"></i>
            </button>

            <div class="topbar-title">
                <span class="topbar-eyebrow"><?= e(App\Core\Param::string('institucion_nombre', 'Centro Educativo')) ?></span>
                <h1><?= e($title ?? 'Panel') ?></h1>
            </div>

            <div class="ms-auto d-flex align-items-center gap-2">
                <?php if ($user !== null): ?>
                    <span class="badge rounded-pill text-bg-light border d-none d-md-inline">
                        <i class="bi bi-geo-alt me-1"></i><?= e($user['sede'] ?? 'Todas las sedes') ?>
                    </span>
                    <div class="dropdown">
                        <button class="btn user-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar"><?= e(iniciales((string) $user['nombre'])) ?></span>
                            <span class="user-meta d-none d-sm-flex">
                                <span class="user-name"><?= e($user['nombre']) ?></span>
                                <span class="user-role"><?= e($user['rol']) ?></span>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-semibold"><?= e($user['nombre']) ?></div>
                                <div class="small text-muted"><?= e($user['username']) ?> · <?= e($user['rol']) ?></div>
                            </li>
                            <li><a class="dropdown-item" href="<?= e(url('/change-password')) ?>"><i class="bi bi-key me-2"></i>Cambiar contraseña</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="<?= e(url('/logout')) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <main class="app-content">
            <?= App\Core\View::partial('partials.flash', ['flashes' => $flashes]) ?>
            <?= $content ?>
        </main>

        <footer class="app-footer">
            <span><?= e(App\Core\Param::string('nombre_sistema', 'Futuro Digital')) ?> · <?= e(date('Y')) ?></span>
            <span class="text-muted">Sedes Sincelejo y Corozal</span>
        </footer>
    </div>
</div>

<script src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
