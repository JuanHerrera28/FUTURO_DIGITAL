<?php

declare(strict_types=1);

/** @var array<string,mixed> $period */
/** @var list<array<string,mixed>> $lowGroups */
$estado = (string) $period['estado'];
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/periods')) ?>">Periodos</a></li><li class="breadcrumb-item active"><?= e($period['codigo']) ?></li></ol></nav>
        <h2><?= e($period['nombre']) ?></h2>
        <p class="text-muted mb-0"><?= e($period['codigo']) ?> · <?= badge_estado($estado) ?></p>
    </div>
    <?php if (can('periods.manage')): ?>
        <div class="page-actions">
            <a href="<?= e(url('/periods/' . $period['id'] . '/edit')) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
        </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Calendario</div>
            <div class="card-body">
                <div class="detail-grid">
                    <div><span>Inicio</span><strong><?= e(fecha($period['fecha_inicio'])) ?></strong></div>
                    <div><span>Fin</span><strong><?= e(fecha($period['fecha_fin'])) ?></strong></div>
                    <div><span>Apertura matrícula</span><strong><?= e(fecha($period['matricula_inicio'])) ?></strong></div>
                    <div><span>Cierre matrícula</span><strong><?= e(fecha($period['matricula_fin'])) ?></strong></div>
                    <div><span>Estado</span><strong><?= badge_estado($estado) ?></strong></div>
                    <div><span>Creado</span><strong><?= e(fecha_hora($period['created_at'] ?? null)) ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Acciones del periodo</div>
            <div class="card-body d-flex flex-column gap-3">
                <?php if ($estado === 'planificado'): ?>
                    <div class="alert alert-info mb-0"><i class="bi bi-info-circle me-2"></i>Al abrir la matrícula se valida que no exista otro periodo con matrícula abierta.</div>
                    <form method="post" action="<?= e(url('/periods/' . $period['id'] . '/open-enrollment')) ?>">
                        <?= csrf_field() ?>
                        <?php if ($lowGroups !== []): ?>
                            <div class="alert alert-warning">
                                <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Grupos por debajo del cupo mínimo</div>
                                <ul class="mb-2 small">
                                    <?php foreach ($lowGroups as $group): ?>
                                        <li><?= e($group['curso']) ?> · <?= e($group['codigo']) ?> (<?= (int) $group['inscritos'] ?> inscritos)</li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirmar" name="confirmar" value="1" required>
                                    <label class="form-check-label" for="confirmar">Confirmo la apertura pese a los grupos bajo el mínimo. Quedará auditado.</label>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label" for="observacion">Observación (opcional)</label>
                            <textarea class="form-control" id="observacion" name="observacion" rows="2" maxlength="255"></textarea>
                        </div>
                        <button class="btn btn-success" type="submit"><i class="bi bi-unlock me-1"></i>Abrir matrícula</button>
                    </form>
                <?php elseif ($estado === 'matricula_abierta'): ?>
                    <div class="alert alert-success mb-0"><i class="bi bi-unlock me-2"></i>La matrícula está abierta hasta el <?= e(fecha($period['matricula_fin'])) ?>.</div>
                    <form method="post" action="<?= e(url('/periods/' . $period['id'] . '/start')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-navy" type="submit"><i class="bi bi-play-fill me-1"></i>Pasar a curso</button>
                    </form>
                <?php elseif ($estado === 'en_curso'): ?>
                    <div class="alert alert-primary mb-0"><i class="bi bi-play-circle me-2"></i>El periodo está en curso. El cierre consolida notas, asistencia y matrículas.</div>
                    <form method="post" action="<?= e(url('/periods/' . $period['id'] . '/close')) ?>" onsubmit="return confirm('¿Cerrar el periodo? Esta acción consolida las inscripciones.');">
                        <?= csrf_field() ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="forzar" name="forzar" value="1">
                            <label class="form-check-label" for="forzar">Forzar cierre aunque algún grupo no tenga pesos del 100 %</label>
                        </div>
                        <button class="btn btn-danger" type="submit"><i class="bi bi-lock me-1"></i>Cerrar periodo</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-secondary mb-0"><i class="bi bi-lock me-2"></i>El periodo está cerrado. No admite modificaciones académicas ni financieras.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">Grupos por debajo del cupo mínimo</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Grupo</th><th>Curso</th><th>Inscritos</th></tr></thead>
            <tbody>
            <?php if ($lowGroups === []): ?>
                <tr><td colspan="3" class="text-muted text-center py-3">Todos los grupos alcanzan el cupo mínimo configurado.</td></tr>
            <?php endif; ?>
            <?php foreach ($lowGroups as $group): ?>
                <tr><td class="mono"><?= e($group['codigo']) ?></td><td><?= e($group['curso']) ?></td><td><?= (int) $group['inscritos'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
