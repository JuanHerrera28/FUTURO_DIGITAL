<?php

declare(strict_types=1);

/** @var array<string,mixed> $docente */
/** @var list<array<string,mixed>> $periods */
/** @var int $periodoId */
/** @var list<array<string,mixed>> $groups */
$user = auth_user();
?>
<div class="page-header">
    <div>
        <h2>Mi carga académica</h2>
        <p class="text-muted mb-0">
            <?= e($user['nombre'] ?? '') ?>
            <?php if (!empty($docente['codigo'])): ?> · <?= e($docente['codigo']) ?><?php endif; ?>
            <?php if (!empty($docente['titulo'])): ?> · <?= e($docente['titulo']) ?><?php endif; ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/teaching')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i>Actualizar</a>
    </div>
</div>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('/teaching')) ?>" class="filter-form">
            <div class="form-group wide">
                <label class="form-label" for="periodo_id">Periodo</label>
                <select class="form-select" id="periodo_id" name="periodo_id">
                    <option value="">Todos los periodos</option>
                    <?php foreach ($periods as $period): ?>
                        <option value="<?= (int) $period['id'] ?>" <?= selected($periodoId, $period['id']) ?>><?= e($period['nombre']) ?> · <?= e(estado_label($period['estado'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><button class="btn btn-navy" type="submit"><i class="bi bi-search me-1"></i>Filtrar</button></div>
        </form>
    </div>
</div>

<div class="row g-4">
    <?php if ($groups === []): ?>
        <div class="col-12">
            <div class="card"><div class="card-body"><div class="empty-state compact"><i class="bi bi-collection"></i><p class="mb-0">No tiene grupos asignados en el periodo seleccionado.</p></div></div></div>
        </div>
    <?php endif; ?>
    <?php foreach ($groups as $group): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h3 class="h6 mb-1"><?= e($group['curso']) ?></h3>
                            <div class="small text-muted mono"><?= e($group['codigo']) ?> · <?= e($group['curso_codigo']) ?></div>
                        </div>
                        <?= badge_estado($group['estado']) ?>
                    </div>
                    <div class="detail-grid small mb-3">
                        <div><span>Periodo</span><strong><?= e($group['periodo']) ?></strong></div>
                        <div><span>Sede</span><strong><?= e($group['sede']) ?></strong></div>
                        <div><span>Aula</span><strong><?= e($group['aula']) ?></strong></div>
                        <div><span>Inscritos</span><strong><?= (int) $group['inscritos'] ?> / <?= (int) $group['capacidad_max'] ?></strong></div>
                    </div>
                    <div class="d-flex gap-2 mt-auto">
                        <?php if (can('grades.view')): ?>
                            <a href="<?= e(url('/grades/' . $group['id'])) ?>" class="btn btn-sm btn-outline-primary flex-fill"><i class="bi bi-clipboard-check me-1"></i>Notas</a>
                        <?php endif; ?>
                        <?php if (can('attendance.view')): ?>
                            <a href="<?= e(url('/attendance/' . $group['id'])) ?>" class="btn btn-sm btn-outline-primary flex-fill"><i class="bi bi-calendar-check me-1"></i>Asistencia</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
