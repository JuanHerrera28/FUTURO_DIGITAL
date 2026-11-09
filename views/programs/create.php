<?php

declare(strict_types=1);
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/programs')) ?>">Programas</a></li><li class="breadcrumb-item active">Nuevo</li></ol></nav>
        <h2>Nuevo programa</h2>
        <p class="text-muted mb-0">Después de crearlo podrá cargar su plan de estudios.</p>
    </div>
</div>

<form method="post" action="<?= e(url('/programs')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label required" for="codigo">Código</label>
                    <input type="text" class="form-control" id="codigo" name="codigo" maxlength="20" value="<?= e(old('codigo')) ?>" required>
                    <?php if (has_error('codigo')): ?><div class="text-danger small mt-1"><?= e(error('codigo')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-5">
                    <label class="form-label required" for="nombre">Nombre</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" maxlength="120" value="<?= e(old('nombre')) ?>" required>
                    <?php if (has_error('nombre')): ?><div class="text-danger small mt-1"><?= e(error('nombre')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="duracion_periodos">Duración (periodos)</label>
                    <input type="number" class="form-control" id="duracion_periodos" name="duracion_periodos" min="1" max="20" value="<?= e(old('duracion_periodos', 1)) ?>" required>
                    <?php if (has_error('duracion_periodos')): ?><div class="text-danger small mt-1"><?= e(error('duracion_periodos')) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('/programs')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar programa</button>
        </div>
    </div>
</form>
