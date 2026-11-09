<?php

declare(strict_types=1);

$filters = $filters ?? [];
$sedes = $sedes ?? [];
?>
<div class="page-header">
    <div>
        <h2>Cartera</h2>
        <p class="text-muted mb-0">Cargos pendientes y pagados. Los saldos se calculan a partir de las aplicaciones de pago.</p>
    </div>
    <?php if (can('payments.manage')): ?>
        <div class="page-actions">
            <a href="<?= e(url('/payments/charges/create')) ?>" class="btn btn-outline-primary"><i class="bi bi-receipt me-1"></i>Nuevo cargo</a>
            <a href="<?= e(url('/payments/create')) ?>" class="btn btn-primary"><i class="bi bi-cash-coin me-1"></i>Registrar pago</a>
        </div>
    <?php endif; ?>
</div>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('/payments')) ?>" class="filter-form">
            <div class="form-group wide">
                <label class="form-label" for="buscar">Buscar</label>
                <input type="search" class="form-control" id="buscar" name="buscar" placeholder="Estudiante, documento o descripción" value="<?= e($filters['buscar'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="estado">Estado</label>
                <select class="form-select" id="estado" name="estado">
                    <option value="">Todos</option>
                    <?php foreach (['pendiente' => 'Pendiente', 'pagado' => 'Pagado', 'vencido' => 'Vencido'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= selected($filters['estado'] ?? '', $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
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
            <thead><tr><th>Estudiante</th><th>Concepto</th><th>Emisión</th><th>Vencimiento</th><th class="text-end">Valor</th><th class="text-end">Pagado</th><th class="text-end">Saldo</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="9"><div class="empty-state compact"><i class="bi bi-cash-stack"></i><p class="mb-0">No se encontraron cargos con los filtros aplicados.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php $saldo = max(0.0, (float) $row['valor_total'] - (float) $row['pagado']); ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($row['apellidos'] . ', ' . $row['nombres']) ?></div>
                        <div class="small text-muted mono"><?= e($row['estudiante_codigo']) ?> · <?= e($row['numero_documento']) ?></div>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= e($row['concepto']) ?></div>
                        <div class="small text-muted"><?= e(str_limit($row['descripcion'], 50)) ?></div>
                    </td>
                    <td><?= e(fecha($row['fecha_emision'])) ?></td>
                    <td><?= e(fecha($row['fecha_vencimiento'])) ?></td>
                    <td class="text-end"><?= e(money($row['valor_total'])) ?></td>
                    <td class="text-end"><?= e(money($row['pagado'])) ?></td>
                    <td class="text-end fw-semibold <?= $saldo > 0.01 ? 'text-danger' : 'text-success' ?>"><?= e(money($saldo)) ?></td>
                    <td><?= badge_estado($row['estado']) ?></td>
                    <td class="text-end">
                        <div class="table-actions">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/payments/statement/' . $row['estudiante_id'])) ?>" title="Estado de cuenta"><i class="bi bi-file-earmark-text"></i></a>
                            <?php if (can('payments.manage') && (string) $row['estado'] !== 'pagado'): ?>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#annul-charge-<?= (int) $row['id'] ?>" title="Anular cargo"><i class="bi bi-x-octagon"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php if (can('payments.manage') && (string) $row['estado'] !== 'pagado'): ?>
                    <tr class="collapse" id="annul-charge-<?= (int) $row['id'] ?>">
                        <td colspan="9" class="bg-light">
                            <form method="post" action="<?= e(url('/payments/charges/' . $row['id'] . '/annul')) ?>" class="row g-2 align-items-end" data-confirm="¿Anular este cargo?">
                                <?= csrf_field() ?>
                                <div class="col-md-9">
                                    <label class="form-label" for="motivo_cargo_<?= (int) $row['id'] ?>">Motivo de anulación</label>
                                    <input class="form-control" id="motivo_cargo_<?= (int) $row['id'] ?>" name="motivo" maxlength="255" required>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-danger w-100" type="submit"><i class="bi bi-x-octagon me-1"></i>Anular cargo</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted small">Mostrando <?= $paginator->from() ?>–<?= $paginator->to() ?> de <?= $paginator->total ?> cargo(s)</div>
        <?= pagination($paginator) ?>
    </div>
</div>
