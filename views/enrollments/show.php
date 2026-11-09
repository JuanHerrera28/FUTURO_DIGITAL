<?php

declare(strict_types=1);

/** @var array<string,mixed> $matricula */
/** @var list<array<string,mixed>> $inscriptions */
/** @var list<array<string,mixed>> $availableGroups */
$canManage = can('enrollment.manage');
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/enrollments')) ?>">Matrículas</a></li><li class="breadcrumb-item active"><?= e($matricula['codigo']) ?></li></ol></nav>
        <h2><?= e($matricula['apellidos'] . ', ' . $matricula['nombres']) ?></h2>
        <p class="text-muted mb-0"><?= e($matricula['codigo']) ?> · <?= e($matricula['programa']) ?> · <?= e($matricula['periodo']) ?> · <?= badge_estado($matricula['estado']) ?></p>
    </div>
    <div class="page-actions">
        <?php if (can('payments.view')): ?>
            <a href="<?= e(url('/payments/statement/' . $matricula['estudiante_id'])) ?>" class="btn btn-outline-secondary"><i class="bi bi-cash-coin me-1"></i>Estado de cuenta</a>
        <?php endif; ?>
        <?php if (can('students.view')): ?>
            <a href="<?= e(url('/students/' . $matricula['estudiante_id'])) ?>" class="btn btn-outline-secondary"><i class="bi bi-person me-1"></i>Ficha del estudiante</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Datos de la matrícula</div>
            <div class="card-body">
                <div class="detail-grid">
                    <div><span>Código</span><strong class="mono"><?= e($matricula['codigo']) ?></strong></div>
                    <div><span>Estado</span><strong><?= badge_estado($matricula['estado']) ?></strong></div>
                    <div><span>Programa</span><strong><?= e($matricula['programa']) ?></strong></div>
                    <div><span>Periodo</span><strong><?= e($matricula['periodo']) ?></strong></div>
                    <div><span>Sede</span><strong><?= e($matricula['sede']) ?></strong></div>
                    <div><span>Fecha</span><strong><?= e(fecha($matricula['fecha'])) ?></strong></div>
                    <div><span>Documento</span><strong><?= e($matricula['numero_documento']) ?></strong></div>
                    <div><span>Estudiante</span><strong class="mono"><?= e($matricula['estudiante_codigo']) ?></strong></div>
                    <div><span>Correo</span><strong><?= e($matricula['email'] ?? '—') ?></strong></div>
                    <div><span>Teléfono</span><strong><?= e($matricula['telefono'] ?? '—') ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Inscripciones a grupos</span>
                <span class="badge text-bg-light border"><?= count($inscriptions) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Curso</th><th>Grupo</th><th>Aula</th><th>Docente</th><th>Estado</th><th class="text-end">Nota</th><?php if ($canManage): ?><th class="text-end">Acciones</th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php if ($inscriptions === []): ?>
                        <tr><td colspan="<?= $canManage ? 7 : 6 ?>"><div class="empty-state compact"><i class="bi bi-journal-plus"></i><p class="mb-0">Sin cursos inscritos. Use el formulario inferior para inscribir.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($inscriptions as $inscription): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($inscription['curso']) ?></div>
                                <div class="small text-muted mono"><?= e($inscription['curso_codigo']) ?></div>
                            </td>
                            <td class="mono"><?= e($inscription['grupo']) ?></td>
                            <td><?= e($inscription['aula']) ?></td>
                            <td><?= e($inscription['docente']) ?></td>
                            <td><?= badge_estado($inscription['estado']) ?></td>
                            <td class="text-end fw-semibold"><?= $inscription['nota_final'] !== null ? e(number_format((float) $inscription['nota_final'], 1, ',', '.')) : '—' ?></td>
                            <?php if ($canManage): ?>
                                <td class="text-end">
                                    <?php if ((string) $inscription['estado'] === 'activa'): ?>
                                        <div class="d-flex gap-1 justify-content-end flex-wrap">
                                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#change-<?= (int) $inscription['id'] ?>"><i class="bi bi-arrow-left-right"></i></button>
                                            <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#cancel-<?= (int) $inscription['id'] ?>"><i class="bi bi-x-lg"></i></button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php if ($canManage && (string) $inscription['estado'] === 'activa'): ?>
                            <tr class="collapse" id="change-<?= (int) $inscription['id'] ?>">
                                <td colspan="<?= $canManage ? 7 : 6 ?>" class="bg-light">
                                    <form method="post" action="<?= e(url('/enrollments/inscriptions/' . $inscription['id'] . '/change-group')) ?>" class="row g-2 align-items-end">
                                        <?= csrf_field() ?>
                                        <div class="col-md-5">
                                            <label class="form-label" for="new_group_<?= (int) $inscription['id'] ?>">Nuevo grupo</label>
                                            <select class="form-select" id="new_group_<?= (int) $inscription['id'] ?>" name="grupo_id" required>
                                                <option value="">Seleccione…</option>
                                                <?php foreach ($availableGroups as $group): ?>
                                                    <option value="<?= (int) $group['id'] ?>"><?= e($group['curso']) ?> · <?= e($group['codigo']) ?> · <?= e($group['docente']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="motivo_<?= (int) $inscription['id'] ?>">Motivo</label>
                                            <input class="form-control" id="motivo_<?= (int) $inscription['id'] ?>" name="motivo" maxlength="255" required>
                                        </div>
                                        <div class="col-md-3">
                                            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-arrow-left-right me-1"></i>Cambiar grupo</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            <tr class="collapse" id="cancel-<?= (int) $inscription['id'] ?>">
                                <td colspan="<?= $canManage ? 7 : 6 ?>" class="bg-light">
                                    <form method="post" action="<?= e(url('/enrollments/inscriptions/' . $inscription['id'] . '/cancel')) ?>" class="row g-2 align-items-end" data-confirm="¿Cancelar esta inscripción?">
                                        <?= csrf_field() ?>
                                        <div class="col-md-9">
                                            <label class="form-label" for="cancel_motivo_<?= (int) $inscription['id'] ?>">Motivo de cancelación</label>
                                            <input class="form-control" id="cancel_motivo_<?= (int) $inscription['id'] ?>" name="motivo" maxlength="255" required>
                                        </div>
                                        <div class="col-md-3">
                                            <button class="btn btn-danger w-100" type="submit"><i class="bi bi-x-lg me-1"></i>Cancelar inscripción</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($inscription['movimientos'] !== []): ?>
                            <tr>
                                <td colspan="<?= $canManage ? 7 : 6 ?>" class="bg-white">
                                    <div class="text-tiny text-muted mb-1">Movimientos recientes</div>
                                    <ul class="timeline mb-0">
                                        <?php foreach ($inscription['movimientos'] as $movement): ?>
                                            <li>
                                                <div class="time"><?= e(fecha_hora($movement['created_at'])) ?> · <?= e($movement['usuario'] ?? 'Sistema') ?></div>
                                                <div class="small">
                                                    <strong><?= e(estado_label($movement['tipo'])) ?></strong>
                                                    <?php if (!empty($movement['grupo_origen']) || !empty($movement['grupo_destino'])): ?>
                                                        · <?= e($movement['grupo_origen'] ?? '—') ?> → <?= e($movement['grupo_destino'] ?? '—') ?>
                                                    <?php endif; ?>
                                                    <?php if (!empty($movement['motivo'])): ?>
                                                        <div class="text-muted"><?= e($movement['motivo']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
    <div class="card mt-4">
        <div class="card-header">Inscribir en un nuevo grupo</div>
        <div class="card-body">
            <?php if ($availableGroups === []): ?>
                <div class="alert alert-secondary mb-0"><i class="bi bi-info-circle me-2"></i>No hay grupos disponibles del mismo periodo y sede, o la matrícula no está vigente.</div>
            <?php else: ?>
                <form method="post" action="<?= e(url('/enrollments/' . $matricula['id'] . '/enroll')) ?>" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-md-8">
                        <label class="form-label required" for="grupo_id">Grupo disponible</label>
                        <select class="form-select" id="grupo_id" name="grupo_id" required>
                            <option value="">Seleccione…</option>
                            <?php foreach ($availableGroups as $group): ?>
                                <?php $cupos = max(0, (int) $group['capacidad_max'] - (int) $group['inscritos']); ?>
                                <option value="<?= (int) $group['id'] ?>" <?= $cupos <= 0 ? 'disabled' : '' ?>>
                                    <?= e($group['curso']) ?> · <?= e($group['codigo']) ?> · <?= e($group['docente']) ?> · <?= $cupos ?> cupo(s)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-journal-plus me-1"></i>Inscribir</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
