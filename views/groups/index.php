<?php

declare(strict_types=1);

$filters = $filters ?? [];
$periods = $periods ?? [];
$sedes = $sedes ?? [];
?>
<div class="page-header">
    <div>
        <h2>Grupos</h2>
        <p class="text-muted mb-0">Oferta académica por periodo, sede, docente y aula.</p>
    </div>
    <?php if (can('groups.manage')): ?>
        <div class="page-actions">
            <a href="<?= e(url('/groups/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nuevo grupo</a>
        </div>
    <?php endif; ?>
</div>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('/groups')) ?>" class="filter-form">
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
                    <?php foreach (['activo', 'inactivo', 'cancelado'] as $estado): ?>
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
            <thead><tr><th>Grupo</th><th>Curso</th><th>Periodo</th><th>Sede</th><th>Aula</th><th>Docente</th><th>Cupos</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="9"><div class="empty-state compact"><i class="bi bi-collection"></i><p class="mb-0">No se encontraron grupos con los filtros aplicados.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php $cupos = max(0, (int) $row['capacidad_max'] - (int) $row['inscritos']); ?>
                <tr>
                    <td class="mono"><?= e($row['codigo']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($row['curso']) ?></div>
                        <div class="small text-muted mono"><?= e($row['curso_codigo']) ?></div>
                    </td>
                    <td><?= e($row['periodo']) ?> <?= badge_estado($row['periodo_estado']) ?></td>
                    <td><?= e($row['sede']) ?></td>
                    <td><?= e($row['aula']) ?></td>
                    <td><?= e($row['docente']) ?></td>
                    <td>
                        <div class="small"><strong><?= (int) $row['inscritos'] ?></strong> / <?= (int) $row['capacidad_max'] ?></div>
                        <div class="small <?= $cupos <= 0 ? 'text-danger' : 'text-muted' ?>"><?= $cupos ?> cupo(s)</div>
                    </td>
                    <td><?= badge_estado($row['estado']) ?></td>
                    <td>
                        <div class="table-actions">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/groups/' . $row['id'])) ?>" title="Ver grupo"><i class="bi bi-eye"></i></a>
                            <?php if (can('groups.manage')): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/groups/' . $row['id'] . '/edit')) ?>" title="Editar"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                        </div>
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
