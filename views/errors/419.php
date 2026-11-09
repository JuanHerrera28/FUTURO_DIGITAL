<?php

declare(strict_types=1);
$status = $status ?? 419;
?>
<div class="error-page">
    <div class="error-code"><?= e($status) ?></div>
    <h2>Formulario expirado</h2>
    <p class="text-muted"><?= e($message ?? 'El token de seguridad no es válido.') ?></p>
    <a href="<?= e(url(App\Core\Auth::check() ? '/dashboard' : '/login')) ?>" class="btn btn-primary">
        <i class="bi bi-arrow-clockwise me-1"></i>Recargar
    </a>
</div>
