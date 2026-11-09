<?php

declare(strict_types=1);

$filters = $filters ?? [];
$periods = $periods ?? [];
?>
<div class="page-header">
    <div>
        <h2>Asistencia</h2>
        <p class="text-muted mb-0">Sesiones y control de inasistencias por grupo. Solo el docente asignado registra asistencia (RN-12).</p>
    </div>
</div>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('/attendance')) ?>" class="filter-form">
            <div class="form-group wide">
                <label class="form-label" for="buscar">Buscar</label>
                <input type="search" class="form-control" id="buscar" name="buscar" placeholder="Código o curso" value="<?= e($filters['buscar'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="periodo_id">Periodo</label>
                <select class="form-select" id="periodo_id" name="periodo_id">
                    <option value="">Todos</option>
                    <?php foreach ($periods as $period): ?>
                        <option value="<?= (int) $period['id'] ?>" <?= selected($filters['periodo_id'] ?? '', $period['id']) ?>><?= e($period['nombre']) ?></option>
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
            <thead><tr><th>Grupo</th><th>Curso</th><th>Periodo</th><th>Sede</th><th>Docente</th><th>Inscritos</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="8"><div class="empty-state compact"><i class="bi bi-calendar-check"></i><p class="mb-0">No hay grupos disponibles para tomar asistencia con los filtros aplicados.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="mono"><?= e($row['codigo']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($row['curso']) ?></div>
                        <div class="small text-muted mono"><?= e($row['curso_codigo']) ?></div>
                    </td>
                    <td><?= e($row['periodo']) ?> <?= badge_estado($row['periodo_estado']) ?></td>
                    <td><?= e($row['sede']) ?></td>
                    <td><?= e($row['docente']) ?></td>
                    <td><span class="badge text-bg-light border"><?= (int) $row['inscritos'] ?></span></td>
                    <td><?= badge_estado($row['estado']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/attendance/' . $row['id'])) ?>"><i class="bi bi-calendar-check me-1"></i>Asistencia</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted small">Mostrando <?= $paginator->from() ?>–<?= $paginator->to() ?> de <?= $paginator->total ?> grupo(s)</div>
        <?= pagination($paginator) ?>
    </div>
</div>
