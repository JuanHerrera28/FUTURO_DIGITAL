<?php

declare(strict_types=1);

$person = null;
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/teachers')) ?>">Docentes</a></li><li class="breadcrumb-item active">Nuevo</li></ol></nav>
        <h2>Nuevo docente</h2>
        <p class="text-muted mb-0">Al guardar se genera el usuario del docente con contraseña temporal.</p>
    </div>
</div>

<form method="post" action="<?= e(url('/teachers')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="form-section-title">Datos de la persona</div>
            <?= App\Core\View::partial('partials.person-fields', ['person' => $person, 'documentTypes' => $documentTypes]) ?>

            <div class="form-section-title mt-4">Datos de vinculación</div>
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
                <div class="col-md-4">
                    <label class="form-label required" for="tipo_vinculacion">Tipo de vinculación</label>
                    <select class="form-select" id="tipo_vinculacion" name="tipo_vinculacion" required>
                        <?php foreach (['planta' => 'Planta', 'catedra' => 'Cátedra', 'contrato' => 'Contrato'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= selected(old('tipo_vinculacion', 'catedra'), $value) ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="titulo">Título profesional</label>
                    <input type="text" class="form-control" id="titulo" name="titulo" maxlength="150" value="<?= e(old('titulo')) ?>">
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('/teachers')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar docente</button>
        </div>
    </div>
</form>
