<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
if (!admin_es_cuenta_principal($conexion)) {
    http_response_code(403);
    exit('Este panel está reservado a la cuenta principal de GPUFIX.');
}
admin_validar_csrf();
$tablasDisponibles = array_column($conexion->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all(MYSQLI_NUM), 0);
sort($tablasDisponibles);
$empleados = $conexion->query("SELECT e.id_empleado,e.id_puesto,u.nombre,u.apellido,u.correo,p.nombre AS puesto FROM empleado e JOIN usuarios u ON u.id_correo=e.id_correo JOIN puesto p ON p.id_puesto=e.id_puesto WHERE u.correo <> 'gpufixstore@gmail.com' ORDER BY u.nombre,u.apellido")->fetch_all(MYSQLI_ASSOC);
$empleadoId = filter_input(INPUT_GET, 'empleado', FILTER_VALIDATE_INT) ?: 0;
$error = '';
$mensaje = $_SESSION['mensaje_empleos'] ?? '';
unset($_SESSION['mensaje_empleos']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empleadoId = filter_input(INPUT_POST, 'empleado', FILTER_VALIDATE_INT) ?: 0;
    try {
        if (!in_array($empleadoId, array_map('intval', array_column($empleados, 'id_empleado')), true)) throw new RuntimeException('Seleccioná un empleado.');
        $seleccion = $_POST['tablas'] ?? [];
        if (!is_array($seleccion)) throw new RuntimeException('Seleccioná tablas válidas.');
        foreach ($seleccion as $tabla) if (!is_string($tabla) || !in_array($tabla, $tablasDisponibles, true)) throw new RuntimeException('Seleccioná tablas válidas.');
        $archivo = __DIR__ . '/permisos-puestos.php';
        $candado = fopen($archivo . '.lock', 'c');
        if (!$candado || !flock($candado, LOCK_EX)) throw new RuntimeException('No se pudo guardar la configuración.');
        try {
            $permisos = admin_leer_permisos();
            $permisos['empleados'][(string)$empleadoId] = array_values(array_unique($seleccion));
            $contenido = "<?php exit; ?>\n" . json_encode($permisos, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $temporal = tempnam(__DIR__, '.permisos-');
            if (!$temporal) throw new RuntimeException('No se pudo preparar la configuración.');
            try {
                if (file_put_contents($temporal, $contenido) !== strlen($contenido) || !rename($temporal, $archivo)) throw new RuntimeException('No se pudieron guardar los permisos.');
            } finally {
                if (is_file($temporal)) unlink($temporal);
            }
        } finally {
            flock($candado, LOCK_UN);
            fclose($candado);
        }
        $_SESSION['mensaje_empleos'] = 'Tablas asignadas al empleado. Los permisos se aplican al abrir la próxima página.';
        header('Location: admin-empleos.php?empleado=' . $empleadoId, true, 303);
        exit;
    } catch (Throwable $fallo) {
        error_log('Implementación de empleos: ' . $fallo->getMessage());
        $error = 'No se pudieron guardar los permisos. Revisá la selección y los permisos de escritura del proyecto.';
    }
}
$seleccionado = null;
foreach ($empleados as $empleado) if ((int)$empleado['id_empleado'] === $empleadoId) $seleccionado = $empleado;
$asignadas = [];
if ($seleccionado) {
    foreach ($tablasDisponibles as $tabla) {
        $configuracion = admin_leer_permisos();
        if (isset($configuracion['empleados'][(string)$empleadoId])) {
            if (in_array($tabla, $configuracion['empleados'][(string)$empleadoId], true)) $asignadas[] = $tabla;
        } elseif (in_array(admin_permiso_tabla($tabla), $configuracion[(string)$seleccionado['id_puesto']] ?? [], true)) {
            $asignadas[] = $tabla;
        }
    }
}
admin_layout_inicio('Implementación de Empleos');
?>
<h1 class="h3">Implementación de Empleos</h1>
<section class="panel">
<form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-4">
<label class="flex-grow-1" for="empleado">Empleado<select class="form-select" id="empleado" name="empleado" required>
<option value="">Seleccionar empleado</option>
<?php foreach ($empleados as $empleado): ?><option value="<?= (int)$empleado['id_empleado'] ?>" <?= (int)$empleado['id_empleado'] === $empleadoId ? 'selected' : '' ?>><?= admin_e($empleado['nombre'].' '.$empleado['apellido'].' · '.$empleado['correo']) ?></option><?php endforeach; ?>
</select></label><button class="btn btn-primary">Seleccionar</button></form>
<?php if ($mensaje): ?><p class="alert alert-success" role="status"><?= admin_e($mensaje) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-danger" role="alert"><?= admin_e($error) ?></p><?php endif; ?>
<?php if ($seleccionado): ?>
<p>Puesto actual · <strong><?= admin_e($seleccionado['puesto']) ?></strong></p>
<form method="post"><input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="empleado" value="<?= $empleadoId ?>">
<fieldset><legend class="h5">Tablas que puede ver este empleado</legend>
<div class="row g-3 mb-4"><?php foreach ($tablasDisponibles as $tabla): ?>
<label class="col-md-6 col-lg-4"><input type="checkbox" name="tablas[]" value="<?= admin_e($tabla) ?>" <?= in_array($tabla, $asignadas, true) ? 'checked' : '' ?>> <?= admin_e(admin_etiqueta_dato($tabla)) ?><small class="d-block text-secondary ms-3"><?= admin_e($tabla) ?></small></label>
<?php endforeach; ?></div></fieldset>
<p>Marcá para asignar acceso. Desmarcá para retirarlo. Las funciones que usan varias tablas se habilitan cuando tiene acceso a todas las tablas de esa sección.</p>
<button class="btn btn-primary">Guardar permisos del empleado</button></form>
<?php elseif (!$empleados): ?><p>Todavía no hay otros empleados registrados.</p><?php endif; ?>
<p class="mt-4 mb-0"><a href="admin-tablas.php?tabla=puesto">Administrar puestos</a> · <a href="admin-tablas.php?tabla=empleado">Administrar empleados</a></p>
</section>
<?php admin_layout_fin(); ?>
