<?php

declare(strict_types=1);
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/courses')) ?>">Cursos</a></li><li class="breadcrumb-item active">Nuevo</li></ol></nav>
        <h2>Nuevo curso</h2>
    </div>
</div>

<form method="post" action="<?= e(url('/courses')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label required" for="codigo">Código</label>
                    <input type="text" class="form-control" id="codigo" name="codigo" maxlength="20" value="<?= e(old('codigo')) ?>" required>
                    <?php if (has_error('codigo')): ?><div class="text-danger small mt-1"><?= e(error('codigo')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-8">
                    <label class="form-label required" for="nombre">Nombre</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" maxlength="150" value="<?= e(old('nombre')) ?>" required>
                    <?php if (has_error('nombre')): ?><div class="text-danger small mt-1"><?= e(error('nombre')) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('/courses')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar curso</button>
        </div>
    </div>
</form>
