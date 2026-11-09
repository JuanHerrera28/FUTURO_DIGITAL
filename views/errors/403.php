<?php

declare(strict_types=1);
$status = $status ?? 403;
?>
<div class="error-page">
    <div class="error-code"><?= e($status) ?></div>
    <h2><?= e($message ?? 'Acceso denegado') ?></h2>
    <?php if (!empty($details)): ?>
        <pre class="error-details"><?= e($details) ?></pre>
    <?php endif; ?>
    <div class="d-flex gap-2 justify-content-center">
        <a href="<?= e(url(App\Core\Auth::check() ? '/dashboard' : '/login')) ?>" class="btn btn-primary">
            <i class="bi bi-arrow-left me-1"></i><?= App\Core\Auth::check() ? 'Volver al panel' : 'Ir al inicio de sesión' ?>
        </a>
    </div>
</div>
