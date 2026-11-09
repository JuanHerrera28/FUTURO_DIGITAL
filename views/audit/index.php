<?php

declare(strict_types=1);

$filters = $filters ?? [];
$modules = $modules ?? [];
$actions = $actions ?? [];
?>
<div class="page-header">
    <div>
        <h2>Auditoría</h2>
        <p class="text-muted mb-0">Registro de cambios críticos con usuario, entidad, valores anteriores y nuevos. Solo lectura.</p>
    </div>
</div>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('/audit')) ?>" class="filter-form">
            <div class="form-group wide">
                <label class="form-label" for="buscar">Buscar</label>
                <input type="search" class="form-control" id="buscar" name="buscar" placeholder="Acción, entidad, usuario" value="<?= e($filters['buscar'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="modulo">Módulo</label>
                <select class="form-select" id="modulo" name="modulo">
                    <option value="">Todos</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= e($module) ?>" <?= selected($filters['modulo'] ?? '', $module) ?>><?= e(estado_label($module)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="accion">Acción</label>
                <select class="form-select" id="accion" name="accion">
                    <option value="">Todas</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?= e($action) ?>" <?= selected($filters['accion'] ?? '', $action) ?>><?= e(estado_label($action)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="desde">Desde</label>
                <input type="date" class="form-control" id="desde" name="desde" value="<?= e($filters['desde'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="hasta">Hasta</label>
                <input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($filters['hasta'] ?? '') ?>">
            </div>
            <div class="form-group"><button class="btn btn-navy" type="submit"><i class="bi bi-search me-1"></i>Filtrar</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Módulo</th><th>Entidad</th><th>IP</th><th class="text-end">Detalle</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="7"><div class="empty-state compact"><i class="bi bi-shield-check"></i><p class="mb-0">No se encontraron registros de auditoría con los filtros aplicados.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="text-nowrap"><?= e(fecha_hora($row['created_at'])) ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($row['usuario'] !== '' ? $row['usuario'] : ($row['username'] ?? 'Sistema')) ?></div>
                        <div class="small text-muted"><?= e($row['username'] ?? '—') ?> · <?= e($row['rol'] ?? '—') ?></div>
                    </td>
                    <td><?= e(estado_label($row['accion'])) ?></td>
                    <td><?= e(estado_label($row['modulo'])) ?></td>
                    <td>
                        <div class="mono small"><?= e($row['entidad']) ?><?= !empty($row['entidad_id']) ? ' #' . e($row['entidad_id']) : '' ?></div>
                    </td>
                    <td class="mono small"><?= e($row['ip'] ?? '—') ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#audit-<?= (int) $row['id'] ?>"><i class="bi bi-code-square"></i></button>
                    </td>
                </tr>
                <tr class="collapse" id="audit-<?= (int) $row['id'] ?>">
                    <td colspan="7" class="bg-light">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-tiny text-muted mb-1">Valor anterior</div>
                                <pre class="small mb-0 bg-white border rounded p-2"><?= e(json_pretty($row['valor_anterior'])) ?></pre>
                            </div>
                            <div class="col-md-6">
                                <div class="text-tiny text-muted mb-1">Valor nuevo</div>
                                <pre class="small mb-0 bg-white border rounded p-2"><?= e(json_pretty($row['valor_nuevo'])) ?></pre>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted small">Mostrando <?= $paginator->from() ?>–<?= $paginator->to() ?> de <?= $paginator->total ?> registro(s)</div>
        <?= pagination($paginator) ?>
    </div>
</div>
