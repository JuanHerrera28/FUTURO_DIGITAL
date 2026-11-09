<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $roles */
/** @var list<array<string,mixed>> $sedes */
/** @var list<array<string,mixed>> $documentTypes */
$person = null;
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/users')) ?>">Usuarios</a></li><li class="breadcrumb-item active">Nuevo</li></ol></nav>
        <h2>Nuevo usuario</h2>
        <p class="text-muted mb-0">La contraseña temporal se genera automáticamente y se muestra una sola vez.</p>
    </div>
</div>

<form method="post" action="<?= e(url('/users')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="form-section-title">Datos de la persona</div>
            <?= App\Core\View::partial('partials.person-fields', ['person' => $person, 'documentTypes' => $documentTypes]) ?>

            <div class="form-section-title mt-4">Acceso al sistema</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="rol_id">Rol</label>
                    <select class="form-select" id="rol_id" name="rol_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int) $role['id'] ?>" <?= selected(old('rol_id'), $role['id']) ?>><?= e($role['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('rol_id')): ?><div class="text-danger small mt-1"><?= e(error('rol_id')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="sede_id">Sede</label>
                    <select class="form-select" id="sede_id" name="sede_id">
                        <option value="">Global / sin sede</option>
                        <?php foreach ($sedes as $sede): ?>
                            <option value="<?= (int) $sede['id'] ?>" <?= selected(old('sede_id'), $sede['id']) ?>><?= e($sede['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Los roles con alcance por sede solo verán la información de la sede asignada.</div>
                    <?php if (has_error('sede_id')): ?><div class="text-danger small mt-1"><?= e(error('sede_id')) ?></div><?php endif; ?>
                </div>
                <div class="col-12">
                    <div class="info-strip">
                        <div><span>Usuario</span><strong>Se usa el número de documento como nombre de usuario</strong></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('/users')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Crear usuario</button>
        </div>
    </div>
</form>
