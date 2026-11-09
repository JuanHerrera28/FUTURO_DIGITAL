<?php

declare(strict_types=1);

$courses = $courses ?? [];
$teachers = $teachers ?? [];
$periods = $periods ?? [];
$classrooms = $classrooms ?? [];
$sedes = $sedes ?? [];
$days = dias_semana();
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/groups')) ?>">Grupos</a></li><li class="breadcrumb-item active">Nuevo</li></ol></nav>
        <h2>Nuevo grupo</h2>
        <p class="text-muted mb-0">El grupo hereda la sede del aula y del docente; valide los cruces de horario.</p>
    </div>
</div>

<form method="post" action="<?= e(url('/groups')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="form-section-title">Datos del grupo</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="curso_id">Curso</label>
                    <select class="form-select" id="curso_id" name="curso_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int) $course['id'] ?>" <?= selected(old('curso_id'), $course['id']) ?>><?= e($course['nombre']) ?> (<?= e($course['codigo']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('curso_id')): ?><div class="text-danger small mt-1"><?= e(error('curso_id')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="periodo_id">Periodo</label>
                    <select class="form-select" id="periodo_id" name="periodo_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($periods as $period): ?>
                            <option value="<?= (int) $period['id'] ?>" <?= selected(old('periodo_id'), $period['id']) ?>><?= e($period['nombre']) ?> · <?= e(estado_label($period['estado'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('periodo_id')): ?><div class="text-danger small mt-1"><?= e(error('periodo_id')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="sede_id">Sede</label>
                    <select class="form-select" id="sede_id" name="sede_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($sedes as $sede): ?>
                            <option value="<?= (int) $sede['id'] ?>" <?= selected(old('sede_id'), $sede['id']) ?>><?= e($sede['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('sede_id')): ?><div class="text-danger small mt-1"><?= e(error('sede_id')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="aula_id">Aula</label>
                    <select class="form-select" id="aula_id" name="aula_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($classrooms as $classroom): ?>
                            <option value="<?= (int) $classroom['id'] ?>" <?= selected(old('aula_id'), $classroom['id']) ?>><?= e($classroom['nombre']) ?> · <?= e($classroom['sede']) ?> (<?= (int) $classroom['capacidad'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('aula_id')): ?><div class="text-danger small mt-1"><?= e(error('aula_id')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="docente_id">Docente</label>
                    <select class="form-select" id="docente_id" name="docente_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= (int) $teacher['id'] ?>" <?= selected(old('docente_id'), $teacher['id']) ?>><?= e($teacher['apellidos'] . ', ' . $teacher['nombres']) ?> · <?= e($teacher['sede']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (has_error('docente_id')): ?><div class="text-danger small mt-1"><?= e(error('docente_id')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="capacidad_max">Capacidad máxima</label>
                    <input type="number" class="form-control" id="capacidad_max" name="capacidad_max" min="1" max="500" value="<?= e(old('capacidad_max', 30)) ?>" required>
                    <?php if (has_error('capacidad_max')): ?><div class="text-danger small mt-1"><?= e(error('capacidad_max')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="cupo_minimo">Cupo mínimo</label>
                    <input type="number" class="form-control" id="cupo_minimo" name="cupo_minimo" min="1" max="500" value="<?= e(old('cupo_minimo', 5)) ?>">
                    <?php if (has_error('cupo_minimo')): ?><div class="text-danger small mt-1"><?= e(error('cupo_minimo')) ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-section-title mt-4">Horario</div>
            <p class="text-muted small">Registre al menos un bloque. Deje las filas sobrantes vacías.</p>
            <?php for ($i = 0; $i < 3; $i++): ?>
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-4">
                        <label class="form-label" for="day_<?= $i ?>">Día <?= $i + 1 ?></label>
                        <select class="form-select" id="day_<?= $i ?>" name="days[]">
                            <option value="">—</option>
                            <?php foreach ($days as $value => $label): ?>
                                <option value="<?= (int) $value ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="start_<?= $i ?>">Hora inicio</label>
                        <input type="time" class="form-control" id="start_<?= $i ?>" name="starts[]">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="end_<?= $i ?>">Hora fin</label>
                        <input type="time" class="form-control" id="end_<?= $i ?>" name="ends[]">
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('/groups')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar grupo</button>
        </div>
    </div>
</form>
