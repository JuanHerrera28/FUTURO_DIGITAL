<?php

declare(strict_types=1);

$filters = $filters ?? [];
$sedes = $sedes ?? [];
?>
<div class="page-header">
    <div>
        <h2>Aulas</h2>
        <p class="text-muted mb-0">Espacios físicos y capacidad por sede.</p>
    </div>
    <?php if (can('classrooms.manage')): ?>
        <div class="page-actions">
            <a href="<?= e(url('/classrooms/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nueva aula</a>
        </div>
    <?php endif; ?>
</div>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('/classrooms')) ?>" class="filter-form">
            <div class="form-group wide">
                <label class="form-label" for="buscar">Buscar</label>
                <input type="search" class="form-control" id="buscar" name="buscar" value="<?= e($filters['buscar'] ?? '') ?>" placeholder="Nombre del aula">
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
                    <?php foreach (['activo', 'inactivo'] as $estado): ?>
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
            <thead><tr><th>Aula</th><th>Sede</th><th>Capacidad</th><th>Grupos activos</th><th>Estado</th><?php if (can('classrooms.manage')): ?><th class="text-end">Acciones</th><?php endif; ?></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="6"><div class="empty-state compact"><i class="bi bi-door-open"></i><p class="mb-0">No se encontraron aulas.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="fw-semibold"><?= e($row['nombre']) ?></td>
                    <td><?= e($row['sede']) ?></td>
                    <td><?= (int) $row['capacidad'] ?></td>
                    <td><span class="badge text-bg-light border"><?= (int) $row['grupos'] ?></span></td>
                    <td><?= badge_estado($row['estado']) ?></td>
                    <?php if (can('classrooms.manage')): ?>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/classrooms/' . $row['id'] . '/edit')) ?>" title="Editar"><i class="bi bi-pencil"></i></a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted small">Mostrando <?= $paginator->from() ?>–<?= $paginator->to() ?> de <?= $paginator->total ?> aula(s)</div>
        <?= pagination($paginator) ?>
    </div>
</div>
