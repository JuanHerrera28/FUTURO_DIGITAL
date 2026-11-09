<?php

declare(strict_types=1);

/** @var array<string,list<array<string,mixed>>> $groups */
$labels = [
    'general' => 'General',
    'academico' => 'Académico',
    'asistencia' => 'Asistencia',
    'matricula' => 'Matrícula',
    'financiero' => 'Financiero',
    'seguridad' => 'Seguridad',
];
?>
<div class="page-header">
    <div>
        <h2>Parámetros del sistema</h2>
        <p class="text-muted mb-0">Los umbrales y reglas configurables viven aquí. Cada cambio queda auditado con su valor anterior y nuevo.</p>
    </div>
</div>

<form method="post" action="<?= e(url('/params')) ?>">
    <?= csrf_field() ?>
    <div class="row g-4">
        <?php foreach ($groups as $group => $params): ?>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><?= e($labels[$group] ?? ucfirst((string) $group)) ?></div>
                    <div class="card-body">
                        <?php foreach ($params as $param): ?>
                            <?php $isBool = (string) $param['tipo'] === 'bool'; ?>
                            <div class="mb-3">
                                <label class="form-label" for="param_<?= e($param['clave']) ?>"><?= e($param['descripcion'] ?? $param['clave']) ?></label>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($isBool): ?>
                                        <input type="hidden" name="parametros[<?= e($param['clave']) ?>]" value="0">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="param_<?= e($param['clave']) ?>" name="parametros[<?= e($param['clave']) ?>]" value="1" <?= checked($param['valor']) ?>>
                                        </div>
                                    <?php elseif (in_array((string) $param['tipo'], ['int', 'decimal'], true)): ?>
                                        <input type="number" class="form-control" id="param_<?= e($param['clave']) ?>" name="parametros[<?= e($param['clave']) ?>]"
                                               value="<?= e($param['valor']) ?>" step="<?= (string) $param['tipo'] === 'int' ? '1' : '0.01' ?>">
                                    <?php else: ?>
                                        <input type="text" class="form-control" id="param_<?= e($param['clave']) ?>" name="parametros[<?= e($param['clave']) ?>]" value="<?= e($param['valor']) ?>">
                                    <?php endif; ?>
                                    <span class="badge text-bg-light border mono"><?= e($param['clave']) ?></span>
                                </div>
                                <div class="form-text">
                                    Tipo: <?= e($param['tipo']) ?>
                                    <?php if (!empty($param['updated_at'])): ?> · Actualizado <?= e(fecha_hora($param['updated_at'])) ?><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="d-flex justify-content-end mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar parámetros</button>
    </div>
</form>
