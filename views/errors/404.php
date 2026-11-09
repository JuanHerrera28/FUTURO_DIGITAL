<?php

declare(strict_types=1);
$status = $status ?? 404;
?>
<div class="error-page">
    <div class="error-code"><?= e($status) ?></div>
    <h2><?= e($message ?? 'La página solicitada no existe') ?></h2>
    <p class="text-muted">Verifique la dirección o regrese al panel principal.</p>
    <a href="<?= e(url(App\Core\Auth::check() ? '/dashboard' : '/login')) ?>" class="btn btn-primary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>
