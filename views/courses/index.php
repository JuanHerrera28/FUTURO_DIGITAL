<?php

declare(strict_types=1);

$filters = $filters ?? [];
?>
<div class="page-header">
    <div>
        <h2>Cursos</h2>
        <p class="text-muted mb-0">Catálogo global de cursos usado por los planes de estudio.</p>
    </div>
    <?php if (can('courses.manage')): ?>
        <div class="page-actions">
            <a href="<?= e(url('/courses/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nuevo curso</a>
        </div>
    <?php endif; ?>
</div>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('/courses')) ?>" class="filter-form">
            <div class="form-group wide">
                <label class="form-label" for="buscar">Buscar</label>
                <input type="search" class="form-control" id="buscar" name="buscar" placeholder="Código o nombre" value="<?= e($filters['buscar'] ?? '') ?>">
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
            <thead><tr><th>Código</th><th>Curso</th><th>Programas</th><th>Grupos activos</th><th>Prerrequisitos</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="7"><div class="empty-state compact"><i class="bi bi-journal-bookmark"></i><p class="mb-0">No se encontraron cursos.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="mono"><?= e($row['codigo']) ?></td>
                    <td class="fw-semibold"><?= e($row['nombre']) ?></td>
                    <td><span class="badge text-bg-light border"><?= (int) $row['programas'] ?></span></td>
                    <td><span class="badge text-bg-light border"><?= (int) $row['grupos'] ?></span></td>
                    <td><span class="badge text-bg-light border"><?= (int) $row['prerrequisitos'] ?></span></td>
                    <td><?= badge_estado($row['estado']) ?></td>
                    <td>
                        <div class="table-actions">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/courses/' . $row['id'])) ?>" title="Ver"><i class="bi bi-eye"></i></a>
                            <?php if (can('courses.manage')): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/courses/' . $row['id'] . '/edit')) ?>" title="Editar"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted small">Mostrando <?= $paginator->from() ?>–<?= $paginator->to() ?> de <?= $paginator->total ?> curso(s)</div>
        <?= pagination($paginator) ?>
    </div>
</div>
