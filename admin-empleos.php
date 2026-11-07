<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
if (!admin_es_cuenta_principal($conexion)) { http_response_code(403); exit('Acceso reservado a GPUFIX.'); }
admin_validar_csrf();
$puestos = $conexion->query('SELECT id_puesto,nombre FROM puesto ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
$puestoId = filter_input(INPUT_GET, 'puesto', FILTER_VALIDATE_INT) ?: 0;
$error = '';
$mensaje = $_SESSION['mensaje_empleos'] ?? '';
unset($_SESSION['mensaje_empleos']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $puestoId = filter_input(INPUT_POST, 'puesto', FILTER_VALIDATE_INT) ?: 0;
    try {
        if (!in_array($puestoId, array_map('intval', array_column($puestos,'id_puesto')), true)) throw new RuntimeException('Seleccioná un puesto válido.');
        if (($_POST['accion'] ?? '') === 'asignar') {
            $empleadoId = filter_input(INPUT_POST, 'empleado', FILTER_VALIDATE_INT) ?: 0;
            $buscar = $conexion->prepare("SELECT id_empleado FROM empleado WHERE id_empleado=? AND email <> 'gpufixstore@gmail.com'");
            $buscar->bind_param('i',$empleadoId); $buscar->execute();
            if (!$buscar->get_result()->fetch_row()) throw new RuntimeException('Seleccioná un empleado válido.');
            $guardar = $conexion->prepare('UPDATE empleado SET id_puesto=? WHERE id_empleado=?');
            $guardar->bind_param('ii',$puestoId,$empleadoId); $guardar->execute();
            $mensaje = 'Puesto asignado. Sus paneles se aplican en la próxima página.';
        } elseif (($_POST['accion'] ?? '') === 'permisos') {
            $seleccion = $_POST['paneles'] ?? [];
            if (!is_array($seleccion)) throw new RuntimeException('Seleccioná paneles válidos.');
            foreach ($seleccion as $panel) if (!is_string($panel) || !isset(admin_secciones_permisos()[$panel])) throw new RuntimeException('Panel no disponible.');
            $archivo = __DIR__ . '/permisos-puestos.php';
            $candado = fopen($archivo.'.lock','c');
            if (!$candado || !flock($candado, LOCK_EX)) throw new RuntimeException('No se pudo guardar.');
            try {
                $permisos = admin_leer_permisos();
                $permisos[(string)$puestoId] = array_values(array_unique($seleccion));
                unset($permisos['empleados']);
                $contenido = "<?php exit; ?>\n" . json_encode($permisos, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                $temporal = tempnam(__DIR__,'.permisos-');
                if (!$temporal) throw new RuntimeException('No se pudo preparar el archivo.');
                try {
                    if (file_put_contents($temporal,$contenido) !== strlen($contenido) || !rename($temporal,$archivo)) throw new RuntimeException('No se pudo guardar.');
                } finally { if (is_file($temporal)) unlink($temporal); }
            } finally { flock($candado,LOCK_UN); fclose($candado); }
            $mensaje = 'Paneles del puesto guardados. Se aplican a todos sus empleados.';
        } else { throw new RuntimeException('Acción inválida.'); }
        $_SESSION['mensaje_empleos'] = $mensaje;
        header('Location: admin-empleos.php?puesto='.$puestoId,true,303); exit;
    } catch (Throwable $fallo) { error_log($fallo->getMessage()); $error = $fallo instanceof mysqli_sql_exception ? 'No se pudo guardar el cambio.' : $fallo->getMessage(); }
}
$empleados = $conexion->query("SELECT e.id_empleado,e.nombre,e.apellido,e.email,e.id_puesto FROM empleado e WHERE COALESCE(email,'') <> 'gpufixstore@gmail.com' ORDER BY nombre,apellido")->fetch_all(MYSQLI_ASSOC);
$permisos = admin_leer_permisos();
admin_layout_inicio('Implementación de Empleos');
?>
<h1 class="h3">Implementación de Empleos</h1>
<p>Elegí los paneles de cada puesto y asigná ese puesto a los empleados.</p>
<div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-primary" href="admin-tablas.php?tabla=puesto&amp;nuevo=1">Crear puesto</a><a class="btn btn-outline-primary" href="admin-tablas.php?tabla=puesto">Editar o eliminar puestos</a><a class="btn btn-outline-primary" href="admin-tablas.php?tabla=empleado&amp;nuevo=1">Crear empleado</a><a class="btn btn-outline-primary" href="admin-tablas.php?tabla=usuarios&amp;nuevo=1">Crear usuario</a></div>
<?php if ($mensaje): ?><p class="alert alert-success" role="status"><?= admin_e($mensaje) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-danger" role="alert"><?= admin_e($error) ?></p><?php endif; ?>
<section class="panel"><h2 class="h5">Paneles por puesto</h2>
<form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-3"><label class="flex-grow-1">Puesto<select class="form-select" name="puesto" required><option value="">Seleccionar puesto</option><?php foreach($puestos as $puesto): ?><option value="<?= (int)$puesto['id_puesto'] ?>" <?= $puestoId===$puesto['id_puesto'] || $puestoId===(int)$puesto['id_puesto'] ? 'selected' : '' ?>><?= admin_e($puesto['nombre']) ?></option><?php endforeach; ?></select></label><button class="btn btn-primary">Configurar</button></form>
<?php if(in_array($puestoId,array_map('intval',array_column($puestos,'id_puesto')),true)): ?>
<form method="post"><input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="accion" value="permisos"><input type="hidden" name="puesto" value="<?= $puestoId ?>">
<div class="row g-3 mb-3"><?php foreach(admin_secciones_permisos() as $pagina=>$nombre): ?><label class="col-md-6 col-lg-4"><input type="checkbox" name="paneles[]" value="<?= admin_e($pagina) ?>" <?= in_array($pagina,$permisos[(string)$puestoId]??[],true)?'checked':'' ?>> <?= admin_e($nombre) ?></label><?php endforeach; ?></div>
<button class="btn btn-primary">Guardar paneles del puesto</button></form><?php endif; ?></section>
<section class="panel"><h2 class="h5">Asignar puesto a empleados</h2>
<?php foreach($empleados as $empleado): ?><form method="post" class="row g-2 align-items-end mb-3"><input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="accion" value="asignar"><input type="hidden" name="empleado" value="<?= (int)$empleado['id_empleado'] ?>"><div class="col-md-5"><strong><?= admin_e($empleado['nombre'].' '.$empleado['apellido']) ?></strong><div><?= admin_e($empleado['email']) ?></div></div><label class="col-md">Puesto<select class="form-select" name="puesto" required><?php foreach($puestos as $puesto): ?><option value="<?= (int)$puesto['id_puesto'] ?>" <?= $puesto['id_puesto']==$empleado['id_puesto']?'selected':'' ?>><?= admin_e($puesto['nombre']) ?></option><?php endforeach; ?></select></label><div class="col-md-auto"><button class="btn btn-primary">Guardar puesto</button></div></form><?php endforeach; ?>
<?php if(!$empleados): ?><p>Todavía no hay otros empleados.</p><?php endif; ?></section>
<?php admin_layout_fin(); ?>
