<?php

declare(strict_types=1);

/** @var array<string,mixed> $program */
/** @var list<array<string,mixed>> $plan */
/** @var list<array<string,mixed>> $prerequisites */
/** @var list<array<string,mixed>> $courses */

$planCourseIds = array_map(static fn (array $row): int => (int) $row['curso_id'], $plan);
$programPrerequisites = array_values(array_filter(
    $prerequisites,
    static fn (array $row): bool => in_array((int) $row['curso_id'], $planCourseIds, true) && (string) $row['estado'] === 'activo'
));
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/programs')) ?>">Programas</a></li><li class="breadcrumb-item active"><?= e($program['codigo']) ?></li></ol></nav>
        <h2><?= e($program['nombre']) ?></h2>
        <p class="text-muted mb-0"><?= e($program['codigo']) ?> · <?= (int) $program['duracion_periodos'] ?> periodo(s) · <?= e(estado_label($program['estado'])) ?></p>
    </div>
    <?php if (can('programs.manage')): ?>
        <div class="page-actions">
            <a href="<?= e(url('/programs/' . $program['id'] . '/edit')) ?>" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Editar programa</a>
        </div>
    <?php endif; ?>
</div>

<div class="card mb-4" id="plan">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Plan de estudios</span>
        <span class="badge text-bg-light border"><?= count($plan) ?> curso(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Nivel</th><th>Curso</th><th>Código</th><th>Obligatorio</th><th>Estado curso</th><?php if (can('programs.manage')): ?><th class="text-end">Acción</th><?php endif; ?></tr></thead>
            <tbody>
            <?php if ($plan === []): ?>
                <tr><td colspan="6" class="text-muted text-center py-3">El plan de estudios está vacío.</td></tr>
            <?php endif; ?>
            <?php foreach ($plan as $row): ?>
                <tr>
                    <td><?= (int) $row['nivel'] ?></td>
                    <td class="fw-semibold"><?= e($row['curso']) ?></td>
                    <td class="mono"><?= e($row['curso_codigo']) ?></td>
                    <td><?= (int) $row['obligatorio'] === 1 ? 'Sí' : 'No' ?></td>
                    <td><?= badge_estado($row['curso_estado']) ?></td>
                    <?php if (can('programs.manage')): ?>
                        <td class="text-end">
                            <form method="post" action="<?= e(url('/programs/' . $program['id'] . '/courses/' . $row['id'] . '/remove')) ?>" onsubmit="return confirm('¿Retirar este curso del plan?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (can('programs.manage')): ?>
        <div class="card-body border-top">
            <form method="post" action="<?= e(url('/programs/' . $program['id'] . '/courses')) ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-5">
                    <label class="form-label" for="curso_id">Curso</label>
                    <select class="form-select" id="curso_id" name="curso_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int) $course['id'] ?>"><?= e($course['nombre']) ?> (<?= e($course['codigo']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="nivel">Nivel</label>
                    <input type="number" class="form-control" id="nivel" name="nivel" min="1" max="20" value="1" required>
                </div>
                <div class="col-md-2">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" id="obligatorio" name="obligatorio" value="1" checked>
                        <label class="form-check-label" for="obligatorio">Obligatorio</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-navy w-100" type="submit"><i class="bi bi-plus-lg me-1"></i>Agregar al plan</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="card" id="prerrequisitos">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Prerrequisitos</span>
        <span class="badge text-bg-light border"><?= count($programPrerequisites) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Curso</th><th>Requiere</th><th>Tipo</th><th>Estado</th><?php if (can('programs.manage')): ?><th class="text-end">Acción</th><?php endif; ?></tr></thead>
            <tbody>
            <?php if ($programPrerequisites === []): ?>
                <tr><td colspan="5" class="text-muted text-center py-3">Sin prerrequisitos registrados.</td></tr>
            <?php endif; ?>
            <?php foreach ($programPrerequisites as $row): ?>
                <tr>
                    <td><?= e($row['curso']) ?> <span class="text-muted mono">(<?= e($row['curso_codigo']) ?>)</span></td>
                    <td><?= e($row['requisito']) ?> <span class="text-muted mono">(<?= e($row['requisito_codigo']) ?>)</span></td>
                    <td><?= e(estado_label($row['tipo'])) ?></td>
                    <td><?= badge_estado($row['estado']) ?></td>
                    <?php if (can('programs.manage')): ?>
                        <td class="text-end">
                            <form method="post" action="<?= e(url('/programs/' . $program['id'] . '/prerequisites/' . $row['id'] . '/remove')) ?>" onsubmit="return confirm('¿Inactivar este prerrequisito?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (can('programs.manage')): ?>
        <div class="card-body border-top">
            <form method="post" action="<?= e(url('/programs/' . $program['id'] . '/prerequisites')) ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-4">
                    <label class="form-label" for="prereq_curso">Curso</label>
                    <select class="form-select" id="prereq_curso" name="curso_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int) $course['id'] ?>"><?= e($course['nombre']) ?> (<?= e($course['codigo']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="prereq_requisito">Curso requisito</label>
                    <select class="form-select" id="prereq_requisito" name="requisito_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int) $course['id'] ?>"><?= e($course['nombre']) ?> (<?= e($course['codigo']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="prereq_tipo">Tipo</label>
                    <select class="form-select" id="prereq_tipo" name="tipo">
                        <option value="aprobado">Aprobado</option>
                        <option value="cursado">Cursado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-navy w-100" type="submit"><i class="bi bi-plus-lg me-1"></i>Registrar</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>
