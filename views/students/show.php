<?php

declare(strict_types=1);

/** @var array<string,mixed> $student */
/** @var list<array<string,mixed>> $guardians */
/** @var list<array<string,mixed>> $history */
/** @var list<array<string,mixed>> $matriculas */
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/students')) ?>">Estudiantes</a></li><li class="breadcrumb-item active"><?= e($student['codigo'] ?? '') ?></li></ol></nav>
        <h2><?= e($student['apellidos'] . ', ' . $student['nombres']) ?></h2>
        <p class="text-muted mb-0">
            <?= e($student['tipo_documento']) ?> <?= e($student['numero_documento']) ?> · <?= e($student['sede']) ?>
        </p>
    </div>
    <div class="page-actions">
        <?php if (can('payments.view')): ?>
            <a href="<?= e(url('/payments/statement/' . $student['id'])) ?>" class="btn btn-outline-secondary"><i class="bi bi-cash-coin me-1"></i>Estado de cuenta</a>
        <?php endif; ?>
        <?php if (can('enrollment.manage')): ?>
            <a href="<?= e(url('/enrollments/create?estudiante_id=' . $student['id'])) ?>" class="btn btn-outline-success"><i class="bi bi-person-plus me-1"></i>Matricular</a>
        <?php endif; ?>
        <?php if (can('students.manage')): ?>
            <a href="<?= e(url('/students/' . $student['id'] . '/edit')) ?>" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Datos del estudiante</span>
                <?= badge_estado($student['estado']) ?>
            </div>
            <div class="card-body">
                <div class="detail-grid">
                    <div><span>Código</span><strong class="mono"><?= e($student['codigo'] ?? '—') ?></strong></div>
                    <div><span>Documento</span><strong><?= e($student['tipo_documento'] . ' ' . $student['numero_documento']) ?></strong></div>
                    <div><span>Fecha de nacimiento</span><strong><?= e(fecha($student['fecha_nacimiento'])) ?></strong></div>
                    <div><span>Sede</span><strong><?= e($student['sede']) ?></strong></div>
                    <div><span>Correo</span><strong><?= e($student['email'] ?? '—') ?></strong></div>
                    <div><span>Teléfono</span><strong><?= e($student['telefono'] ?? '—') ?></strong></div>
                    <div class="wide"><span>Dirección</span><strong><?= e($student['direccion'] ?? '—') ?></strong></div>
                </div>

                <div class="form-section-title mt-4">Autorización de datos</div>
                <div class="detail-grid">
                    <div><span>Autoriza</span><strong><?= (int) ($student['autorizacion_datos'] ?? 0) === 1 ? 'Sí' : 'No' ?></strong></div>
                    <div><span>Fecha</span><strong><?= e(fecha($student['fecha_autorizacion'] ?? null)) ?></strong></div>
                    <div class="wide"><span>Medio</span><strong><?= e($student['medio_autorizacion'] ?? '—') ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7" id="acudientes">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Acudientes</span>
                <span class="badge text-bg-light border"><?= count($guardians) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Nombre</th><th>Parentesco</th><th>Documento</th><th>Teléfono</th><th>Estado</th><?php if (can('students.manage')): ?><th class="text-end">Acción</th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php if ($guardians === []): ?>
                        <tr><td colspan="6" class="text-muted text-center py-3">Sin acudientes registrados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($guardians as $guardian): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($guardian['nombre']) ?></td>
                            <td><?= e($guardian['parentesco'] ?? '—') ?></td>
                            <td><?= e($guardian['documento'] ?? '—') ?></td>
                            <td><?= e($guardian['telefono'] ?? '—') ?></td>
                            <td><?= badge_estado($guardian['estado']) ?></td>
                            <?php if (can('students.manage')): ?>
                                <td class="text-end">
                                    <form method="post" action="<?= e(url('/students/guardians/' . $guardian['id'] . '/toggle')) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">
                                            <?= (string) $guardian['estado'] === 'activo' ? 'Inactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (can('students.manage')): ?>
                <div class="card-body border-top">
                    <form method="post" action="<?= e(url('/students/' . $student['id'] . '/guardians')) ?>" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <div class="col-md-4">
                            <label class="form-label" for="guardian_nombre">Nombre</label>
                            <input class="form-control" id="guardian_nombre" name="nombre" maxlength="150" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="guardian_parentesco">Parentesco</label>
                            <input class="form-control" id="guardian_parentesco" name="parentesco" maxlength="60">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="guardian_documento">Documento</label>
                            <input class="form-control" id="guardian_documento" name="documento" maxlength="30">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="guardian_telefono">Teléfono</label>
                            <input class="form-control" id="guardian_telefono" name="telefono" maxlength="30">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-navy w-100" type="submit"><i class="bi bi-plus-lg me-1"></i>Agregar</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">Matrículas activas</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Código</th><th>Programa</th><th>Periodo</th><th>Fecha</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($matriculas === []): ?>
                        <tr><td colspan="5" class="text-muted text-center py-3">Sin matrículas activas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($matriculas as $matricula): ?>
                        <tr>
                            <td class="mono"><?= e($matricula['codigo']) ?></td>
                            <td><?= e($matricula['programa']) ?></td>
                            <td><?= e($matricula['periodo']) ?> <?= badge_estado($matricula['periodo_estado']) ?></td>
                            <td><?= e(fecha($matricula['fecha'])) ?></td>
                            <td class="text-end">
                                <?php if (can('enrollment.view')): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/enrollments/' . $matricula['id'])) ?>"><i class="bi bi-eye"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">Historial académico</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Periodo</th><th>Programa</th><th>Curso</th><th>Grupo</th><th>Docente</th><th>Estado</th><th class="text-end">Nota final</th></tr></thead>
            <tbody>
            <?php if ($history === []): ?>
                <tr><td colspan="7" class="text-muted text-center py-3">Sin historial académico.</td></tr>
            <?php endif; ?>
            <?php foreach ($history as $row): ?>
                <tr>
                    <td><?= e($row['periodo']) ?></td>
                    <td><?= e($row['programa']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($row['curso'] ?? '—') ?></div>
                        <div class="small text-muted mono"><?= e($row['curso_codigo'] ?? '') ?></div>
                    </td>
                    <td class="mono"><?= e($row['grupo'] ?? '—') ?></td>
                    <td><?= e($row['docente'] ?? '—') ?></td>
                    <td><?= $row['inscripcion_estado'] !== null ? badge_estado($row['inscripcion_estado']) : badge_estado($row['matricula_estado']) ?></td>
                    <td class="text-end fw-semibold"><?= $row['nota_final'] !== null ? e(number_format((float) $row['nota_final'], 1, ',', '.')) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
