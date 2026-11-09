<?php

declare(strict_types=1);

/** @var array<string,mixed> $group */
/** @var list<array<string,mixed>> $schedules */
/** @var list<array<string,mixed>> $students */
/** @var int $seats */
$days = dias_semana();
$ocupacion = porcentaje((int) $group['inscritos'], (int) $group['capacidad_max']);
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/groups')) ?>">Grupos</a></li><li class="breadcrumb-item active"><?= e($group['codigo']) ?></li></ol></nav>
        <h2><?= e($group['curso']) ?></h2>
        <p class="text-muted mb-0"><?= e($group['codigo']) ?> · <?= e($group['periodo']) ?> · <?= e($group['sede']) ?> · <?= badge_estado($group['estado']) ?></p>
    </div>
    <div class="page-actions">
        <?php if (can('grades.view')): ?>
            <a href="<?= e(url('/grades/' . $group['id'])) ?>" class="btn btn-outline-secondary"><i class="bi bi-clipboard-check me-1"></i>Calificaciones</a>
        <?php endif; ?>
        <?php if (can('attendance.view')): ?>
            <a href="<?= e(url('/attendance/' . $group['id'])) ?>" class="btn btn-outline-secondary"><i class="bi bi-calendar-check me-1"></i>Asistencia</a>
        <?php endif; ?>
        <?php if (can('groups.manage')): ?>
            <a href="<?= e(url('/groups/' . $group['id'] . '/edit')) ?>" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Datos del grupo</div>
            <div class="card-body">
                <div class="detail-grid">
                    <div><span>Código</span><strong class="mono"><?= e($group['codigo']) ?></strong></div>
                    <div><span>Periodo</span><strong><?= e($group['periodo']) ?></strong></div>
                    <div><span>Curso</span><strong><?= e($group['curso']) ?></strong></div>
                    <div><span>Código curso</span><strong class="mono"><?= e($group['curso_codigo']) ?></strong></div>
                    <div><span>Sede</span><strong><?= e($group['sede']) ?></strong></div>
                    <div><span>Aula</span><strong><?= e($group['aula']) ?></strong></div>
                    <div><span>Docente</span><strong><?= e($group['docente']) ?></strong></div>
                    <div><span>Estado</span><strong><?= badge_estado($group['estado']) ?></strong></div>
                </div>

                <div class="form-section-title mt-4">Cupos</div>
                <div class="d-flex justify-content-between small mb-1">
                    <span><?= (int) $group['inscritos'] ?> inscritos de <?= (int) $group['capacidad_max'] ?></span>
                    <span class="<?= $seats <= 0 ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $seats ?> disponible(s)</span>
                </div>
                <div class="progress" role="progressbar" aria-valuenow="<?= (int) $ocupacion ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar <?= $ocupacion >= 100 ? 'bg-danger' : ($ocupacion >= 80 ? 'bg-warning' : 'bg-success') ?>" style="width: <?= min(100, $ocupacion) ?>%"></div>
                </div>
                <?php if ((int) $group['inscritos'] < (int) $group['cupo_minimo']): ?>
                    <div class="alert alert-warning mt-3 mb-0 small"><i class="bi bi-exclamation-triangle me-1"></i>El grupo está por debajo del cupo mínimo de <?= (int) $group['cupo_minimo'] ?> estudiantes (RN-03).</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Horario asignado</div>
            <div class="card-body">
                <?php if ($schedules === []): ?>
                    <div class="empty-state compact"><i class="bi bi-clock"></i><p class="mb-0">Sin bloques de horario activos.</p></div>
                <?php else: ?>
                    <ul class="schedule-list">
                        <?php foreach ($schedules as $schedule): ?>
                            <li>
                                <span class="day"><?= e($days[(int) $schedule['dia_semana']] ?? '—') ?></span>
                                <span class="mono"><?= e(hora($schedule['hora_inicio'])) ?> – <?= e(hora($schedule['hora_fin'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Estudiantes inscritos</span>
        <span class="badge text-bg-light border"><?= count($students) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Código</th><th>Estudiante</th><th>Documento</th><th>Matrícula</th><th>Estado</th><th class="text-end">Nota final</th></tr></thead>
            <tbody>
            <?php if ($students === []): ?>
                <tr><td colspan="6"><div class="empty-state compact"><i class="bi bi-people"></i><p class="mb-0">Aún no hay estudiantes inscritos en este grupo.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($students as $student): ?>
                <tr>
                    <td class="mono"><?= e($student['estudiante_codigo']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($student['apellidos'] . ', ' . $student['nombres']) ?></div>
                    </td>
                    <td><?= e($student['numero_documento']) ?></td>
                    <td class="mono"><?= e($student['matricula']) ?></td>
                    <td><?= badge_estado($student['estado']) ?></td>
                    <td class="text-end fw-semibold"><?= $student['nota_final'] !== null ? e(number_format((float) $student['nota_final'], 1, ',', '.')) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
