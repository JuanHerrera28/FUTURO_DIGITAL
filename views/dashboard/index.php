<?php

declare(strict_types=1);

$user = auth_user();
$stats = $stats ?? [];
$alerts = $alerts ?? [];
$quick = [];
if (can('students.view')) {
    $quick[] = ['url' => '/students', 'icon' => 'people', 'title' => 'Estudiantes', 'text' => 'Consultar y registrar estudiantes.'];
}
if (can('enrollment.view')) {
    $quick[] = ['url' => '/enrollments', 'icon' => 'person-plus', 'title' => 'Matrículas', 'text' => 'Matricular e inscribir en grupos.'];
}
if (can('groups.view')) {
    $quick[] = ['url' => '/groups', 'icon' => 'collection', 'title' => 'Grupos', 'text' => 'Oferta, horarios y cupos.'];
}
if (can('payments.view')) {
    $quick[] = ['url' => '/payments', 'icon' => 'cash-coin', 'title' => 'Pagos', 'text' => 'Cartera y comprobantes.'];
}
if (can('reports.view')) {
    $quick[] = ['url' => '/reports', 'icon' => 'graph-up-arrow', 'title' => 'Informes', 'text' => 'Indicadores y exportación CSV.'];
}
?>
<div class="page-header">
    <div>
        <h2>Hola, <?= e($user['nombre'] ?? '') ?></h2>
        <p class="text-muted mb-0">
            <?= e($user['rol'] ?? '') ?><?= !empty($user['sede']) ? ' · Sede ' . e($user['sede']) : ' · Todas las sedes' ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/change-password')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-key me-1"></i>Cambiar contraseña</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <?php
    $cards = [
        ['key' => 'estudiantes', 'label' => 'Estudiantes activos', 'icon' => 'people', 'tone' => 'blue'],
        ['key' => 'matriculas', 'label' => 'Matrículas del periodo', 'icon' => 'person-plus', 'tone' => 'green'],
        ['key' => 'grupos', 'label' => 'Grupos activos', 'icon' => 'collection', 'tone' => 'gold'],
        ['key' => 'cartera', 'label' => 'Saldo pendiente', 'icon' => 'cash-coin', 'tone' => 'red', 'money' => true],
    ];
    ?>
    <?php foreach ($cards as $card): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="stat-icon stat-<?= e($card['tone']) ?>"><i class="bi bi-<?= e($card['icon']) ?>"></i></div>
                    <div class="stat-value">
                        <?= isset($stats[$card['key']]) ? (!empty($card['money']) ? money($stats[$card['key']]) : (int) $stats[$card['key']]) : '—' ?>
                    </div>
                    <div class="stat-label"><?= e($card['label']) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Accesos rápidos</h3></div>
            <div class="card-body">
                <?php if ($quick === []): ?>
                    <p class="text-muted mb-0">Su rol no tiene módulos administrativos asignados.</p>
                <?php else: ?>
                    <div class="quick-grid">
                        <?php foreach ($quick as $item): ?>
                            <a href="<?= e(url($item['url'])) ?>" class="quick-item">
                                <span class="quick-icon"><i class="bi bi-<?= e($item['icon']) ?>"></i></span>
                                <span>
                                    <strong><?= e($item['title']) ?></strong>
                                    <small><?= e($item['text']) ?></small>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Alertas recientes</h3>
                <?php if (can('alerts.view')): ?>
                    <a href="<?= e(url('/alerts')) ?>" class="small">Ver todas</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($alerts === []): ?>
                    <div class="empty-state compact">
                        <i class="bi bi-check-circle"></i>
                        <p class="mb-0">No hay alertas pendientes.</p>
                    </div>
                <?php else: ?>
                    <ul class="alert-list">
                        <?php foreach ($alerts as $alert): ?>
                            <li>
                                <span class="alert-dot alert-<?= e($alert['nivel']) ?>"></span>
                                <div>
                                    <div class="fw-semibold"><?= e($alert['mensaje']) ?></div>
                                    <div class="small text-muted"><?= e($alert['estudiante']) ?> · <?= e(fecha_hora($alert['created_at'])) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
