<?php

declare(strict_types=1);

/** @var array<string,mixed> $teacher */
/** @var list<array<string,mixed>> $load */
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/teachers')) ?>">Docentes</a></li><li class="breadcrumb-item active"><?= e($teacher['codigo'] ?? '') ?></li></ol></nav>
        <h2><?= e($teacher['apellidos'] . ', ' . $teacher['nombres']) ?></h2>
        <p class="text-muted mb-0"><?= e($teacher['numero_documento']) ?> · <?= e($teacher['sede']) ?> · <?= e(estado_label($teacher['tipo_vinculacion'])) ?></p>
    </div>
    <div class="page-actions">
        <?php if (can('teachers.manage')): ?>
            <a href="<?= e(url('/teachers/' . $teacher['id'] . '/edit')) ?>" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center"><span>Datos del docente</span><?= badge_estado($teacher['estado']) ?></div>
            <div class="card-body">
                <div class="detail-grid">
                    <div><span>Código</span><strong class="mono"><?= e($teacher['codigo'] ?? '—') ?></strong></div>
                    <div><span>Documento</span><strong><?= e($teacher['numero_documento']) ?></strong></div>
                    <div><span>Sede</span><strong><?= e($teacher['sede']) ?></strong></div>
                    <div><span>Vinculación</span><strong><?= e(estado_label($teacher['tipo_vinculacion'])) ?></strong></div>
                    <div class="wide"><span>Título</span><strong><?= e($teacher['titulo'] ?? '—') ?></strong></div>
                    <div><span>Correo</span><strong><?= e($teacher['email'] ?? '—') ?></strong></div>
                    <div><span>Teléfono</span><strong><?= e($teacher['telefono'] ?? '—') ?></strong></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">Carga académica</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Periodo</th><th>Curso</th><th>Grupo</th><th>Sede</th><th>Aula</th><th>Inscritos</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php if ($load === []): ?>
                        <tr><td colspan="7" class="text-muted text-center py-3">Sin grupos asignados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($load as $row): ?>
                        <tr>
                            <td><?= e($row['periodo']) ?> <?= badge_estado($row['periodo_estado']) ?></td>
                            <td><div class="fw-semibold"><?= e($row['curso']) ?></div><div class="small text-muted mono"><?= e($row['curso_codigo']) ?></div></td>
                            <td class="mono"><?= e($row['codigo']) ?></td>
                            <td><?= e($row['sede']) ?></td>
                            <td><?= e($row['aula']) ?></td>
                            <td><?= (int) $row['inscritos'] ?></td>
                            <td><?= badge_estado($row['estado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
