<?php

declare(strict_types=1);

$students = $students ?? [];
$programs = $programs ?? [];
$periods = $periods ?? [];
$preselected = (int) ($_GET['estudiante_id'] ?? 0);
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/enrollments')) ?>">Matrículas</a></li><li class="breadcrumb-item active">Nueva</li></ol></nav>
        <h2>Nueva matrícula</h2>
        <p class="text-muted mb-0">La matrícula exige matrícula abierta y fecha vigente; genera el cargo según la tarifa configurada.</p>
    </div>
</div>

<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Si el estudiante no tiene usuario, se creará en este momento y sus credenciales se mostrarán una única vez.</div>

<form method="post" action="<?= e(url('/enrollments')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="estudiante_id">Estudiante</label>
                    <select class="form-select" id="estudiante_id" name="estudiante_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= (int) $student['id'] ?>" <?= selected(old('estudiante_id', $preselected), $student['id']) ?>>
                                <?= e($student['apellidos'] . ', ' . $student['nombres']) ?> · <?= e($student['numero_documento']) ?> · <?= e($student['sede']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('estudiante_id')): ?><div class="text-danger small mt-1"><?= e(error('estudiante_id')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="programa_id">Programa</label>
                    <select class="form-select" id="programa_id" name="programa_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?= (int) $program['id'] ?>" <?= selected(old('programa_id'), $program['id']) ?>><?= e($program['nombre']) ?> (<?= e($program['codigo']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('programa_id')): ?><div class="text-danger small mt-1"><?= e(error('programa_id')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="periodo_id">Periodo</label>
                    <select class="form-select" id="periodo_id" name="periodo_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($periods as $period): ?>
                            <option value="<?= (int) $period['id'] ?>" <?= selected(old('periodo_id'), $period['id']) ?>><?= e($period['nombre']) ?> · <?= e(estado_label($period['estado'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('periodo_id')): ?><div class="text-danger small mt-1"><?= e(error('periodo_id')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="info-strip w-100">
                        <div><span>Código</span><strong>Se asigna automáticamente (MAT-AAAA-00001)</strong></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('/enrollments')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Crear matrícula</button>
        </div>
    </div>
</form>
