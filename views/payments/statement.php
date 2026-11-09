<?php

declare(strict_types=1);

/** @var array<string,mixed> $student */
/** @var array{charges:list<array<string,mixed>>,total:float,pagado:float,saldo:float,vencido:float} $statement */
$charges = $statement['charges'];
$visualBadge = static function (string $state): string {
    $map = [
        'pagado' => ['success', 'Pagado'],
        'vencido' => ['danger', 'Vencido'],
        'pendiente' => ['warning', 'Pendiente'],
    ];
    [$class, $label] = $map[$state] ?? ['secondary', estado_label($state)];

    return '<span class="badge text-bg-' . $class . '">' . e($label) . '</span>';
};
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/payments')) ?>">Cartera</a></li><li class="breadcrumb-item active">Estado de cuenta</li></ol></nav>
        <h2><?= e($student['apellidos'] . ', ' . $student['nombres']) ?></h2>
        <p class="text-muted mb-0">
            <?= e($student['codigo']) ?> · <?= e($student['tipo_documento']) ?> <?= e($student['numero_documento']) ?> · <?= e($student['sede']) ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/payments')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
        <?php if (can('payments.manage')): ?>
            <a href="<?= e(url('/payments/charges/create')) ?>" class="btn btn-outline-primary"><i class="bi bi-receipt me-1"></i>Nuevo cargo</a>
            <a href="<?= e(url('/payments/create?estudiante_id=' . $student['id'])) ?>" class="btn btn-primary"><i class="bi bi-cash-coin me-1"></i>Registrar pago</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Total cargos</div><div class="stat-value"><?= e(money($statement['total'])) ?></div><div class="stat-hint"><?= count($charges) ?> cargo(s)</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Pagado</div><div class="stat-value text-success"><?= e(money($statement['pagado'])) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Saldo</div><div class="stat-value <?= (float) $statement['saldo'] > 0.01 ? 'text-danger' : 'text-success' ?>"><?= e(money($statement['saldo'])) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Vencido</div><div class="stat-value <?= (float) $statement['vencido'] > 0.01 ? 'text-danger' : '' ?>"><?= e(money($statement['vencido'])) ?></div><div class="stat-hint">Con mora configurada</div></div></div>
</div>

<div class="card">
    <div class="card-header">Cargos y pagos aplicados</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Concepto</th><th>Descripción</th><th>Emisión</th><th>Vencimiento</th><th class="text-end">Valor</th><th class="text-end">Pagado</th><th class="text-end">Saldo</th><th>Estado</th><?php if (can('payments.manage')): ?><th class="text-end">Acciones</th><?php endif; ?></tr></thead>
            <tbody>
            <?php if ($charges === []): ?>
                <tr><td colspan="<?= can('payments.manage') ? 9 : 8 ?>"><div class="empty-state compact"><i class="bi bi-cash-stack"></i><p class="mb-0">El estudiante no tiene cargos registrados.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($charges as $charge): ?>
                <tr>
                    <td class="fw-semibold"><?= e($charge['concepto']) ?></td>
                    <td><?= e(str_limit($charge['descripcion'], 60)) ?></td>
                    <td><?= e(fecha($charge['fecha_emision'])) ?></td>
                    <td><?= e(fecha($charge['fecha_vencimiento'])) ?></td>
                    <td class="text-end"><?= e(money($charge['valor_total'])) ?></td>
                    <td class="text-end"><?= e(money($charge['pagado'])) ?></td>
                    <td class="text-end fw-semibold <?= (float) $charge['saldo'] > 0.01 ? 'text-danger' : 'text-success' ?>"><?= e(money($charge['saldo'])) ?></td>
                    <td><?= $visualBadge((string) $charge['estado_visual']) ?></td>
                    <?php if (can('payments.manage')): ?>
                        <td class="text-end">
                            <?php if ((string) $charge['estado'] !== 'pagado'): ?>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#annul-<?= (int) $charge['id'] ?>" title="Anular cargo"><i class="bi bi-x-octagon"></i></button>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php if (can('payments.manage') && (string) $charge['estado'] !== 'pagado'): ?>
                    <tr class="collapse" id="annul-<?= (int) $charge['id'] ?>">
                        <td colspan="9" class="bg-light">
                            <form method="post" action="<?= e(url('/payments/charges/' . $charge['id'] . '/annul')) ?>" class="row g-2 align-items-end" data-confirm="¿Anular este cargo?">
                                <?= csrf_field() ?>
                                <div class="col-md-9">
                                    <label class="form-label" for="motivo_<?= (int) $charge['id'] ?>">Motivo de anulación</label>
                                    <input class="form-control" id="motivo_<?= (int) $charge['id'] ?>" name="motivo" maxlength="255" required>
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
</div>
