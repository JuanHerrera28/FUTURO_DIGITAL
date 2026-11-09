<?php

declare(strict_types=1);

$filters = $filters ?? [];
$sedes = $sedes ?? [];
?>
<div class="page-header">
    <div>
        <h2>Estudiantes</h2>
        <p class="text-muted mb-0">Registro y consulta de estudiantes de ambas sedes.</p>
    </div>
    <?php if (can('students.manage')): ?>
        <div class="page-actions">
            <a href="<?= e(url('/students/create')) ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Nuevo estudiante</a>
        </div>
    <?php endif; ?>
</div>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('/students')) ?>" class="filter-form">
            <div class="form-group wide">
                <label class="form-label" for="buscar">Buscar</label>
                <input type="search" class="form-control" id="buscar" name="buscar" placeholder="Nombre, documento o código" value="<?= e($filters['buscar'] ?? '') ?>">
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
                    <?php foreach (['activo', 'inactivo', 'graduado', 'retirado'] as $estado): ?>
                        <option value="<?= e($estado) ?>" <?= selected($filters['estado'] ?? '', $estado) ?>><?= e(estado_label($estado)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <button class="btn btn-navy" type="submit"><i class="bi bi-search me-1"></i>Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>Código</th>
                <th>Estudiante</th>
                <th>Documento</th>
                <th>Sede</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="6"><div class="empty-state compact"><i class="bi bi-people"></i><p class="mb-0">No se encontraron estudiantes con los filtros aplicados.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="mono"><?= e($row['codigo'] ?? '—') ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($row['apellidos'] . ', ' . $row['nombres']) ?></div>
                        <div class="small text-muted"><?= e($row['email'] ?? '') ?></div>
                    </td>
                    <td><?= e($row['tipo_documento']) ?> <?= e($row['numero_documento']) ?></td>
                    <td><?= e($row['sede']) ?></td>
                    <td><?= badge_estado($row['estado']) ?></td>
                    <td>
                        <div class="table-actions">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/students/' . $row['id'])) ?>" title="Ver ficha"><i class="bi bi-eye"></i></a>
                            <?php if (can('students.manage')): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/students/' . $row['id'] . '/edit')) ?>" title="Editar"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                            <?php if (can('enrollment.manage')): ?>
                                <a class="btn btn-sm btn-outline-success" href="<?= e(url('/enrollments/create?estudiante_id=' . $row['id'])) ?>" title="Matricular"><i class="bi bi-person-plus"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted small">Mostrando <?= $paginator->from() ?>–<?= $paginator->to() ?> de <?= $paginator->total ?> estudiante(s)</div>
        <?= pagination($paginator) ?>
    </div>
</div>
