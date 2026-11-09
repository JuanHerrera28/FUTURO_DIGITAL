<?php

declare(strict_types=1);

$flashes = $flashes ?? [];
$credentials = $flashes['credentials'] ?? [];
unset($flashes['credentials']);

$types = [
    'success' => ['class' => 'success', 'icon' => 'check-circle-fill'],
    'error' => ['class' => 'danger', 'icon' => 'exclamation-octagon-fill'],
    'warning' => ['class' => 'warning', 'icon' => 'exclamation-triangle-fill'],
    'info' => ['class' => 'info', 'icon' => 'info-circle-fill'],
];
?>
<?php foreach ($types as $type => $meta): ?>
    <?php foreach (($flashes[$type] ?? []) as $message): ?>
        <div class="alert alert-<?= e($meta['class']) ?> alert-dismissible fade show app-alert" role="alert">
            <i class="bi bi-<?= e($meta['icon']) ?> me-2"></i>
            <span><?= nl2br(e(is_array($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : $message)) ?></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<?php foreach ($credentials as $credential): ?>
    <?php if (!is_array($credential)) { continue; } ?>
    <div class="card credential-card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-start gap-3">
                <div class="credential-icon"><i class="bi bi-key-fill"></i></div>
                <div class="flex-grow-1">
                    <h5 class="mb-1">Credenciales temporales generadas</h5>
                    <p class="text-muted mb-3">
                        <?= e($credential['mensaje'] ?? 'Entregue estos datos a la persona. La contraseña no se volverá a mostrar.') ?>
                    </p>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <div class="credential-item">
                                <span>Usuario</span>
                                <strong><?= e($credential['username'] ?? '') ?></strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="credential-item">
                                <span>Contraseña temporal</span>
                                <strong class="credential-password"><?= e($credential['password'] ?? '') ?></strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="credential-item">
                                <span>Rol / sede</span>
                                <strong><?= e($credential['rol'] ?? '') ?><?= !empty($credential['sede']) ? ' · ' . e($credential['sede']) : '' ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="small text-danger mt-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Debe cambiar la contraseña en el primer inicio de sesión.
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary d-none d-md-inline"
                        data-copy="<?= e(($credential['username'] ?? '') . ' / ' . ($credential['password'] ?? '')) ?>">
                    <i class="bi bi-clipboard me-1"></i>Copiar
                </button>
            </div>
        </div>
    </div>
<?php endforeach; ?>
