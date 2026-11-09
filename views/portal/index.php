<?php

declare(strict_types=1);

/** @var array<string,mixed> $student */
/** @var array<string,mixed>|null $matricula */
/** @var list<array<string,mixed>> $grades */
/** @var list<array<string,mixed>> $attendance */
/** @var array{charges:list<array<string,mixed>>,total:float,pagado:float,saldo:float,vencido:float} $statement */
/** @var list<array<string,mixed>> $schedule */
/** @var list<array<string,mixed>> $alerts */
/** @var list<array<string,mixed>> $guardians */
$days = dias_semana();
$attendanceBadge = static function (string $state): string {
    $map = ['normal' => ['success', 'Normal'], 'alerta' => ['warning', 'En alerta'], 'perdida' => ['danger', 'Pérdida']];
    [$class, $label] = $map[$state] ?? ['secondary', estado_label($state)];

    return '<span class="badge text-bg-' . $class . '">' . e($label) . '</span>';
};
$visualBadge = static function (string $state): string {
    $map = ['pagado' => ['success', 'Pagado'], 'vencido' => ['danger', 'Vencido'], 'pendiente' => ['warning', 'Pendiente']];
    [$class, $label] = $map[$state] ?? ['secondary', estado_label($state)];

    return '<span class="badge text-bg-' . $class . '">' . e($label) . '</span>';
};
$levelBadge = static function (string $level): string {
    $map = ['informativa' => 'info', 'advertencia' => 'warning', 'critica' => 'danger'];

    return '<span class="badge text-bg-' . ($map[$level] ?? 'secondary') . '">' . e(estado_label($level)) . '</span>';
};
?>
<div class="page-header">
    <div>
        <h2>Hola, <?= e($student['nombres']) ?></h2>
        <p class="text-muted mb-0">
            <?= e($student['codigo']) ?> · <?= e($student['tipo_documento']) ?> <?= e($student['numero_documento']) ?> · <?= e($student['sede']) ?>
        </p>
    </div>
    <div class="page-actions">
        <span class="badge text-bg-light border fs-6">Portal del estudiante</span>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Saldo pendiente</div>
            <div class="stat-value <?= (float) $statement['saldo'] > 0.01 ? 'text-danger' : 'text-success' ?>"><?= e(money($statement['saldo'])) ?></div>
            <div class="stat-hint">Vencido <?= e(money($statement['vencido'])) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Cursos inscritos</div>
            <div class="stat-value"><?= count($grades) ?></div>
            <div class="stat-hint">Histórico de inscripciones</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Alertas abiertas</div>
            <div class="stat-value"><?= count(array_filter($alerts, static fn (array $a): bool => (string) $a['estado'] === 'nueva')) ?></div>
            <div class="stat-hint"><?= count($alerts) ?> en total</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">Programa</div>
            <div class="stat-value fs-5"><?= e($matricula['programa'] ?? '—') ?></div>
            <div class="stat-hint"><?= e($matricula['periodo'] ?? 'Sin matrícula activa') ?></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Mi matrícula</div>
            <div class="card-body">
                <?php if ($matricula === null): ?>
                    <div class="alert alert-secondary mb-0"><i class="bi bi-info-circle me-2"></i>No tiene una matrícula activa registrada.</div>
                <?php else: ?>
                    <div class="detail-grid">
                        <div><span>Código</span><strong class="mono"><?= e($matricula['codigo']) ?></strong></div>
                        <div><span>Estado</span><strong><?= badge_estado($matricula['estado']) ?></strong></div>
                        <div><span>Programa</span><strong><?= e($matricula['programa']) ?></strong></div>
                        <div><span>Periodo</span><strong><?= e($matricula['periodo']) ?></strong></div>
                        <div><span>Inicio</span><strong><?= e(fecha($matricula['fecha_inicio'])) ?></strong></div>
                        <div><span>Fin</span><strong><?= e(fecha($matricula['fecha_fin'])) ?></strong></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Mi horario</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Día</th><th>Horario</th><th>Curso</th><th>Grupo</th><th>Aula</th><th>Docente</th></tr></thead>
                    <tbody>
                    <?php if ($schedule === []): ?>
                        <tr><td colspan="6"><div class="empty-state compact"><i class="bi bi-calendar-week"></i><p class="mb-0">No hay horarios registrados.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($schedule as $block): ?>
                        <tr>
                            <td><?= e($days[(int) $block['dia_semana']] ?? '—') ?></td>
                            <td class="mono"><?= e(hora($block['hora_inicio'])) ?>–<?= e(hora($block['hora_fin'])) ?></td>
                            <td>
                                <div class="fw-semibold"><?= e($block['curso']) ?></div>
                                <div class="small text-muted mono"><?= e($block['curso_codigo']) ?></div>
                            </td>
                            <td class="mono"><?= e($block['grupo']) ?></td>
                            <td><?= e($block['aula']) ?></td>
                            <td><?= e($block['docente']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">Mis calificaciones</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Curso</th><th>Grupo</th><th>Periodo</th><th>Detalle</th><th class="text-end">Nota final</th><th>Estado</th></tr></thead>
            <tbody>
            <?php if ($grades === []): ?>
                <tr><td colspan="6"><div class="empty-state compact"><i class="bi bi-clipboard-check"></i><p class="mb-0">Aún no tiene calificaciones registradas.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($grades as $grade): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($grade['curso']) ?></div>
                        <div class="small text-muted mono"><?= e($grade['curso_codigo']) ?></div>
                    </td>
                    <td class="mono"><?= e($grade['grupo']) ?></td>
                    <td><?= e($grade['periodo']) ?> <?= badge_estado($grade['periodo_estado']) ?></td>
                    <td class="small text-muted"><?= e($grade['detalle'] ?? '—') ?></td>
                    <td class="text-end fw-bold"><?= $grade['nota_final'] !== null ? e(number_format((float) $grade['nota_final'], 1, ',', '.')) : '—' ?></td>
                    <td><?= badge_estado($grade['estado']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">Mi asistencia</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Curso</th><th>Grupo</th><th class="text-end">Sesiones</th><th class="text-end">Ausencias</th><th class="text-end">Excusas</th><th class="text-end">Tardes</th><th class="text-end">Inasistencia</th><th>Estado</th></tr></thead>
            <tbody>
            <?php if ($attendance === []): ?>
                <tr><td colspan="8"><div class="empty-state compact"><i class="bi bi-calendar-check"></i><p class="mb-0">Aún no hay registros de asistencia.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($attendance as $row): ?>
                <tr>
                    <td class="fw-semibold"><?= e($row['curso']) ?></td>
                    <td class="mono"><?= e($row['grupo']) ?></td>
                    <td class="text-end"><?= (int) $row['sesiones'] ?></td>
                    <td class="text-end"><?= (int) $row['ausentes'] ?></td>
                    <td class="text-end"><?= (int) $row['excusas'] ?></td>
                    <td class="text-end"><?= (int) $row['tardes'] ?></td>
                    <td class="text-end fw-semibold"><?= number_format((float) $row['porcentaje_inasistencia'], 1, ',', '.') ?> %</td>
                    <td><?= $attendanceBadge((string) $row['estado_asistencia']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Mi estado de cuenta</span>
                <span class="small text-muted">Saldo <?= e(money($statement['saldo'])) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Concepto</th><th>Vencimiento</th><th class="text-end">Valor</th><th class="text-end">Saldo</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php if ($statement['charges'] === []): ?>
                        <tr><td colspan="5"><div class="empty-state compact"><i class="bi bi-cash-stack"></i><p class="mb-0">No tiene cargos registrados.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($statement['charges'] as $charge): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($charge['concepto']) ?></div>
                                <div class="small text-muted"><?= e(str_limit($charge['descripcion'], 50)) ?></div>
                            </td>
                            <td><?= e(fecha($charge['fecha_vencimiento'])) ?></td>
                            <td class="text-end"><?= e(money($charge['valor_total'])) ?></td>
                            <td class="text-end fw-semibold"><?= e(money($charge['saldo'])) ?></td>
                            <td><?= $visualBadge((string) $charge['estado_visual']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Mis alertas</div>
            <div class="card-body">
                <?php if ($alerts === []): ?>
                    <div class="alert alert-success mb-0"><i class="bi bi-check-circle me-2"></i>No tiene alertas registradas.</div>
                <?php else: ?>
                    <ul class="timeline mb-0">
                        <?php foreach ($alerts as $alert): ?>
                            <li>
                                <div class="time"><?= e(fecha_hora($alert['created_at'])) ?> · <?= $levelBadge((string) $alert['nivel']) ?></div>
                                <div class="small">
                                    <strong><?= e(estado_label($alert['tipo'])) ?></strong>
                                    <span class="text-muted">· <?= e(estado_label($alert['estado'])) ?></span>
                                    <div><?= e($alert['mensaje']) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">Mis acudientes</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Nombre</th><th>Documento</th><th>Parentesco</th><th>Teléfono</th><th>Estado</th></tr></thead>
            <tbody>
            <?php if ($guardians === []): ?>
                <tr><td colspan="5"><div class="empty-state compact"><i class="bi bi-person-lines-fill"></i><p class="mb-0">No hay acudientes registrados.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($guardians as $guardian): ?>
                <tr>
                    <td class="fw-semibold"><?= e($guardian['nombre']) ?></td>
                    <td><?= e($guardian['documento'] ?? '—') ?></td>
                    <td><?= e($guardian['parentesco'] ?? '—') ?></td>
                    <td><?= e($guardian['telefono'] ?? '—') ?></td>
                    <td><?= badge_estado($guardian['estado']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
