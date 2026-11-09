<?php

declare(strict_types=1);

/** @var array<string,mixed> $user */
/** @var list<array<string,mixed>> $roles */
/** @var list<array<string,mixed>> $sedes */
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/users')) ?>">Usuarios</a></li><li class="breadcrumb-item active"><?= e($user['username']) ?></li></ol></nav>
        <h2><?= e($user['apellidos'] . ', ' . $user['nombres']) ?></h2>
        <p class="text-muted mb-0"><?= e($user['numero_documento']) ?> · <?= e($user['username']) ?> · <?= badge_estado($user['estado']) ?></p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Datos de la persona</div>
            <div class="card-body">
                <div class="detail-grid">
                    <div><span>Nombres</span><strong><?= e($user['nombres']) ?></strong></div>
                    <div><span>Apellidos</span><strong><?= e($user['apellidos']) ?></strong></div>
                    <div><span>Documento</span><strong><?= e($user['numero_documento']) ?></strong></div>
                    <div><span>Correo</span><strong><?= e($user['email'] ?? '—') ?></strong></div>
                    <div><span>Teléfono</span><strong><?= e($user['telefono'] ?? '—') ?></strong></div>
                    <div><span>Usuario</span><strong class="mono"><?= e($user['username']) ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Acceso y alcance</div>
            <div class="card-body">
                <form method="post" action="<?= e(url('/users/' . $user['id'])) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required" for="rol_id">Rol</label>
                            <select class="form-select" id="rol_id" name="rol_id" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int) $role['id'] ?>" <?= selected(old('rol_id', $user['rol_id']), $role['id']) ?>><?= e($role['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (has_error('rol_id')): ?><div class="text-danger small mt-1"><?= e(error('rol_id')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sede_id">Sede</label>
                            <select class="form-select" id="sede_id" name="sede_id">
                                <option value="">Global / sin sede</option>
                                <?php foreach ($sedes as $sede): ?>
                                    <option value="<?= (int) $sede['id'] ?>" <?= selected(old('sede_id', $user['sede_id']), $sede['id']) ?>><?= e($sede['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (has_error('sede_id')): ?><div class="text-danger small mt-1"><?= e(error('sede_id')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="estado">Estado</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <?php foreach (['activo', 'inactivo'] as $state): ?>
                                    <option value="<?= e($state) ?>" <?= selected(old('estado', $user['estado']), $state) ?>><?= e(estado_label($state)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (has_error('estado')): ?><div class="text-danger small mt-1"><?= e(error('estado')) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="<?= e(url('/users')) ?>" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">Contraseña</div>
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <p class="mb-0 text-muted">Genere una contraseña temporal nueva. La persona deberá cambiarla en su próximo ingreso.</p>
        <form method="post" action="<?= e(url('/users/' . $user['id'] . '/reset-password')) ?>" data-confirm="¿Restablecer la contraseña de este usuario?">
            <?= csrf_field() ?>
            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-key me-1"></i>Restablecer contraseña</button>
        </form>
    </div>
</div>
