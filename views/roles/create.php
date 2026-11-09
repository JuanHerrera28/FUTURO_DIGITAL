<?php

declare(strict_types=1);
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/roles')) ?>">Roles</a></li><li class="breadcrumb-item active">Nuevo</li></ol></nav>
        <h2>Nuevo rol</h2>
        <p class="text-muted mb-0">Después de crear el rol podrá asignar sus permisos por módulo.</p>
    </div>
</div>

<form method="post" action="<?= e(url('/roles')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label required" for="nombre">Nombre</label>
                    <input class="form-control" id="nombre" name="nombre" maxlength="60" value="<?= e(old('nombre')) ?>" required>
                    <?php if (has_error('nombre')): ?><div class="text-danger small mt-1"><?= e(error('nombre')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="descripcion">Descripción</label>
                    <input class="form-control" id="descripcion" name="descripcion" maxlength="255" value="<?= e(old('descripcion')) ?>">
                    <?php if (has_error('descripcion')): ?><div class="text-danger small mt-1"><?= e(error('descripcion')) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('/roles')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Crear rol</button>
        </div>
    </div>
</form>
