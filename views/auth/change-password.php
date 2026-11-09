<?php

declare(strict_types=1);

$obligatorio = $obligatorio ?? false;
?>
<?php if ($obligatorio): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start">
        <i class="bi bi-shield-exclamation fs-5"></i>
        <div>
            <strong>Cambio obligatorio.</strong>
            Está usando una contraseña temporal. Defina una contraseña personal antes de continuar.
        </div>
    </div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h2>Cambiar contraseña</h2>
        <p class="text-muted mb-0">Use al menos 8 caracteres, con mayúscula, minúscula, número y símbolo.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body p-4">
                <form method="post" action="<?= e(url('/change-password')) ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="actual" class="form-label">Contraseña actual</label>
                        <input type="password" class="form-control <?= has_error('actual') ? 'is-invalid' : '' ?>"
                               id="actual" name="actual" autocomplete="current-password" required>
                        <?php if (has_error('actual')): ?><div class="invalid-feedback"><?= e(error('actual')) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="nueva" class="form-label">Nueva contraseña</label>
                        <input type="password" class="form-control <?= has_error('nueva') ? 'is-invalid' : '' ?>"
                               id="nueva" name="nueva" autocomplete="new-password" required>
                        <?php if (has_error('nueva')): ?><div class="invalid-feedback"><?= e(error('nueva')) ?></div><?php endif; ?>
                        <div class="form-text">Mínimo 8 caracteres, una mayúscula, una minúscula, un número y un símbolo.</div>
                    </div>

                    <div class="mb-4">
                        <label for="nueva_confirmed" class="form-label">Confirmar nueva contraseña</label>
                        <input type="password" class="form-control <?= has_error('nueva_confirmed') ? 'is-invalid' : '' ?>"
                               id="nueva_confirmed" name="nueva_confirmed" autocomplete="new-password" required>
                        <?php if (has_error('nueva_confirmed')): ?><div class="invalid-feedback"><?= e(error('nueva_confirmed')) ?></div><?php endif; ?>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Guardar contraseña</button>
                        <?php if (!$obligatorio): ?>
                            <a href="<?= e(url('/dashboard')) ?>" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
