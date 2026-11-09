<?php

declare(strict_types=1);
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/classrooms')) ?>">Aulas</a></li><li class="breadcrumb-item active">Editar</li></ol></nav>
        <h2>Editar aula</h2>
        <p class="text-muted mb-0"><?= e($classroom['nombre']) ?> · <?= e($classroom['sede']) ?></p>
    </div>
</div>

<form method="post" action="<?= e(url('/classrooms/' . $classroom['id'])) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label required" for="nombre">Nombre</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" maxlength="80" value="<?= e(old('nombre', $classroom['nombre'])) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="capacidad">Capacidad</label>
                    <input type="number" class="form-control" id="capacidad" name="capacidad" min="1" max="500" value="<?= e(old('capacidad', $classroom['capacidad'])) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="estado">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <?php foreach (['activo', 'inactivo'] as $estado): ?>
                            <option value="<?= e($estado) ?>" <?= selected(old('estado', $classroom['estado']), $estado) ?>><?= e(estado_label($estado)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('/classrooms')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar cambios</button>
        </div>
    </div>
</form>
