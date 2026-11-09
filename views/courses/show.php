<?php

declare(strict_types=1);

/** @var array<string,mixed> $course */
/** @var list<array<string,mixed>> $programs */
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/courses')) ?>">Cursos</a></li><li class="breadcrumb-item active"><?= e($course['codigo']) ?></li></ol></nav>
        <h2><?= e($course['nombre']) ?></h2>
        <p class="text-muted mb-0"><?= e($course['codigo']) ?> · <?= e(estado_label($course['estado'])) ?></p>
    </div>
    <?php if (can('courses.manage')): ?>
        <div class="page-actions">
            <a href="<?= e(url('/courses/' . $course['id'] . '/edit')) ?>" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">Programas que incluyen este curso</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Programa</th><th>Nivel</th><th>Obligatorio</th><th>Estado</th></tr></thead>
            <tbody>
            <?php if ($programs === []): ?>
                <tr><td colspan="4" class="text-muted text-center py-3">Este curso aún no está en ningún plan de estudios.</td></tr>
            <?php endif; ?>
            <?php foreach ($programs as $row): ?>
                <tr>
                    <td class="fw-semibold"><?= e($row['programa']) ?></td>
                    <td><?= (int) $row['nivel'] ?></td>
                    <td><?= (int) $row['obligatorio'] === 1 ? 'Sí' : 'No' ?></td>
                    <td><?= badge_estado($row['estado']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-muted small">Los planes de estudio se administran desde el módulo de Programas.</div>
</div>
