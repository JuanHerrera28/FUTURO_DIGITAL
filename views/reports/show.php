<?php

declare(strict_types=1);

/** @var array<string,array<string,mixed>> $catalog */
/** @var array<string,mixed> $report */
/** @var array<string,mixed> $filters */
/** @var list<array<string,mixed>> $sedes */
/** @var list<array<string,mixed>> $periods */
/** @var list<array<string,mixed>> $programs */
/** @var list<array<string,mixed>> $groups */
$active = $catalog[$report['key']]['filters'] ?? [];
$in = static fn (string $name): bool => in_array($name, $active, true);

$query = array_filter(array_merge(['reporte' => $report['key']], $filters), static fn ($value): bool => $value !== null && $value !== '' && $value !== 0);
$exportUrl = url('/reports/export?' . http_build_query($query));
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/reports')) ?>">Informes</a></li><li class="breadcrumb-item active"><?= e($report['title']) ?></li></ol></nav>
        <h2><?= e($report['title']) ?></h2>
        <p class="text-muted mb-0"><?= e($report['description']) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/reports')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Catálogo</a>
        <a href="<?= e($exportUrl) ?>" class="btn btn-primary"><i class="bi bi-download me-1"></i>Exportar CSV</a>
    </div>
</div>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('/reports/run')) ?>" class="filter-form">
            <div class="form-group wide">
                <label class="form-label" for="reporte">Informe</label>
                <select class="form-select" id="reporte" name="reporte">
                    <?php foreach ($catalog as $key => $item): ?>
                        <option value="<?= e($key) ?>" <?= selected($report['key'], $key) ?>><?= e($item['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($in('buscar')): ?>
                <div class="form-group wide">
                    <label class="form-label" for="buscar">Buscar</label>
                    <input type="search" class="form-control" id="buscar" name="buscar" value="<?= e($filters['buscar'] ?? '') ?>" placeholder="Nombre, documento o código">
                </div>
            <?php endif; ?>
            <?php if ($in('sede')): ?>
                <div class="form-group">
                    <label class="form-label" for="sede_id">Sede</label>
                    <select class="form-select" id="sede_id" name="sede_id">
                        <option value="">Todas</option>
                        <?php foreach ($sedes as $sede): ?>
                            <option value="<?= (int) $sede['id'] ?>" <?= selected($filters['sede_id'] ?? '', $sede['id']) ?>><?= e($sede['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($in('periodo')): ?>
                <div class="form-group">
                    <label class="form-label" for="periodo_id">Periodo</label>
                    <select class="form-select" id="periodo_id" name="periodo_id">
                        <option value="">Actual / todos</option>
                        <?php foreach ($periods as $period): ?>
                            <option value="<?= (int) $period['id'] ?>" <?= selected($filters['periodo_id'] ?? '', $period['id']) ?>><?= e($period['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($in('programa')): ?>
                <div class="form-group">
                    <label class="form-label" for="programa_id">Programa</label>
                    <select class="form-select" id="programa_id" name="programa_id">
                        <option value="">Todos</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?= (int) $program['id'] ?>" <?= selected($filters['programa_id'] ?? '', $program['id']) ?>><?= e($program['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($in('grupo')): ?>
                <div class="form-group wide">
                    <label class="form-label" for="grupo_id">Grupo</label>
                    <select class="form-select" id="grupo_id" name="grupo_id">
                        <option value="">Seleccione…</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?= (int) $group['id'] ?>" <?= selected($filters['grupo_id'] ?? '', $group['id']) ?>>
                                <?= e($group['curso']) ?> · <?= e($group['codigo']) ?> · <?= e($group['sede']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($in('estado')): ?>
                <div class="form-group">
                    <label class="form-label" for="estado">Estado</label>
                    <input class="form-control" id="estado" name="estado" value="<?= e($filters['estado'] ?? '') ?>" placeholder="Todos">
                </div>
            <?php endif; ?>
            <?php if ($in('tipo')): ?>
                <div class="form-group">
                    <label class="form-label" for="tipo">Tipo</label>
                    <select class="form-select" id="tipo" name="tipo">
                        <option value="">Todos</option>
                        <?php foreach (['inasistencia', 'bajo_rendimiento', 'mora'] as $type): ?>
                            <option value="<?= e($type) ?>" <?= selected($filters['tipo'] ?? '', $type) ?>><?= e(estado_label($type)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($in('nivel')): ?>
                <div class="form-group">
                    <label class="form-label" for="nivel">Nivel</label>
                    <select class="form-select" id="nivel" name="nivel">
                        <option value="">Todos</option>
                        <?php foreach (['informativa', 'advertencia', 'critica'] as $level): ?>
                            <option value="<?= e($level) ?>" <?= selected($filters['nivel'] ?? '', $level) ?>><?= e(estado_label($level)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($in('desde')): ?>
                <div class="form-group">
                    <label class="form-label" for="desde">Desde</label>
                    <input type="date" class="form-control" id="desde" name="desde" value="<?= e($filters['desde'] ?? '') ?>">
                </div>
            <?php endif; ?>
            <?php if ($in('hasta')): ?>
                <div class="form-group">
                    <label class="form-label" for="hasta">Hasta</label>
                    <input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($filters['hasta'] ?? '') ?>">
                </div>
            <?php endif; ?>
            <?php if ($in('medio')): ?>
                <div class="form-group">
                    <label class="form-label" for="medio">Medio</label>
                    <select class="form-select" id="medio" name="medio">
                        <option value="">Todos</option>
                        <?php foreach (['efectivo', 'transferencia', 'tarjeta', 'otro'] as $medium): ?>
                            <option value="<?= e($medium) ?>" <?= selected($filters['medio'] ?? '', $medium) ?>><?= e(estado_label($medium)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="form-group"><button class="btn btn-navy" type="submit"><i class="bi bi-play-circle me-1"></i>Ejecutar</button></div>
        </form>
    </div>
</div>

<?php if (($report['summary'] ?? []) !== []): ?>
    <div class="row g-3 mb-4">
        <?php foreach ($report['summary'] as $label => $value): ?>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label"><?= e($label) ?></div>
                    <div class="stat-value"><?= e(is_float($value) ? number_format($value, 2, ',', '.') : $value) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Resultados</span>
        <span class="badge text-bg-light border"><?= count($report['rows']) ?> fila(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <?php foreach ($report['columns'] as $column => $label): ?>
                        <th><?= e($label) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($report['rows'] === []): ?>
                <tr><td colspan="<?= max(1, count($report['columns'])) ?>"><div class="empty-state compact"><i class="bi bi-graph-up-arrow"></i><p class="mb-0">El informe no devolvió resultados con los filtros aplicados.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($report['rows'] as $row): ?>
                <tr>
                    <?php foreach (array_keys($report['columns']) as $column): ?>
                        <td><?= e($row[$column] ?? '—') ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
