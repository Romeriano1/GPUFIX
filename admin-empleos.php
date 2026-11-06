<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
if (!admin_es_cuenta_principal($conexion)) {
    http_response_code(403);
    exit('Este panel está reservado a la cuenta principal de GPUFIX.');
}
admin_validar_csrf();
$error = '';
$mensaje = $_SESSION['mensaje_empleos'] ?? '';
unset($_SESSION['mensaje_empleos']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $puesto = filter_input(INPUT_POST, 'puesto', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $consulta = $conexion->prepare('SELECT id_puesto FROM puesto WHERE id_puesto = ?');
        $consulta->bind_param('i', $puesto);
        $consulta->execute();
        if (!$consulta->get_result()->fetch_row()) throw new RuntimeException('Seleccioná un puesto existente.');
        if (($_POST['accion'] ?? '') === 'asignar') {
            $empleado = filter_input(INPUT_POST, 'empleado', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $consulta = $conexion->prepare("UPDATE empleado e INNER JOIN usuarios u ON u.id_correo=e.id_correo SET e.id_puesto=? WHERE e.id_empleado=? AND u.correo <> 'gpufixstore@gmail.com'");
            $consulta->bind_param('ii', $puesto, $empleado);
            $consulta->execute();
            $mensaje = 'Puesto guardado. Los permisos se aplican al abrir la próxima página.';
        } elseif (($_POST['accion'] ?? '') === 'permisos') {
            $seleccion = $_POST['secciones'] ?? [];
            if (!is_array($seleccion) || array_diff($seleccion, array_keys(admin_secciones_permisos()))) throw new RuntimeException('Seleccioná secciones válidas.');
            $archivo = __DIR__ . '/permisos-puestos.php';
            $candado = fopen($archivo . '.lock', 'c');
            if (!$candado || !flock($candado, LOCK_EX)) throw new RuntimeException('No se pudo guardar la configuración.');
            try {
                $permisos = admin_leer_permisos();
                $permisos[(string)$puesto] = array_values(array_unique($seleccion));
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
            $mensaje = 'Permisos del puesto guardados.';
        } else {
            throw new RuntimeException('Acción inválida.');
        }
        $_SESSION['mensaje_empleos'] = $mensaje;
        header('Location: admin-empleos.php', true, 303);
        exit;
    } catch (Throwable $fallo) {
        error_log('Implementación de empleos: ' . $fallo->getMessage());
        $error = $fallo instanceof mysqli_sql_exception ? 'No se pudo guardar el puesto.' : $fallo->getMessage();
    }
}
$puestos = $conexion->query('SELECT id_puesto,nombre FROM puesto ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
$empleados = $conexion->query('SELECT e.id_empleado,e.id_puesto,u.nombre,u.apellido,u.correo FROM empleado e JOIN usuarios u ON u.id_correo=e.id_correo ORDER BY u.nombre,u.apellido')->fetch_all(MYSQLI_ASSOC);
$permisos = admin_leer_permisos();
admin_layout_inicio('Implementación de Empleos');
?>
<h1 class="h3">Implementación de Empleos</h1>
<p>Cada empleado tiene un puesto. Sus permisos se comparten con los demás empleados de ese puesto.</p>
<p><a class="btn btn-outline-primary" href="admin-tablas.php?tabla=puesto">Agregar, editar o eliminar puestos</a></p>
<?php if ($mensaje): ?><p class="alert alert-success" role="status"><?= admin_e($mensaje) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-danger" role="alert"><?= admin_e($error) ?></p><?php endif; ?>
<section class="panel"><h2 class="h5">Puesto de cada empleado</h2>
<?php foreach ($empleados as $empleado): ?>
<form method="post" class="row g-2 align-items-end mb-4">
<input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="accion" value="asignar"><input type="hidden" name="empleado" value="<?= (int)$empleado['id_empleado'] ?>">
<div class="col-md-5"><strong><?= admin_e($empleado['nombre'].' '.$empleado['apellido']) ?></strong><div><?= admin_e($empleado['correo']) ?></div></div>
<?php if ($empleado['correo'] === 'gpufixstore@gmail.com'): ?><div class="col-md">Cuenta principal · Acceso completo</div><?php else: ?>
<div class="col-md"><label class="form-label" for="puesto-<?= (int)$empleado['id_empleado'] ?>">Puesto</label><select class="form-select" id="puesto-<?= (int)$empleado['id_empleado'] ?>" name="puesto" required><?php foreach ($puestos as $puesto): ?><option value="<?= (int)$puesto['id_puesto'] ?>" <?= $puesto['id_puesto']==$empleado['id_puesto'] ? 'selected' : '' ?>><?= admin_e($puesto['nombre']) ?></option><?php endforeach; ?></select></div><div class="col-md-auto"><button class="btn btn-primary">Guardar</button></div>
<?php endif; ?></form><?php endforeach; ?></section>
<section class="panel"><h2 class="h5">Secciones habilitadas por puesto</h2><p>Marcá las secciones que podrá abrir cada puesto. Un puesto sin secciones habilitadas verá un aviso al entrar.</p>
<?php foreach ($puestos as $puesto): ?><form method="post" class="border rounded p-3 mb-3">
<h3 class="h6"><?= admin_e($puesto['nombre']) ?></h3><input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="accion" value="permisos"><input type="hidden" name="puesto" value="<?= (int)$puesto['id_puesto'] ?>">
<div class="row g-2 mb-3"><?php foreach (admin_secciones_permisos() as $pagina=>$nombre): ?><label class="col-md-4"><input type="checkbox" name="secciones[]" value="<?= admin_e($pagina) ?>" <?= in_array($pagina,$permisos[(string)$puesto['id_puesto']] ?? [],true) ? 'checked' : '' ?>> <?= admin_e($nombre) ?></label><?php endforeach; ?></div>
<button class="btn btn-primary">Guardar permisos</button></form><?php endforeach; ?></section>
<?php admin_layout_fin(); ?>
