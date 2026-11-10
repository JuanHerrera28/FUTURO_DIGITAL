<?php

declare(strict_types=1);

/** @var array<string,mixed> $group */
/** @var array<string,mixed> $evaluation */
/** @var list<array<string,mixed>> $rows */
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/grades')) ?>">Calificaciones</a></li><li class="breadcrumb-item"><a href="<?= e(url('/grades/' . $group['id'])) ?>"><?= e($group['codigo']) ?></a></li><li class="breadcrumb-item active"><?= e($evaluation['nombre']) ?></li></ol></nav>
        <h2><?= e($evaluation['nombre']) ?></h2>
        <p class="text-muted mb-0">
            <?= e($group['curso']) ?> · <?= e($group['codigo']) ?> · Peso <?= number_format((float) $evaluation['peso'], 1, ',', '.') ?> % · <?= e(fecha($evaluation['fecha'])) ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/grades/' . $group['id'])) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver al grupo</a>
    </div>
</div>

<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>La planilla solo puede guardarse cuando los pesos de las evaluaciones activas del grupo suman 100 %. Las notas se registran con un decimal.</div>

<form method="post" action="<?= e(url('/grades/' . $group['id'] . '/evaluations/' . $evaluation['id'] . '/notas')) ?>">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Registro de notas</span>
            <span class="badge text-bg-light border"><?= count($rows) ?> estudiante(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Estudiante</th><th class="text-end">Nota final actual</th><th style="width:140px">Nota</th><th>Observación</th></tr></thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="4"><div class="empty-state compact"><i class="bi bi-people"></i><p class="mb-0">No hay estudiantes activos inscritos en el grupo.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php $inscripcionId = (int) $row['inscripcion_id']; ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($row['apellidos'] . ', ' . $row['nombres']) ?></div>
                            <div class="small text-muted mono"><?= e($row['estudiante_codigo']) ?> · <?= e($row['numero_documento']) ?></div>
                        </td>
                        <td class="text-end">
                            <?= $row['nota_final'] !== null ? e(number_format((float) $row['nota_final'], 1, ',', '.')) : '—' ?>
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm" name="notas[<?= $inscripcionId ?>]" min="0" max="5" step="0.1"
                                   value="<?= $row['nota'] !== null ? e(number_format((float) $row['nota'], 1, '.', '')) : '' ?>"
                                   placeholder="0.0" aria-label="Nota de <?= e($row['nombres'] . ' ' . $row['apellidos']) ?>">
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm" name="observaciones[<?= $inscripcionId ?>]" maxlength="255"
                                   value="<?= e($row['observacion'] ?? '') ?>" placeholder="Opcional" aria-label="Observación de <?= e($row['nombres'] . ' ' . $row['apellidos']) ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($rows !== []): ?>
            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="<?= e(url('/grades/' . $group['id'])) ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar notas</button>
            </div>
        <?php endif; ?>
    </div>
</form>
