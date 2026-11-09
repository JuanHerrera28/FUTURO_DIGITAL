<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $rows */
?>
<div class="page-header">
    <div>
        <h2>Roles y permisos</h2>
        <p class="text-muted mb-0">La matriz de permisos es editable. El rol Administrador está protegido por el sistema.</p>
    </div>
    <?php if (can('roles.manage')): ?>
        <div class="page-actions">
            <a href="<?= e(url('/roles/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nuevo rol</a>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Rol</th><th>Descripción</th><th class="text-end">Usuarios</th><th class="text-end">Permisos</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="fw-semibold"><?= e($row['nombre']) ?></td>
                    <td><?= e($row['descripcion'] ?? '—') ?></td>
                    <td class="text-end"><span class="badge text-bg-light border"><?= (int) $row['usuarios'] ?></span></td>
                    <td class="text-end"><span class="badge text-bg-light border"><?= (int) $row['permisos'] ?></span></td>
                    <td><?= badge_estado($row['estado']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/roles/' . $row['id'] . '/edit')) ?>"><i class="bi bi-shield-lock me-1"></i>Permisos</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
