<?php

declare(strict_types=1);

$person = null;
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/students')) ?>">Estudiantes</a></li><li class="breadcrumb-item active">Nuevo</li></ol></nav>
        <h2>Nuevo estudiante</h2>
        <p class="text-muted mb-0">La persona se crea una sola vez; el documento es único para ambas sedes.</p>
    </div>
</div>

<form method="post" action="<?= e(url('/students')) ?>" novalidate>
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
                        <option value="">Seleccione…</option>
                        <?php foreach ($sedes as $sede): ?>
                            <option value="<?= (int) $sede['id'] ?>" <?= selected(old('sede_id'), $sede['id']) ?>><?= e($sede['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('sede_id')): ?><div class="text-danger small mt-1"><?= e(error('sede_id')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="info-strip w-100">
                        <div><span>Código</span><strong>Se asigna automáticamente (EST-00001)</strong></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('/students')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar estudiante</button>
        </div>
    </div>
</form>
