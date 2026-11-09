<?php

declare(strict_types=1);
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/periods')) ?>">Periodos</a></li><li class="breadcrumb-item active">Editar</li></ol></nav>
        <h2>Editar periodo</h2>
        <p class="text-muted mb-0"><?= e($period['nombre']) ?> · <?= e($period['codigo']) ?></p>
    </div>
</div>

<?php if ((string) $period['estado'] === 'cerrado'): ?>
    <div class="alert alert-warning"><i class="bi bi-lock me-2"></i>Un periodo cerrado no puede modificarse (RF-22).</div>
<?php endif; ?>

<form method="post" action="<?= e(url('/periods/' . $period['id'])) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label required" for="codigo">Código</label>
                    <input type="text" class="form-control" id="codigo" name="codigo" maxlength="20" value="<?= e(old('codigo', $period['codigo'])) ?>" required>
                </div>
                <div class="col-md-9">
                    <label class="form-label required" for="nombre">Nombre</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" value="<?= e(old('nombre', $period['nombre'])) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="fecha_inicio">Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?= e(old('fecha_inicio', $period['fecha_inicio'])) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="fecha_fin">Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?= e(old('fecha_fin', $period['fecha_fin'])) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="matricula_inicio">Apertura matrícula</label>
                    <input type="date" class="form-control" id="matricula_inicio" name="matricula_inicio" value="<?= e(old('matricula_inicio', $period['matricula_inicio'])) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="matricula_fin">Cierre matrícula</label>
                    <input type="date" class="form-control" id="matricula_fin" name="matricula_fin" value="<?= e(old('matricula_fin', $period['matricula_fin'])) ?>" required>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('/periods/' . $period['id'])) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary" <?= (string) $period['estado'] === 'cerrado' ? 'disabled' : '' ?>><i class="bi bi-save me-1"></i>Guardar cambios</button>
        </div>
    </div>
</form>
