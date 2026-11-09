<?php

declare(strict_types=1);
$status = $status ?? 405;
?>
<div class="error-page">
    <div class="error-code"><?= e($status) ?></div>
    <h2>Método no permitido</h2>
    <p class="text-muted"><?= e($message ?? 'La acción solicitada no está disponible por esta vía.') ?></p>
    <a href="<?= e(url(App\Core\Auth::check() ? '/dashboard' : '/login')) ?>" class="btn btn-primary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>
