<?php

declare(strict_types=1);

/** @var array<string,mixed>|null $person */
$person = $person ?? null;
$documentTypes = $documentTypes ?? [];
$canEditIdentity = $canEditIdentity ?? true;
?>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label required" for="tipo_documento_id">Tipo de documento</label>
        <select class="form-select" id="tipo_documento_id" name="tipo_documento_id" required>
            <option value="">Seleccione…</option>
            <?php foreach ($documentTypes as $type): ?>
                <option value="<?= (int) $type['id'] ?>" <?= selected(old('tipo_documento_id', $person['tipo_documento_id'] ?? ''), $type['id']) ?>>
                    <?= e($type['nombre']) ?> (<?= e($type['codigo']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (has_error('tipo_documento_id')): ?><div class="text-danger small mt-1"><?= e(error('tipo_documento_id')) ?></div><?php endif; ?>
    </div>
    <div class="col-md-4">
        <label class="form-label required" for="numero_documento">Número de documento</label>
        <input type="text" class="form-control" id="numero_documento" name="numero_documento" maxlength="30"
               value="<?= e(old('numero_documento', $person['numero_documento'] ?? '')) ?>" required>
        <?php if (has_error('numero_documento')): ?><div class="text-danger small mt-1"><?= e(error('numero_documento')) ?></div><?php endif; ?>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label>
        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento"
               value="<?= e(old('fecha_nacimiento', $person['fecha_nacimiento'] ?? '')) ?>">
        <?php if (has_error('fecha_nacimiento')): ?><div class="text-danger small mt-1"><?= e(error('fecha_nacimiento')) ?></div><?php endif; ?>
    </div>
    <div class="col-md-6">
        <label class="form-label required" for="nombres">Nombres</label>
        <input type="text" class="form-control" id="nombres" name="nombres" maxlength="100"
               value="<?= e(old('nombres', $person['nombres'] ?? '')) ?>" required>
        <?php if (has_error('nombres')): ?><div class="text-danger small mt-1"><?= e(error('nombres')) ?></div><?php endif; ?>
    </div>
    <div class="col-md-6">
        <label class="form-label required" for="apellidos">Apellidos</label>
        <input type="text" class="form-control" id="apellidos" name="apellidos" maxlength="100"
               value="<?= e(old('apellidos', $person['apellidos'] ?? '')) ?>" required>
        <?php if (has_error('apellidos')): ?><div class="text-danger small mt-1"><?= e(error('apellidos')) ?></div><?php endif; ?>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="email">Correo electrónico</label>
        <input type="email" class="form-control" id="email" name="email" maxlength="120"
               value="<?= e(old('email', $person['email'] ?? '')) ?>">
        <?php if (has_error('email')): ?><div class="text-danger small mt-1"><?= e(error('email')) ?></div><?php endif; ?>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="telefono">Teléfono</label>
        <input type="text" class="form-control" id="telefono" name="telefono" maxlength="30"
               value="<?= e(old('telefono', $person['telefono'] ?? '')) ?>">
        <?php if (has_error('telefono')): ?><div class="text-danger small mt-1"><?= e(error('telefono')) ?></div><?php endif; ?>
    </div>
    <div class="col-12">
        <label class="form-label" for="direccion">Dirección</label>
        <input type="text" class="form-control" id="direccion" name="direccion" maxlength="180"
               value="<?= e(old('direccion', $person['direccion'] ?? '')) ?>">
        <?php if (has_error('direccion')): ?><div class="text-danger small mt-1"><?= e(error('direccion')) ?></div><?php endif; ?>
    </div>
</div>

<div class="form-section-title mt-4">Autorización de tratamiento de datos</div>
<div class="row g-3 align-items-end">
    <div class="col-md-5">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="autorizacion_datos" name="autorizacion_datos" value="1"
                   <?= checked(old('autorizacion_datos', $person['autorizacion_datos'] ?? 0)) ?>>
            <label class="form-check-label" for="autorizacion_datos">El titular autoriza el tratamiento de sus datos personales</label>
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="fecha_autorizacion">Fecha de autorización</label>
        <input type="date" class="form-control" id="fecha_autorizacion" name="fecha_autorizacion"
               value="<?= e(old('fecha_autorizacion', $person['fecha_autorizacion'] ?? '')) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="medio_autorizacion">Medio</label>
        <input type="text" class="form-control" id="medio_autorizacion" name="medio_autorizacion" maxlength="80"
               placeholder="Presencial, correo…" value="<?= e(old('medio_autorizacion', $person['medio_autorizacion'] ?? '')) ?>">
    </div>
</div>
