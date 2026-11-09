<?php

declare(strict_types=1);
$status = $status ?? 500;
?>
<div class="error-page">
    <div class="error-code"><?= e($status) ?></div>
    <h2>No fue posible completar la operación</h2>
    <p class="text-muted"><?= e($message ?? 'Ocurrió un error inesperado.') ?></p>
    <?php if (!empty($details)): ?>
        <pre class="error-details"><?= e($details) ?></pre>
    <?php endif; ?>
    <a href="<?= e(url(App\Core\Auth::check() ? '/dashboard' : '/login')) ?>" class="btn btn-primary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>
