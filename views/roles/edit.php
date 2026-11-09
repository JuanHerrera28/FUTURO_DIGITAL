<?php

declare(strict_types=1);

/** @var array<string,mixed> $role */
/** @var array<string,list<array<string,mixed>>> $permissions */
/** @var list<int> $assigned */
$isAdmin = (int) $role['id'] === 1;
?>
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/roles')) ?>">Roles</a></li><li class="breadcrumb-item active"><?= e($role['nombre']) ?></li></ol></nav>
        <h2><?= e($role['nombre']) ?></h2>
        <p class="text-muted mb-0"><?= e($role['descripcion'] ?? 'Sin descripción') ?> · <?= badge_estado($role['estado']) ?></p>
    </div>
</div>

<?php if ($isAdmin): ?>
    <div class="alert alert-info"><i class="bi bi-shield-lock me-2"></i>El rol Administrador no puede desactivarse ni cambiar de nombre. Sus permisos sí pueden ajustarse.</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Datos del rol</div>
            <div class="card-body">
                <form method="post" action="<?= e(url('/roles/' . $role['id'])) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label required" for="nombre">Nombre</label>
                        <input class="form-control" id="nombre" name="nombre" maxlength="60" value="<?= e(old('nombre', $role['nombre'])) ?>" <?= $isAdmin ? 'readonly' : '' ?> required>
                        <?php if (has_error('nombre')): ?><div class="text-danger small mt-1"><?= e(error('nombre')) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="descripcion">Descripción</label>
                        <input class="form-control" id="descripcion" name="descripcion" maxlength="255" value="<?= e(old('descripcion', $role['descripcion'] ?? '')) ?>">
                        <?php if (has_error('descripcion')): ?><div class="text-danger small mt-1"><?= e(error('descripcion')) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="estado">Estado</label>
                        <?php if ($isAdmin): ?>
                            <input type="hidden" name="estado" value="activo">
                            <input class="form-control" value="<?= e(estado_label($role['estado'])) ?>" readonly>
                        <?php else: ?>
                            <select class="form-select" id="estado" name="estado" required>
                                <?php foreach (['activo', 'inactivo'] as $state): ?>
                                    <option value="<?= e($state) ?>" <?= selected(old('estado', $role['estado']), $state) ?>><?= e(estado_label($state)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <?php if (has_error('estado')): ?><div class="text-danger small mt-1"><?= e(error('estado')) ?></div><?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>Guardar datos</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <form method="post" action="<?= e(url('/roles/' . $role['id'] . '/permissions')) ?>">
            <?= csrf_field() ?>
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Matriz de permisos</span>
                    <span class="badge text-bg-light border"><?= count($assigned) ?> asignado(s)</span>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <?php foreach ($permissions as $module => $items): ?>
                            <div class="col-md-6">
                                <div class="permission-group">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h3 class="h6 mb-0"><?= e($module) ?></h3>
                                        <button class="btn btn-sm btn-link p-0" type="button" data-toggle-module="<?= e(md5((string) $module)) ?>">Todos</button>
                                    </div>
                                    <?php foreach ($items as $permission): ?>
                                        <?php $checked = in_array((int) $permission['id'], $assigned, true); ?>
                                        <div class="form-check">
                                            <input class="form-check-input module-<?= e(md5((string) $module)) ?>" type="checkbox" name="permisos[]" value="<?= (int) $permission['id'] ?>" id="perm_<?= (int) $permission['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="perm_<?= (int) $permission['id'] ?>">
                                                <span class="mono small"><?= e($permission['clave']) ?></span>
                                                <?php if (!empty($permission['descripcion'])): ?>
                                                    <span class="d-block text-muted small"><?= e($permission['descripcion']) ?></span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-shield-check me-1"></i>Guardar permisos</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('[data-toggle-module]').forEach(function (button) {
        button.addEventListener('click', function () {
            var className = 'module-' + button.getAttribute('data-toggle-module');
            var boxes = document.querySelectorAll('.' + className);
            var allChecked = Array.prototype.every.call(boxes, function (box) { return box.checked; });
            boxes.forEach(function (box) { box.checked = !allChecked; });
        });
    });
})();
</script>
