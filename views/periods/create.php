<?php

declare(strict_types=1);
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/periods')) ?>">Periodos</a></li><li class="breadcrumb-item active">Nuevo</li></ol></nav>
        <h2>Nuevo periodo</h2>
        <p class="text-muted mb-0">La matrícula debe cerrar antes del fin del periodo.</p>
    </div>
</div>

<form method="post" action="<?= e(url('/periods')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label required" for="codigo">Código</label>
                    <input type="text" class="form-control" id="codigo" name="codigo" maxlength="20" value="<?= e(old('codigo')) ?>" required>
                    <?php if (has_error('codigo')): ?><div class="text-danger small mt-1"><?= e(error('codigo')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-9">
                    <label class="form-label required" for="nombre">Nombre</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" value="<?= e(old('nombre')) ?>" required>
                    <?php if (has_error('nombre')): ?><div class="text-danger small mt-1"><?= e(error('nombre')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="fecha_inicio">Inicio del periodo</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?= e(old('fecha_inicio')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="fecha_fin">Fin del periodo</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?= e(old('fecha_fin')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="matricula_inicio">Apertura de matrícula</label>
                    <input type="date" class="form-control" id="matricula_inicio" name="matricula_inicio" value="<?= e(old('matricula_inicio')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="matricula_fin">Cierre de matrícula</label>
                    <input type="date" class="form-control" id="matricula_fin" name="matricula_fin" value="<?= e(old('matricula_fin')) ?>" required>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('/periods')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar periodo</button>
        </div>
    </div>
</form>
