<?php

declare(strict_types=1);

/** @var array<string,mixed> $group */
/** @var list<array<string,mixed>> $sessions */
/** @var array{rows:list<array<string,mixed>>,sessions:int,alerta:float,perdida:float} $summary */
/** @var bool $owns */
$rows = $summary['rows'];
$alertThreshold = (float) $summary['alerta'];
$lossThreshold = (float) $summary['perdida'];

$attendanceBadge = static function (string $state): string {
    $map = [
        'normal' => ['success', 'Normal'],
        'alerta' => ['warning', 'En alerta'],
        'perdida' => ['danger', 'Pérdida'],
    ];
    [$class, $label] = $map[$state] ?? ['secondary', estado_label($state)];

    return '<span class="badge text-bg-' . $class . '">' . e($label) . '</span>';
};
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/attendance')) ?>">Asistencia</a></li><li class="breadcrumb-item active"><?= e($group['codigo']) ?></li></ol></nav>
        <h2><?= e($group['curso']) ?></h2>
        <p class="text-muted mb-0">
            <?= e($group['codigo']) ?> · <?= e($group['periodo']) ?> · <?= e($group['sede']) ?> · <?= e($group['docente']) ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/attendance')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
    </div>
</div>

<?php if (!$owns): ?>
    <div class="alert alert-info"><i class="bi bi-eye me-2"></i>Vista de consulta. Solo el docente asignado al grupo puede crear sesiones y registrar asistencia.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Inscritos</div><div class="stat-value"><?= (int) $group['inscritos'] ?></div><div class="stat-hint">Capacidad <?= (int) $group['capacidad_max'] ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Sesiones cerradas</div><div class="stat-value"><?= (int) $summary['sessions'] ?></div><div class="stat-hint">Base para el porcentaje</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Umbral de alerta</div><div class="stat-value"><?= number_format($alertThreshold, 1, ',', '.') ?> %</div><div class="stat-hint">Inasistencia acumulada</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Umbral de pérdida</div><div class="stat-value"><?= number_format($lossThreshold, 1, ',', '.') ?> %</div><div class="stat-hint">Marca pérdida por inasistencia</div></div></div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Sesiones</span>
        <?php if ($owns): ?>
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#new-session"><i class="bi bi-plus-lg me-1"></i>Nueva sesión</button>
        <?php endif; ?>
    </div>
    <?php if ($owns): ?>
        <div class="collapse" id="new-session">
            <div class="card-body bg-light border-bottom">
                <form method="post" action="<?= e(url('/attendance/' . $group['id'] . '/sessions')) ?>" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-md-2">
                        <label class="form-label required" for="fecha">Fecha</label>
                        <input type="date" class="form-control" id="fecha" name="fecha" value="<?= e(old('fecha', date('Y-m-d'))) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label required" for="hora_inicio">Inicio</label>
                        <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" value="<?= e(old('hora_inicio')) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label required" for="hora_fin">Fin</label>
                        <input type="time" class="form-control" id="hora_fin" name="hora_fin" value="<?= e(old('hora_fin')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="tema">Tema</label>
                        <input class="form-control" id="tema" name="tema" maxlength="180" value="<?= e(old('tema')) ?>" placeholder="Opcional">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-save me-1"></i>Crear</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Fecha</th><th>Horario</th><th>Tema</th><th class="text-end">Registros</th><th class="text-end">Ausentes</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php if ($sessions === []): ?>
                <tr><td colspan="7"><div class="empty-state compact"><i class="bi bi-calendar-plus"></i><p class="mb-0">Aún no hay sesiones registradas en este grupo.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($sessions as $session): ?>
                <tr>
                    <td><?= e(fecha($session['fecha'])) ?></td>
                    <td class="mono"><?= e(hora($session['hora_inicio'])) ?> – <?= e(hora($session['hora_fin'])) ?></td>
                    <td><?= e($session['tema'] ?? '—') ?></td>
                    <td class="text-end"><?= (int) $session['registros'] ?></td>
                    <td class="text-end"><?= (int) $session['ausentes'] ?></td>
                    <td><?= badge_estado($session['estado']) ?></td>
                    <td class="text-end">
                        <?php if ($owns): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/attendance/' . $group['id'] . '/sessions/' . $session['id'])) ?>"><i class="bi bi-list-check me-1"></i>Registrar</a>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Resumen de inasistencia</span>
        <span class="small text-muted">Las excusas se excluyen de la base de cálculo; las llegadas tarde cuentan como presente.</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Estudiante</th><th class="text-end">Presente</th><th class="text-end">Tarde</th><th class="text-end">Excusa</th><th class="text-end">Ausente</th><th class="text-end">Inasistencia</th><th>Estado</th><th class="text-end">Nota final</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="8"><div class="empty-state compact"><i class="bi bi-people"></i><p class="mb-0">No hay estudiantes inscritos en este grupo.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($row['apellidos'] . ', ' . $row['nombres']) ?></div>
                        <div class="small text-muted mono"><?= e($row['estudiante_codigo']) ?> · <?= e($row['numero_documento']) ?></div>
                    </td>
                    <td class="text-end"><?= (int) $row['presentes'] ?></td>
                    <td class="text-end"><?= (int) $row['tardes'] ?></td>
                    <td class="text-end"><?= (int) $row['excusas'] ?></td>
                    <td class="text-end"><?= (int) $row['ausentes'] ?></td>
                    <td class="text-end fw-semibold"><?= number_format((float) $row['porcentaje_inasistencia'], 1, ',', '.') ?> %</td>
                    <td><?= $attendanceBadge((string) $row['estado_asistencia']) ?></td>
                    <td class="text-end"><?= $row['nota_final'] !== null ? e(number_format((float) $row['nota_final'], 1, ',', '.')) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
