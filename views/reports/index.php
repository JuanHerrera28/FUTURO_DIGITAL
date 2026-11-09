<?php

declare(strict_types=1);

/** @var array<string,array<string,mixed>> $catalog */
$filterLabels = [
    'sede' => 'Sede',
    'estado' => 'Estado',
    'buscar' => 'Búsqueda',
    'periodo' => 'Periodo',
    'programa' => 'Programa',
    'grupo' => 'Grupo',
    'desde' => 'Desde',
    'hasta' => 'Hasta',
    'medio' => 'Medio de pago',
    'tipo' => 'Tipo de alerta',
    'nivel' => 'Nivel',
];
?>
<div class="page-header">
    <div>
        <h2>Informes</h2>
        <p class="text-muted mb-0">Consultas consolidadas con exportación a CSV. Los resultados respetan el alcance por sede.</p>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($catalog as $key => $report): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="brand-mark"><i class="bi bi-<?= e($report['icon']) ?>"></i></span>
                        <div>
                            <h3 class="h6 mb-0"><?= e($report['title']) ?></h3>
                            <span class="small text-muted mono"><?= e($key) ?></span>
                        </div>
                    </div>
                    <p class="text-muted small flex-grow-1"><?= e($report['description']) ?></p>
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        <?php foreach (($report['filters'] ?? []) as $filter): ?>
                            <span class="badge text-bg-light border"><?= e($filterLabels[$filter] ?? ucfirst((string) $filter)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <a href="<?= e(url('/reports/run?reporte=' . urlencode((string) $key))) ?>" class="btn btn-outline-primary w-100">
                        <i class="bi bi-play-circle me-1"></i>Ejecutar informe
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
