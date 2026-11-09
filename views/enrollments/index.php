<?php

declare(strict_types=1);

$filters = $filters ?? [];
$periods = $periods ?? [];
$programs = $programs ?? [];
$sedes = $sedes ?? [];
?>
<div class="page-header">
    <div>
        <h2>Matrículas</h2>
        <p class="text-muted mb-0">Matrícula por estudiante, programa y periodo; las inscripciones a grupos se gestionan en el detalle.</p>
    </div>
    <?php if (can('enrollment.manage')): ?>
        <div class="page-actions">
            <a href="<?= e(url('/enrollments/create')) ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Nueva matrícula</a>
        </div>
    <?php endif; ?>
</div>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('/enrollments')) ?>" class="filter-form">
            <div class="form-group wide">
                <label class="form-label" for="buscar">Buscar</label>
                <input type="search" class="form-control" id="buscar" name="buscar" placeholder="Estudiante, documento o matrícula" value="<?= e($filters['buscar'] ?? '') ?>">
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
            <div class="form-group">
                <label class="form-label" for="programa_id">Programa</label>
                <select class="form-select" id="programa_id" name="programa_id">
                    <option value="">Todos</option>
                    <?php foreach ($programs as $program): ?>
                        <option value="<?= (int) $program['id'] ?>" <?= selected($filters['programa_id'] ?? '', $program['id']) ?>><?= e($program['nombre']) ?></option>
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
            <div class="form-group">
                <label class="form-label" for="estado">Estado</label>
                <select class="form-select" id="estado" name="estado">
                    <option value="">Todos</option>
                    <?php foreach (['activa', 'cancelada', 'finalizada'] as $estado): ?>
                        <option value="<?= e($estado) ?>" <?= selected($filters['estado'] ?? '', $estado) ?>><?= e(estado_label($estado)) ?></option>
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
            <thead><tr><th>Matrícula</th><th>Estudiante</th><th>Programa</th><th>Periodo</th><th>Sede</th><th>Cursos</th><th>Fecha</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="9"><div class="empty-state compact"><i class="bi bi-person-plus"></i><p class="mb-0">No se encontraron matrículas con los filtros aplicados.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="mono"><?= e($row['codigo']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($row['apellidos'] . ', ' . $row['nombres']) ?></div>
                        <div class="small text-muted"><?= e($row['numero_documento']) ?> · <?= e($row['estudiante_codigo']) ?></div>
                    </td>
                    <td><?= e($row['programa']) ?></td>
                    <td><?= e($row['periodo']) ?> <?= badge_estado($row['periodo_estado']) ?></td>
                    <td><?= e($row['sede']) ?></td>
                    <td><span class="badge text-bg-light border"><?= (int) $row['cursos'] ?></span></td>
                    <td><?= e(fecha($row['fecha'])) ?></td>
                    <td><?= badge_estado($row['estado']) ?></td>
                    <td>
                        <div class="table-actions">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/enrollments/' . $row['id'])) ?>" title="Ver matrícula"><i class="bi bi-eye"></i></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted small">Mostrando <?= $paginator->from() ?>–<?= $paginator->to() ?> de <?= $paginator->total ?> matrícula(s)</div>
        <?= pagination($paginator) ?>
    </div>
</div>
