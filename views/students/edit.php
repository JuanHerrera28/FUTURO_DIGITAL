<?php

declare(strict_types=1);

$person = $student;
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/students')) ?>">Estudiantes</a></li><li class="breadcrumb-item active">Editar</li></ol></nav>
        <h2>Editar estudiante</h2>
        <p class="text-muted mb-0"><?= e($student['nombres'] . ' ' . $student['apellidos']) ?> · <?= e($student['codigo'] ?? '') ?></p>
    </div>
</div>

<form method="post" action="<?= e(url('/students/' . $student['id'])) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="form-section-title">Datos de la persona</div>
            <?= App\Core\View::partial('partials.person-fields', ['person' => $person, 'documentTypes' => $documentTypes]) ?>

            <div class="form-section-title mt-4">Datos académicos</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="sede_id">Sede</label>
                    <select class="form-select" id="sede_id" name="sede_id" required>
                        <?php foreach ($sedes as $sede): ?>
                            <option value="<?= (int) $sede['id'] ?>" <?= selected(old('sede_id', $student['sede_id']), $sede['id']) ?>><?= e($sede['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="estado">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <?php foreach (['activo', 'inactivo', 'graduado', 'retirado'] as $estado): ?>
                            <option value="<?= e($estado) ?>" <?= selected(old('estado', $student['estado']), $estado) ?>><?= e(estado_label($estado)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('/students/' . $student['id'])) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar cambios</button>
        </div>
    </div>
</form>
