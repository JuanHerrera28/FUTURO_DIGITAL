<?php

declare(strict_types=1);

/** @var array<string,mixed> $group */
/** @var array{evaluations:list<array<string,mixed>>,rows:list<array<string,mixed>>,weight:float} $matrix */
/** @var bool $owns */
$evaluations = $matrix['evaluations'];
$rows = $matrix['rows'];
$weight = (float) $matrix['weight'];
$weightComplete = abs($weight - 100.0) <= 0.01;
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/grades')) ?>">Calificaciones</a></li><li class="breadcrumb-item active"><?= e($group['codigo']) ?></li></ol></nav>
        <h2><?= e($group['curso']) ?></h2>
        <p class="text-muted mb-0">
            <?= e($group['codigo']) ?> · <?= e($group['periodo']) ?> · <?= e($group['sede']) ?> · <?= e($group['docente']) ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/grades')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
    </div>
</div>

<?php if (!$owns): ?>
    <div class="alert alert-info"><i class="bi bi-eye me-2"></i>Vista de consulta. Solo el docente asignado al grupo puede registrar o modificar calificaciones.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Inscritos</div><div class="stat-value"><?= (int) $group['inscritos'] ?></div><div class="stat-hint">Capacidad <?= (int) $group['capacidad_max'] ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Evaluaciones activas</div><div class="stat-value"><?= count(array_filter($evaluations, static fn (array $e): bool => (string) $e['estado'] === 'activa')) ?></div><div class="stat-hint"><?= count($evaluations) ?> en total</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Peso acumulado</div><div class="stat-value <?= $weightComplete ? 'text-success' : 'text-warning' ?>"><?= number_format($weight, 1, ',', '.') ?> %</div><div class="stat-hint">Debe sumar 100 %</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Estado del periodo</div><div class="stat-value"><?= badge_estado($group['periodo_estado']) ?></div><div class="stat-hint"><?= e($group['aula']) ?></div></div></div>
</div>

