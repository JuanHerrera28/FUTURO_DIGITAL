<?php

declare(strict_types=1);

/** @var array<string,mixed> $group */
/** @var array<string,mixed> $session */
/** @var list<array<string,mixed>> $rows */
$states = [
    'presente' => 'Presente',
    'tarde' => 'Tarde (cuenta presente)',
    'excusa' => 'Excusa',
    'ausente' => 'Ausente',
];
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/attendance')) ?>">Asistencia</a></li><li class="breadcrumb-item"><a href="<?= e(url('/attendance/' . $group['id'])) ?>"><?= e($group['codigo']) ?></a></li><li class="breadcrumb-item active">Sesión</li></ol></nav>
        <h2>Sesión de asistencia</h2>
        <p class="text-muted mb-0">
            <?= e($group['curso']) ?> · <?= e($group['codigo']) ?> · <?= e(fecha($session['fecha'])) ?> · <?= e(hora($session['hora_inicio'])) ?>–<?= e(hora($session['hora_fin'])) ?>
            <?php if (!empty($session['tema'])): ?> · <?= e($session['tema']) ?><?php endif; ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/attendance/' . $group['id'])) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver al grupo</a>
    </div>
</div>

<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Al guardar, la sesión queda cerrada y se recalculan las alertas de inasistencia del grupo. Una inasistencia superior al umbral marca la pérdida del curso.</div>

<form method="post" action="<?= e(url('/attendance/' . $group['id'] . '/sessions/' . $session['id'])) ?>">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Registro de asistencia</span>
            <span class="badge text-bg-light border"><?= count($rows) ?> estudiante(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Estudiante</th><th style="width:220px">Estado</th><th>Observación</th></tr></thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="3"><div class="empty-state compact"><i class="bi bi-people"></i><p class="mb-0">No hay estudiantes activos inscritos en el grupo.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $inscripcionId = (int) $row['inscripcion_id'];
                    $current = (string) ($row['estado'] ?? 'presente');
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($row['apellidos'] . ', ' . $row['nombres']) ?></div>
                            <div class="small text-muted mono"><?= e($row['estudiante_codigo']) ?> · <?= e($row['numero_documento']) ?></div>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="estados[<?= $inscripcionId ?>]" aria-label="Estado de <?= e($row['nombres'] . ' ' . $row['apellidos']) ?>">
                                <?php foreach ($states as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= selected($current, $value) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm" name="observaciones[<?= $inscripcionId ?>]" maxlength="255"
                                   value="<?= e($row['observacion'] ?? '') ?>" placeholder="Opcional. Obligatorio para excusas." aria-label="Observación de <?= e($row['nombres'] . ' ' . $row['apellidos']) ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($rows !== []): ?>
            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="<?= e(url('/attendance/' . $group['id'])) ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar asistencia</button>
            </div>
        <?php endif; ?>
    </div>
</form>
