<?php

declare(strict_types=1);
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/classrooms')) ?>">Aulas</a></li><li class="breadcrumb-item active">Nueva</li></ol></nav>
        <h2>Nueva aula</h2>
    </div>
</div>

<form method="post" action="<?= e(url('/classrooms')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label required" for="sede_id">Sede</label>
                    <select class="form-select" id="sede_id" name="sede_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($sedes as $sede): ?>
                            <option value="<?= (int) $sede['id'] ?>" <?= selected(old('sede_id'), $sede['id']) ?>><?= e($sede['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('sede_id')): ?><div class="text-danger small mt-1"><?= e(error('sede_id')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-5">
                    <label class="form-label required" for="nombre">Nombre</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" maxlength="80" value="<?= e(old('nombre')) ?>" required>
                    <?php if (has_error('nombre')): ?><div class="text-danger small mt-1"><?= e(error('nombre')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="capacidad">Capacidad</label>
                    <input type="number" class="form-control" id="capacidad" name="capacidad" min="1" max="500" value="<?= e(old('capacidad', 30)) ?>" required>
                    <?php if (has_error('capacidad')): ?><div class="text-danger small mt-1"><?= e(error('capacidad')) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('/classrooms')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar aula</button>
        </div>
    </div>
</form>
