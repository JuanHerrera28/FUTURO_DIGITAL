<?php

declare(strict_types=1);

$filters = $filters ?? [];
$sedes = $sedes ?? [];
$typeBadge = static function (string $type): string {
    $map = ['inasistencia' => 'warning', 'bajo_rendimiento' => 'info', 'mora' => 'danger'];

    return '<span class="badge text-bg-' . ($map[$type] ?? 'secondary') . '">' . e(estado_label($type)) . '</span>';
};
$levelBadge = static function (string $level): string {
    $map = ['informativa' => 'info', 'advertencia' => 'warning', 'critica' => 'danger'];

    return '<span class="badge text-bg-' . ($map[$level] ?? 'secondary') . '">' . e(estado_label($level)) . '</span>';
};
?>
<div class="page-header">
    <div>
        <h2>Alertas</h2>
        <p class="text-muted mb-0">Alertas de inasistencia, bajo rendimiento y mora. La mora se sincroniza al abrir el módulo.</p>
    </div>
</div>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('/alerts')) ?>" class="filter-form">
            <div class="form-group wide">
                <label class="form-label" for="buscar">Buscar</label>
                <input type="search" class="form-control" id="buscar" name="buscar" placeholder="Estudiante, documento o mensaje" value="<?= e($filters['buscar'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="tipo">Tipo</label>
                <select class="form-select" id="tipo" name="tipo">
                    <option value="">Todos</option>
                    <?php foreach (['inasistencia', 'bajo_rendimiento', 'mora'] as $type): ?>
                        <option value="<?= e($type) ?>" <?= selected($filters['tipo'] ?? '', $type) ?>><?= e(estado_label($type)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="nivel">Nivel</label>
                <select class="form-select" id="nivel" name="nivel">
                    <option value="">Todos</option>
                    <?php foreach (['informativa', 'advertencia', 'critica'] as $level): ?>
                        <option value="<?= e($level) ?>" <?= selected($filters['nivel'] ?? '', $level) ?>><?= e(estado_label($level)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="estado">Estado</label>
                <select class="form-select" id="estado" name="estado">
                    <option value="">Todos</option>
                    <?php foreach (['nueva', 'atendida'] as $state): ?>
                        <option value="<?= e($state) ?>" <?= selected($filters['estado'] ?? '', $state) ?>><?= e(estado_label($state)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="sede_id">Sede</label>
                <select class="form-select" id="sede_id" name="sede_id">
                    <option value="">Todas</option>
                    <?php foreach ($sedes as $sede): ?>
                        <option value="<?= (int) $sede['id'] ?>" <?= selected($filters['sede_id'] ?? '', $sede['id']) ?>><?= e($sede['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><button class="btn btn-navy" type="submit"><i class="bi bi-search me-1"></i>Filtrar</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Fecha</th><th>Estudiante</th><th>Grupo</th><th>Tipo</th><th>Nivel</th><th>Mensaje</th><th>Estado</th><th>Atendida por</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="9"><div class="empty-state compact"><i class="bi bi-bell-slash"></i><p class="mb-0">No se encontraron alertas con los filtros aplicados.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="text-nowrap"><?= e(fecha_hora($row['created_at'])) ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($row['apellidos'] . ', ' . $row['nombres']) ?></div>
                        <div class="small text-muted mono"><?= e($row['estudiante_codigo']) ?> · <?= e($row['numero_documento']) ?> · <?= e($row['sede']) ?></div>
                    </td>
                    <td>
                        <?php if (!empty($row['grupo'])): ?>
                            <div class="mono small"><?= e($row['grupo']) ?></div>
                            <div class="small text-muted"><?= e($row['curso'] ?? '') ?></div>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $typeBadge((string) $row['tipo']) ?></td>
                    <td><?= $levelBadge((string) $row['nivel']) ?></td>
                    <td class="small"><?= e($row['mensaje']) ?></td>
                    <td><?= badge_estado($row['estado']) ?></td>
                    <td class="small"><?= e($row['atendida_por_nombre'] ?? '—') ?><?= !empty($row['atendida_at']) ? '<div class="text-muted">' . e(fecha_hora($row['atendida_at'])) . '</div>' : '' ?></td>
                    <td class="text-end">
                        <div class="table-actions">
                            <?php if (can('students.view')): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/students/' . $row['estudiante_id'])) ?>" title="Ver estudiante"><i class="bi bi-person"></i></a>
                            <?php endif; ?>
                            <?php if (can('alerts.manage') && (string) $row['estado'] === 'nueva'): ?>
                                <form method="post" action="<?= e(url('/alerts/' . $row['id'] . '/attend')) ?>" class="d-inline" data-confirm="¿Marcar esta alerta como atendida?">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-success" type="submit" title="Marcar atendida"><i class="bi bi-check2-circle"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted small">Mostrando <?= $paginator->from() ?>–<?= $paginator->to() ?> de <?= $paginator->total ?> alerta(s)</div>
        <?= pagination($paginator) ?>
    </div>
</div>