<?php if (!$weightComplete): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Los pesos de las evaluaciones activas suman <strong><?= number_format($weight, 1, ',', '.') ?> %</strong>. Antes de calificar deben sumar exactamente 100 % (RN-13).</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Evaluaciones</span>
        <?php if ($owns): ?>
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#new-evaluation"><i class="bi bi-plus-lg me-1"></i>Nueva evaluación</button>
        <?php endif; ?>
    </div>
    <?php if ($owns): ?>
        <div class="collapse" id="new-evaluation">
            <div class="card-body bg-light border-bottom">
                <form method="post" action="<?= e(url('/grades/' . $group['id'] . '/evaluations')) ?>" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-md-5">
                        <label class="form-label required" for="nombre">Nombre</label>
                        <input class="form-control" id="nombre" name="nombre" maxlength="120" value="<?= e(old('nombre')) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label required" for="peso">Peso (%)</label>
                        <input type="number" class="form-control" id="peso" name="peso" min="0.1" max="100" step="0.01" value="<?= e(old('peso')) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="fecha">Fecha</label>
                        <input type="date" class="form-control" id="fecha" name="fecha" value="<?= e(old('fecha')) ?>">
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
            <thead><tr><th>Evaluación</th><th class="text-end">Peso</th><th>Fecha</th><th class="text-end">Calificadas</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php if ($evaluations === []): ?>
                <tr><td colspan="6"><div class="empty-state compact"><i class="bi bi-journal-text"></i><p class="mb-0">Aún no hay evaluaciones registradas en este grupo.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($evaluations as $evaluation): ?>
                <tr>
                    <td class="fw-semibold"><?= e($evaluation['nombre']) ?></td>
                    <td class="text-end"><?= number_format((float) $evaluation['peso'], 1, ',', '.') ?> %</td>
                    <td><?= e(fecha($evaluation['fecha'])) ?></td>
                    <td class="text-end"><?= (int) $evaluation['calificadas'] ?></td>
                    <td><?= badge_estado($evaluation['estado']) ?></td>
                    <td class="text-end">
                        <?php if ($owns): ?>
                            <div class="table-actions">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/grades/' . $group['id'] . '/evaluations/' . $evaluation['id'])) ?>" title="Calificar"><i class="bi bi-pencil-square"></i></a>
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-eval-<?= (int) $evaluation['id'] ?>" title="Editar"><i class="bi bi-sliders"></i></button>
                                <?php if ((string) $evaluation['estado'] === 'activa'): ?>
                                    <form method="post" action="<?= e(url('/grades/' . $group['id'] . '/evaluations/' . $evaluation['id'] . '/deactivate')) ?>" class="d-inline" data-confirm="¿Inactivar esta evaluación?">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Inactivar"><i class="bi bi-pause-circle"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($owns): ?>
                    <tr class="collapse" id="edit-eval-<?= (int) $evaluation['id'] ?>">
                        <td colspan="6" class="bg-light">
                            <form method="post" action="<?= e(url('/grades/' . $group['id'] . '/evaluations/' . $evaluation['id'])) ?>" class="row g-2 align-items-end">
                                <?= csrf_field() ?>
                                <div class="col-md-4">
                                    <label class="form-label" for="ev_nombre_<?= (int) $evaluation['id'] ?>">Nombre</label>
                                    <input class="form-control" id="ev_nombre_<?= (int) $evaluation['id'] ?>" name="nombre" maxlength="120" value="<?= e($evaluation['nombre']) ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" for="ev_peso_<?= (int) $evaluation['id'] ?>">Peso (%)</label>
                                    <input type="number" class="form-control" id="ev_peso_<?= (int) $evaluation['id'] ?>" name="peso" min="0.1" max="100" step="0.01" value="<?= e($evaluation['peso']) ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" for="ev_fecha_<?= (int) $evaluation['id'] ?>">Fecha</label>
                                    <input type="date" class="form-control" id="ev_fecha_<?= (int) $evaluation['id'] ?>" name="fecha" value="<?= e($evaluation['fecha']) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" for="ev_estado_<?= (int) $evaluation['id'] ?>">Estado</label>
                                    <select class="form-select" id="ev_estado_<?= (int) $evaluation['id'] ?>" name="estado">
                                        <?php foreach (['activa', 'inactiva'] as $state): ?>
                                            <option value="<?= e($state) ?>" <?= selected($evaluation['estado'], $state) ?>><?= e(estado_label($state)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-save me-1"></i>Guardar</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">Planilla consolidada</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <?php foreach ($evaluations as $evaluation): ?>
                        <th class="text-end">
                            <div><?= e($evaluation['nombre']) ?></div>
                            <div class="small text-muted"><?= number_format((float) $evaluation['peso'], 1, ',', '.') ?> %</div>
                        </th>
                    <?php endforeach; ?>
                    <th class="text-end">Nota final</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="<?= count($evaluations) + 2 ?>"><div class="empty-state compact"><i class="bi bi-people"></i><p class="mb-0">No hay estudiantes inscritos en este grupo.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($row['apellidos'] . ', ' . $row['nombres']) ?></div>
                        <div class="small text-muted mono"><?= e($row['estudiante_codigo']) ?> · <?= e($row['numero_documento']) ?></div>
                    </td>
                    <?php foreach ($evaluations as $evaluation): ?>
                        <?php $note = $row['notas'][(int) $evaluation['id']] ?? null; ?>
                        <td class="text-end <?= (string) $evaluation['estado'] === 'inactiva' ? 'text-muted' : '' ?>">
                            <?= $note !== null ? e(number_format((float) $note, 1, ',', '.')) : '—' ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="text-end">
                        <?php if ($row['final']['nota'] !== null): ?>
                            <div class="fw-bold <?= (float) $row['final']['nota'] < 3.0 ? 'text-danger' : 'text-success' ?>"><?= e(number_format((float) $row['final']['nota'], 1, ',', '.')) ?></div>
                            <div class="small text-muted"><?= number_format((float) $row['final']['peso_evaluado'], 1, ',', '.') ?> % evaluado</div>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
