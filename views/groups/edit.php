<?php

declare(strict_types=1);

$courses = $courses ?? [];
$teachers = $teachers ?? [];
$periods = $periods ?? [];
$classrooms = $classrooms ?? [];
$sedes = $sedes ?? [];
$schedules = $schedules ?? [];
$days = dias_semana();

$blocks = [];
foreach ($schedules as $schedule) {
    $blocks[] = [
        'dia_semana' => (int) $schedule['dia_semana'],
        'hora_inicio' => substr((string) $schedule['hora_inicio'], 0, 5),
        'hora_fin' => substr((string) $schedule['hora_fin'], 0, 5),
    ];
}
while (count($blocks) < 3) {
    $blocks[] = ['dia_semana' => 0, 'hora_inicio' => '', 'hora_fin' => ''];
}
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/groups')) ?>">Grupos</a></li><li class="breadcrumb-item"><a href="<?= e(url('/groups/' . $group['id'])) ?>"><?= e($group['codigo']) ?></a></li><li class="breadcrumb-item active">Editar</li></ol></nav>
        <h2>Editar grupo</h2>
        <p class="text-muted mb-0"><?= e($group['codigo']) ?> · <?= e($group['curso']) ?></p>
    </div>
</div>

<form method="post" action="<?= e(url('/groups/' . $group['id'])) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="form-section-title">Datos del grupo</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="curso_id">Curso</label>
                    <select class="form-select" id="curso_id" name="curso_id" required>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int) $course['id'] ?>" <?= selected(old('curso_id', $group['curso_id']), $course['id']) ?>><?= e($course['nombre']) ?> (<?= e($course['codigo']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="periodo_id">Periodo</label>
                    <select class="form-select" id="periodo_id" name="periodo_id" required>
                        <?php foreach ($periods as $period): ?>
                            <option value="<?= (int) $period['id'] ?>" <?= selected(old('periodo_id', $group['periodo_id']), $period['id']) ?>><?= e($period['nombre']) ?> · <?= e(estado_label($period['estado'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="sede_id">Sede</label>
                    <select class="form-select" id="sede_id" name="sede_id" required>
                        <?php foreach ($sedes as $sede): ?>
                            <option value="<?= (int) $sede['id'] ?>" <?= selected(old('sede_id', $group['sede_id']), $sede['id']) ?>><?= e($sede['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="aula_id">Aula</label>
                    <select class="form-select" id="aula_id" name="aula_id" required>
                        <?php foreach ($classrooms as $classroom): ?>
                            <option value="<?= (int) $classroom['id'] ?>" <?= selected(old('aula_id', $group['aula_id']), $classroom['id']) ?>><?= e($classroom['nombre']) ?> · <?= e($classroom['sede']) ?> (<?= (int) $classroom['capacidad'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="docente_id">Docente</label>
                    <select class="form-select" id="docente_id" name="docente_id" required>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= (int) $teacher['id'] ?>" <?= selected(old('docente_id', $group['docente_id']), $teacher['id']) ?>><?= e($teacher['apellidos'] . ', ' . $teacher['nombres']) ?> · <?= e($teacher['sede']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label required" for="capacidad_max">Capacidad</label>
                    <input type="number" class="form-control" id="capacidad_max" name="capacidad_max" min="1" max="500" value="<?= e(old('capacidad_max', $group['capacidad_max'])) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="cupo_minimo">Cupo mínimo</label>
                    <input type="number" class="form-control" id="cupo_minimo" name="cupo_minimo" min="1" max="500" value="<?= e(old('cupo_minimo', $group['cupo_minimo'])) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="estado">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <?php foreach (['activo', 'inactivo', 'cancelado'] as $estado): ?>
                            <option value="<?= e($estado) ?>" <?= selected(old('estado', $group['estado']), $estado) ?>><?= e(estado_label($estado)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-section-title mt-4">Horario</div>
            <?php foreach ($blocks as $i => $block): ?>
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-4">
                        <label class="form-label" for="day_<?= $i ?>">Día <?= $i + 1 ?></label>
                        <select class="form-select" id="day_<?= $i ?>" name="days[]">
                            <option value="">—</option>
                            <?php foreach ($days as $value => $label): ?>
                                <option value="<?= (int) $value ?>" <?= selected($block['dia_semana'], $value) ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="start_<?= $i ?>">Hora inicio</label>
                        <input type="time" class="form-control" id="start_<?= $i ?>" name="starts[]" value="<?= e($block['hora_inicio']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="end_<?= $i ?>">Hora fin</label>
                        <input type="time" class="form-control" id="end_<?= $i ?>" name="ends[]" value="<?= e($block['hora_fin']) ?>">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('/groups/' . $group['id'])) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar cambios</button>
        </div>
    </div>
</form>
