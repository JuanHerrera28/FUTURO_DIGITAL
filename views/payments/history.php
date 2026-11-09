<?php

declare(strict_types=1);

$filters = $filters ?? [];
$sedes = $sedes ?? [];
?>
<div class="page-header">
    <div>
        <h2>Pagos registrados</h2>
        <p class="text-muted mb-0">Historial de pagos y comprobantes. Los pagos anulados conservan su trazabilidad.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/payments')) ?>" class="btn btn-outline-secondary"><i class="bi bi-cash-stack me-1"></i>Cartera</a>
        <?php if (can('payments.manage')): ?>
            <a href="<?= e(url('/payments/create')) ?>" class="btn btn-primary"><i class="bi bi-cash-coin me-1"></i>Registrar pago</a>
        <?php endif; ?>
    </div>
</div>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('/payments/history')) ?>" class="filter-form">
            <div class="form-group wide">
                <label class="form-label" for="buscar">Buscar</label>
                <input type="search" class="form-control" id="buscar" name="buscar" placeholder="Estudiante, documento o referencia" value="<?= e($filters['buscar'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="estado">Estado</label>
                <select class="form-select" id="estado" name="estado">
                    <option value="">Todos</option>
                    <?php foreach (['registrado', 'anulado'] as $state): ?>
                        <option value="<?= e($state) ?>" <?= selected($filters['estado'] ?? '', $state) ?>><?= e(estado_label($state)) ?></option>
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
            <div class="form-group">
                <label class="form-label" for="sede_id">Sede</label>
                <select class="form-select" id="sede_id" name="sede_id">
                    <option value="">Todas</option>
                    <?php foreach ($sedes as $sede): ?>
                        <option value="<?= (int) $sede['id'] ?>" <?= selected($filters['sede_id'] ?? '', $sede['id']) ?>><?= e($sede['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><button class="btn btn-navy" type="submit"><i class="bi bi-search me-1"></i>Filtrar</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Comprobante</th><th>Fecha</th><th>Estudiante</th><th>Medio</th><th>Referencia</th><th class="text-end">Valor</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="8"><div class="empty-state compact"><i class="bi bi-receipt"></i><p class="mb-0">No se encontraron pagos con los filtros aplicados.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="mono"><?= e($row['comprobante'] ?? '—') ?></td>
                    <td><?= e(fecha($row['fecha'])) ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($row['apellidos'] . ', ' . $row['nombres']) ?></div>
                        <div class="small text-muted mono"><?= e($row['estudiante_codigo']) ?> · <?= e($row['numero_documento']) ?></div>
                    </td>
                    <td><?= e(estado_label($row['medio'])) ?></td>
                    <td><?= e($row['referencia'] ?? '—') ?></td>
                    <td class="text-end fw-semibold"><?= e(money($row['valor'])) ?></td>
                    <td><?= badge_estado($row['estado']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/payments/receipts/' . $row['id'])) ?>"><i class="bi bi-eye me-1"></i>Ver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted small">Mostrando <?= $paginator->from() ?>–<?= $paginator->to() ?> de <?= $paginator->total ?> pago(s)</div>
        <?= pagination($paginator) ?>
    </div>
</div>
