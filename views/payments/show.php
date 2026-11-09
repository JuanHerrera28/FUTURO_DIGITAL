<?php

declare(strict_types=1);

/** @var array<string,mixed> $payment */
/** @var list<array<string,mixed>> $applications */
$annulled = (string) $payment['estado'] === 'anulado';
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/payments/history')) ?>">Pagos</a></li><li class="breadcrumb-item active"><?= e($payment['comprobante'] ?? 'Comprobante') ?></li></ol></nav>
        <h2>Comprobante <?= e($payment['comprobante'] ?? '—') ?></h2>
        <p class="text-muted mb-0">
            <?= e($payment['apellidos'] . ', ' . $payment['nombres']) ?> · <?= e(fecha($payment['fecha'])) ?> · <?= badge_estado($payment['estado']) ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/payments/history')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
        <?php if (can('payments.view')): ?>
            <a href="<?= e(url('/payments/statement/' . $payment['estudiante_id'])) ?>" class="btn btn-outline-primary"><i class="bi bi-file-earmark-text me-1"></i>Estado de cuenta</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($annulled): ?>
    <div class="alert alert-danger">
        <i class="bi bi-x-octagon me-2"></i>Este pago está anulado. Motivo: <?= e($payment['motivo_anulacion'] ?? '—') ?> · <?= e(fecha_hora($payment['anulado_at'])) ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Datos del pago</div>
            <div class="card-body">
                <div class="detail-grid">
                    <div><span>Comprobante</span><strong class="mono"><?= e($payment['comprobante'] ?? '—') ?></strong></div>
                    <div><span>Estado comprobante</span><strong><?= badge_estado($payment['comprobante_estado'] ?? $payment['estado']) ?></strong></div>
                    <div><span>Estudiante</span><strong><?= e($payment['apellidos'] . ', ' . $payment['nombres']) ?></strong></div>
                    <div><span>Código</span><strong class="mono"><?= e($payment['estudiante_codigo']) ?></strong></div>
                    <div><span>Documento</span><strong><?= e($payment['numero_documento']) ?></strong></div>
                    <div><span>Fecha</span><strong><?= e(fecha($payment['fecha'])) ?></strong></div>
                    <div><span>Valor</span><strong class="fs-5"><?= e(money($payment['valor'])) ?></strong></div>
                    <div><span>Medio</span><strong><?= e(estado_label($payment['medio'])) ?></strong></div>
                    <div><span>Referencia</span><strong><?= e($payment['referencia'] ?? '—') ?></strong></div>
                    <div><span>Registrado</span><strong><?= e(fecha_hora($payment['created_at'])) ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Aplicaciones del pago</span>
                <span class="badge text-bg-light border"><?= count($applications) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Concepto</th><th>Descripción</th><th>Vencimiento</th><th class="text-end">Valor</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php if ($applications === []): ?>
                        <tr><td colspan="5"><div class="empty-state compact"><i class="bi bi-link-45deg"></i><p class="mb-0">Este pago no tiene aplicaciones registradas.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($applications as $application): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($application['concepto']) ?></td>
                            <td><?= e(str_limit($application['descripcion'], 50)) ?></td>
                            <td><?= e(fecha($application['fecha_vencimiento'])) ?></td>
                            <td class="text-end"><?= e(money($application['valor'])) ?></td>
                            <td><?= badge_estado($application['estado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (can('payments.manage') && !$annulled): ?>
    <div class="card mt-4 border-danger-subtle">
        <div class="card-header text-danger">Anular pago</div>
        <div class="card-body">
            <form method="post" action="<?= e(url('/payments/receipts/' . $payment['id'] . '/annul')) ?>" class="row g-2 align-items-end" data-confirm="¿Anular este pago? Los cargos volverán a quedar pendientes.">
                <?= csrf_field() ?>
                <div class="col-md-9">
                    <label class="form-label required" for="motivo">Motivo de anulación</label>
                    <input class="form-control" id="motivo" name="motivo" maxlength="255" required>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-danger w-100" type="submit"><i class="bi bi-x-octagon me-1"></i>Anular pago</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
