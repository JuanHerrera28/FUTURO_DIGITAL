<?php

declare(strict_types=1);
?>
<div class="auth-heading">
    <span class="auth-kicker">Acceso institucional</span>
    <h1>Iniciar sesión</h1>
    <p>Ingrese con el usuario asignado por la institución.</p>
</div>

<form method="post" action="<?= e(url('/login')) ?>" class="auth-form" novalidate>
    <?= csrf_field() ?>

    <div class="mb-3">
        <label for="username" class="form-label">Usuario</label>
        <div class="input-group input-group-lg">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control <?= has_error('username') ? 'is-invalid' : '' ?>"
                   id="username" name="username" value="<?= e(old('username')) ?>"
                   autocomplete="username" maxlength="80" required autofocus>
        </div>
        <?php if (has_error('username')): ?>
            <div class="invalid-feedback d-block"><?= e(error('username')) ?></div>
        <?php endif; ?>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Contraseña</label>
        <div class="input-group input-group-lg">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control <?= has_error('password') ? 'is-invalid' : '' ?>"
                   id="password" name="password" autocomplete="current-password" required>
            <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Mostrar u ocultar contraseña">
                <i class="bi bi-eye"></i>
            </button>
        </div>
        <?php if (has_error('password')): ?>
            <div class="invalid-feedback d-block"><?= e(error('password')) ?></div>
        <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary btn-lg w-100 auth-submit">
        <i class="bi bi-box-arrow-in-right me-2"></i>Ingresar
    </button>
</form>

<div class="auth-note">
    <i class="bi bi-shield-check"></i>
    <span>La cuenta se bloquea temporalmente después de varios intentos fallidos.</span>
</div>
