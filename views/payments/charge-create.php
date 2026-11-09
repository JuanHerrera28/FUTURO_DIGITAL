<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $students */
/** @var list<array<string,mixed>> $concepts */
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/payments')) ?>">Cartera</a></li><li class="breadcrumb-item active">Nuevo cargo</li></ol></nav>
        <h2>Nuevo cargo</h2>
        <p class="text-muted mb-0">Registre un cargo manual para un estudiante. El saldo se calcula con los pagos aplicados.</p>
    </div>
</div>

<form method="post" action="<?= e(url('/payments/charges')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="estudiante_id">Estudiante</label>
                    <select class="form-select" id="estudiante_id" name="estudiante_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= (int) $student['id'] ?>" <?= selected(old('estudiante_id'), $student['id']) ?>>
                                <?= e($student['apellidos'] . ', ' . $student['nombres']) ?> · <?= e($student['codigo']) ?> · <?= e($student['numero_documento']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('estudiante_id')): ?><div class="text-danger small mt-1"><?= e(error('estudiante_id')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="concepto_id">Concepto</label>
                    <select class="form-select" id="concepto_id" name="concepto_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($concepts as $concept): ?>
                            <option value="<?= (int) $concept['id'] ?>" <?= selected(old('concepto_id'), $concept['id']) ?>><?= e($concept['nombre']) ?> (<?= e($concept['codigo']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('concepto_id')): ?><div class="text-danger small mt-1"><?= e(error('concepto_id')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-8">
                    <label class="form-label required" for="descripcion">Descripción</label>
                    <input class="form-control" id="descripcion" name="descripcion" maxlength="180" value="<?= e(old('descripcion')) ?>" required>
                    <?php if (has_error('descripcion')): ?><div class="text-danger small mt-1"><?= e(error('descripcion')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="valor">Valor</label>
                    <input type="number" class="form-control" id="valor" name="valor" min="0" step="0.01" value="<?= e(old('valor')) ?>" required>
                    <?php if (has_error('valor')): ?><div class="text-danger small mt-1"><?= e(error('valor')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="fecha_vencimiento">Fecha de vencimiento</label>
                    <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento" value="<?= e(old('fecha_vencimiento', date('Y-m-d', strtotime('+30 days')))) ?>" required>
                    <?php if (has_error('fecha_vencimiento')): ?><div class="text-danger small mt-1"><?= e(error('fecha_vencimiento')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="matricula_id">Matrícula asociada (opcional)</label>
                    <input type="number" class="form-control" id="matricula_id" name="matricula_id" min="0" value="<?= e(old('matricula_id')) ?>" placeholder="ID de matrícula">
                    <div class="form-text">Déjelo vacío si el cargo no corresponde a una matrícula.</div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('/payments')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar cargo</button>
        </div>
    </div>
</form>
