<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $students */
/** @var int $studentId */
/** @var array<string,mixed>|null $statement */
$pending = [];
if ($statement !== null) {
    foreach ($statement['charges'] as $charge) {
        if ((float) $charge['saldo'] > 0.01) {
            $pending[] = $charge;
        }
    }
}
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/payments')) ?>">Cartera</a></li><li class="breadcrumb-item active">Registrar pago</li></ol></nav>
        <h2>Registrar pago</h2>
        <p class="text-muted mb-0">Seleccione el estudiante y distribuya el pago entre sus cargos pendientes.</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('/payments/create')) ?>" class="row g-2 align-items-end">
            <div class="col-md-8">
                <label class="form-label" for="estudiante_id">Estudiante</label>
                <select class="form-select" id="estudiante_id" name="estudiante_id" required>
                    <option value="">Seleccione…</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= (int) $student['id'] ?>" <?= selected($studentId, $student['id']) ?>>
                            <?= e($student['apellidos'] . ', ' . $student['nombres']) ?> · <?= e($student['codigo']) ?> · <?= e($student['numero_documento']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-navy w-100" type="submit"><i class="bi bi-search me-1"></i>Consultar cartera</button>
            </div>
        </form>
    </div>
</div>

<?php if ($studentId > 0 && $statement === null): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>No se encontró el estudiante seleccionado.</div>
<?php endif; ?>

<?php if ($statement !== null): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="stat-card"><div class="stat-label">Total cargos</div><div class="stat-value"><?= e(money($statement['total'])) ?></div></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-label">Pagado</div><div class="stat-value text-success"><?= e(money($statement['pagado'])) ?></div></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-label">Saldo</div><div class="stat-value <?= (float) $statement['saldo'] > 0.01 ? 'text-danger' : 'text-success' ?>"><?= e(money($statement['saldo'])) ?></div></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-label">Vencido</div><div class="stat-value <?= (float) $statement['vencido'] > 0.01 ? 'text-danger' : '' ?>"><?= e(money($statement['vencido'])) ?></div></div></div>
    </div>

    <?php if ($pending === []): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>El estudiante no tiene cargos pendientes. No es posible registrar un pago.</div>
    <?php else: ?>
        <form method="post" action="<?= e(url('/payments')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="estudiante_id" value="<?= (int) $studentId ?>">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Datos del pago</span>
                    <span class="badge text-bg-light border">Saldo pendiente <?= e(money($statement['saldo'])) ?></span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label required" for="fecha">Fecha</label>
                            <input type="date" class="form-control" id="fecha" name="fecha" value="<?= e(old('fecha', date('Y-m-d'))) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required" for="valor">Valor del pago</label>
                            <input type="number" class="form-control" id="valor" name="valor" min="0.01" step="0.01" value="<?= e(old('valor')) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required" for="medio">Medio de pago</label>
                            <select class="form-select" id="medio" name="medio" required>
                                <?php foreach (['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'otro' => 'Otro'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= selected(old('medio', 'efectivo'), $value) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="referencia">Referencia</label>
                            <input class="form-control" id="referencia" name="referencia" maxlength="100" value="<?= e(old('referencia')) ?>" placeholder="Opcional">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>Aplicación a cargos (opcional)</span>
                    <button class="btn btn-sm btn-outline-primary" type="button" id="auto-distribute"><i class="bi bi-magic me-1"></i>Distribuir automáticamente</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="allocation-table">
                        <thead><tr><th>Concepto</th><th>Descripción</th><th>Vencimiento</th><th class="text-end">Saldo</th><th style="width:180px">Monto a aplicar</th></tr></thead>
                        <tbody>
                        <?php foreach ($pending as $charge): ?>
                            <tr data-saldo="<?= e(number_format((float) $charge['saldo'], 2, '.', '')) ?>">
                                <td class="fw-semibold"><?= e($charge['concepto']) ?></td>
                                <td><?= e(str_limit($charge['descripcion'], 60)) ?></td>
                                <td><?= e(fecha($charge['fecha_vencimiento'])) ?></td>
                                <td class="text-end"><?= e(money($charge['saldo'])) ?></td>
                                <td>
                                    <input type="hidden" name="cargo_id[]" value="<?= (int) $charge['id'] ?>">
                                    <input type="number" class="form-control form-control-sm allocation" name="monto[]" min="0" step="0.01" placeholder="0" aria-label="Monto para <?= e($charge['concepto']) ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="text-muted small">
                        Deje los montos en cero para abonar automáticamente a los cargos más antiguos. La suma aplicada debe coincidir con el valor del pago.
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Registrar pago</button>
                </div>
            </div>
        </form>

        <script>
        (function () {
            var button = document.getElementById('auto-distribute');
            if (!button) { return; }
            button.addEventListener('click', function () {
                var total = parseFloat(document.getElementById('valor').value || '0');
                var rows = document.querySelectorAll('#allocation-table tbody tr');
                rows.forEach(function (row) {
                    var input = row.querySelector('.allocation');
                    var saldo = parseFloat(row.getAttribute('data-saldo') || '0');
                    var amount = Math.min(saldo, Math.max(0, total));
                    input.value = amount > 0 ? amount.toFixed(2) : '';
                    total = Math.max(0, total - amount);
                });
            });
        })();
        </script>
    <?php endif; ?>
<?php endif; ?>
