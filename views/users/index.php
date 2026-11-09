<?php

declare(strict_types=1);

$filters = $filters ?? [];
$roles = $roles ?? [];
$sedes = $sedes ?? [];
?>
<div class="page-header">
    <div>
        <h2>Usuarios</h2>
        <p class="text-muted mb-0">Cuentas de acceso al sistema. No existe registro público: los usuarios los crea el administrador.</p>
    </div>
    <?php if (can('users.manage')): ?>
        <div class="page-actions">
            <a href="<?= e(url('/users/create')) ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Nuevo usuario</a>
        </div>
    <?php endif; ?>
</div>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('/users')) ?>" class="filter-form">
            <div class="form-group wide">
                <label class="form-label" for="buscar">Buscar</label>
                <input type="search" class="form-control" id="buscar" name="buscar" placeholder="Nombre, documento o usuario" value="<?= e($filters['buscar'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="rol_id">Rol</label>
                <select class="form-select" id="rol_id" name="rol_id">
                    <option value="">Todos</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['id'] ?>" <?= selected($filters['rol_id'] ?? '', $role['id']) ?>><?= e($role['nombre']) ?></option>
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
                    <?php foreach (['activo', 'inactivo'] as $state): ?>
                        <option value="<?= e($state) ?>" <?= selected($filters['estado'] ?? '', $state) ?>><?= e(estado_label($state)) ?></option>
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
            <thead><tr><th>Usuario</th><th>Persona</th><th>Documento</th><th>Rol</th><th>Sede</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="7"><div class="empty-state compact"><i class="bi bi-person-badge"></i><p class="mb-0">No se encontraron usuarios con los filtros aplicados.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="mono"><?= e($row['username']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($row['apellidos'] . ', ' . $row['nombres']) ?></div>
                        <div class="small text-muted"><?= e($row['email'] ?? '—') ?></div>
                    </td>
                    <td><?= e($row['numero_documento']) ?></td>
                    <td><?= e($row['rol']) ?></td>
                    <td><?= e($row['sede'] ?? 'Global') ?></td>
                    <td><?= badge_estado($row['estado']) ?></td>
                    <td class="text-end">
                        <div class="table-actions">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/users/' . $row['id'] . '/edit')) ?>" title="Editar"><i class="bi bi-pencil"></i></a>
                            <form method="post" action="<?= e(url('/users/' . $row['id'] . '/reset-password')) ?>" class="d-inline" data-confirm="¿Restablecer la contraseña de este usuario? Se generará una contraseña temporal.">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-secondary" type="submit" title="Restablecer contraseña"><i class="bi bi-key"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted small">Mostrando <?= $paginator->from() ?>–<?= $paginator->to() ?> de <?= $paginator->total ?> usuario(s)</div>
        <?= pagination($paginator) ?>
    </div>
</div>
