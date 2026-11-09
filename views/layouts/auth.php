<?php

declare(strict_types=1);

$flashes = $flashes ?? [];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Iniciar sesión') ?> · Futuro Digital</title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-shell">
    <section class="auth-brand">
        <div class="auth-brand-inner">
            <div class="brand-lockup">
                <div class="brand-mark brand-mark-lg">FD</div>
                <div>
                    <div class="brand-name-lg">Futuro Digital</div>
                    <div class="brand-sub-lg">Sistema académico-administrativo</div>
                </div>
            </div>

            <h2>Una sola plataforma para las dos sedes.</h2>
            <p class="auth-lead">
                Matrícula, inscripción a cursos, calificaciones, asistencia, pagos y trazabilidad
                en un entorno seguro para Sincelejo y Corozal.
            </p>

            <ul class="auth-features">
                <li><i class="bi bi-shield-lock"></i><span>Acceso por rol y por sede, con auditoría de cambios críticos.</span></li>
                <li><i class="bi bi-people"></i><span>Estudiantes, docentes, grupos y planes de estudio conectados.</span></li>
                <li><i class="bi bi-cash-coin"></i><span>Cargos, pagos y estados de cuenta calculados al instante.</span></li>
            </ul>

            <div class="auth-sedes">
                <span><i class="bi bi-geo-alt"></i> Sincelejo</span>
                <span><i class="bi bi-geo-alt"></i> Corozal</span>
            </div>
        </div>
    </section>

    <section class="auth-panel">
        <div class="auth-panel-inner">
            <div class="auth-mobile-brand d-lg-none">
                <div class="brand-mark">FD</div>
                <span>Futuro Digital</span>
            </div>

            <?= App\Core\View::partial('partials.flash', ['flashes' => $flashes]) ?>

            <div class="auth-card">
                <?= $content ?>
            </div>

            <p class="auth-help">
                <i class="bi bi-info-circle me-1"></i>
                No existe registro público ni recuperación automática. Si olvidó su contraseña, contacte a Secretaría.
            </p>
            <p class="auth-copy">© <?= e(date('Y')) ?> Centro Educativo Futuro Digital · Sincelejo y Corozal</p>
        </div>
    </section>
</div>
<script src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
