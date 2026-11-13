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
        $accion = $_POST['accion'] ?? '';
        if ($accion === 'crear_rol') $puestoId = 0;
        if (!in_array($accion, ['crear_rol', 'cargar_roles'], true) && !in_array($puestoId, array_map('intval', array_column($puestos,'id_puesto')), true)) throw new RuntimeException('Seleccioná un puesto válido.');
        if ($accion === 'cargar_roles') {
            $creados = admin_cargar_roles_organigrama($conexion);
            $mensaje = 'Puestos del organigrama disponibles. Roles nuevos: ' . $creados . '. Los permisos de los roles ya configurados se conservan.';
        } elseif ($accion === 'asignar') {
            $empleadoId = filter_input(INPUT_POST, 'empleado', FILTER_VALIDATE_INT) ?: 0;
            $buscar = $conexion->prepare("SELECT id_empleado FROM empleado WHERE id_empleado=? AND email <> 'gpufixstore@gmail.com'");
            $buscar->bind_param('i',$empleadoId); $buscar->execute();
            if (!$buscar->get_result()->fetch_row()) throw new RuntimeException('Seleccioná un empleado válido.');
            $guardar = $conexion->prepare('UPDATE empleado SET id_puesto=? WHERE id_empleado=?');
            $guardar->bind_param('ii',$puestoId,$empleadoId); $guardar->execute();
            $mensaje = 'Rol asignado. Sus paneles se aplican en la próxima página.';
        } elseif (in_array($accion, ['crear_rol', 'permisos'], true)) {
            $seleccion = $_POST['paneles'] ?? [];
            if (!is_array($seleccion)) throw new RuntimeException('Seleccioná paneles válidos.');
            foreach ($seleccion as $panel) if (!is_string($panel) || !isset(admin_secciones_permisos()[$panel])) throw new RuntimeException('Panel no disponible.');
            $nombreRol = is_string($_POST['nombre'] ?? null) ? trim($_POST['nombre']) : '';
            if ($nombreRol === '' || mb_strlen($nombreRol, 'UTF-8') > 100) throw new RuntimeException('El nombre del rol debe tener entre 1 y 100 caracteres.');
            $conexion->begin_transaction();
            try {
                $actual = null;
                if ($accion !== 'crear_rol') {
                    $buscar = $conexion->prepare('SELECT nombre FROM puesto WHERE id_puesto=? FOR UPDATE');
                    $buscar->bind_param('i', $puestoId); $buscar->execute();
                    $actual = $buscar->get_result()->fetch_assoc();
                    if (!$actual) throw new RuntimeException('El rol ya no existe.');
                    if ($actual['nombre'] === 'Administrador' && $nombreRol !== $actual['nombre']) throw new RuntimeException('El nombre Administrador está protegido.');
                }
                $buscar = $conexion->prepare('SELECT id_puesto FROM puesto WHERE LOWER(TRIM(nombre))=LOWER(?) AND id_puesto<>? LIMIT 1');
                $buscar->bind_param('si', $nombreRol, $puestoId); $buscar->execute();
                if ($buscar->get_result()->fetch_row()) throw new RuntimeException('Ya existe un rol con ese nombre.');
                if ($accion === 'crear_rol') {
                    $guardar = $conexion->prepare('INSERT INTO puesto(nombre) VALUES(?)');
                    $guardar->bind_param('s', $nombreRol); $guardar->execute();
                    $puestoId = (int) $conexion->insert_id;
                } else {
                    $guardar = $conexion->prepare('UPDATE puesto SET nombre=? WHERE id_puesto=?');
                    $guardar->bind_param('si', $nombreRol, $puestoId); $guardar->execute();
                }
                admin_guardar_paneles_puesto($puestoId, $seleccion);
                $conexion->commit();
            } catch (Throwable $fallo) { $conexion->rollback(); throw $fallo; }
            $mensaje = $accion === 'crear_rol' ? 'Rol creado con los paneles elegidos. Ya podés asignarlo a un empleado.' : 'Nombre y paneles del rol guardados. Los cambios se aplican a todos sus empleados en la próxima página.';
        } else { throw new RuntimeException('Acción inválida.'); }
        $_SESSION['mensaje_empleos'] = $mensaje;
        header('Location: admin-empleos.php?puesto='.$puestoId,true,303); exit;
    } catch (Throwable $fallo) { error_log($fallo->getMessage()); $error = $fallo instanceof mysqli_sql_exception ? 'No se pudo guardar el cambio.' : $fallo->getMessage(); }
}
$empleados = $conexion->query("SELECT e.id_empleado,e.nombre,e.apellido,e.email,e.id_puesto FROM empleado e WHERE COALESCE(email,'') <> 'gpufixstore@gmail.com' ORDER BY nombre,apellido")->fetch_all(MYSQLI_ASSOC);
$permisos = admin_leer_permisos();
$rolActual = null;
foreach ($puestos as $puesto) if ((int)$puesto['id_puesto'] === $puestoId) $rolActual = $puesto;
$puestosPorNombre = [];
foreach ($puestos as $puesto) $puestosPorNombre[mb_strtolower(trim($puesto['nombre']), 'UTF-8')] = $puesto;
$panelesDisponibles = admin_secciones_permisos();
admin_layout_inicio('Implementación de Empleos');
?>
<h1 class="h3">Implementación de Empleos</h1>
<p>Los puestos de GPU FIX se organizan según el organigrama. Elegí uno para asignarlo a un empleado o ajustar sus accesos.</p>
<div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-primary" href="#crear-rol">Crear nuevo rol</a><a class="btn btn-outline-primary" href="admin-tablas.php?tabla=puesto">Ver puestos en Tablas SQL</a><a class="btn btn-outline-primary" href="admin-tablas.php?tabla=empleado&amp;nuevo=1">Crear empleado</a><a class="btn btn-outline-primary" href="admin-tablas.php?tabla=usuarios&amp;nuevo=1">Crear usuario</a></div>
<?php if ($mensaje): ?><p class="alert alert-success" role="status"><?= admin_e($mensaje) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-danger" role="alert"><?= admin_e($error) ?></p><?php endif; ?>
<section class="panel"><h2 class="h5">Puestos del organigrama</h2>
<p>Cada puesto incluye accesos iniciales a los paneles indicados. El permiso habilita las acciones disponibles dentro de ese panel. Tablas SQL y la asignación de roles están reservadas a gpufixstore@gmail.com.</p>
<form method="post" class="mb-3"><input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="accion" value="cargar_roles"><button class="btn btn-primary">Cargar puestos del organigrama</button></form>
<?php foreach (admin_roles_organigrama() as $area => $roles): ?>
<h3 class="h6 mt-4"><?= admin_e($area) ?></h3>
<div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Puesto</th><th>Paneles habilitados</th><th>Configuración</th></tr></thead><tbody>
<?php foreach ($roles as $nombreRol => $panelesIniciales):
    $puestoOrganigrama = $puestosPorNombre[mb_strtolower($nombreRol, 'UTF-8')] ?? null;
    $idOrganigrama = $puestoOrganigrama ? (int) $puestoOrganigrama['id_puesto'] : 0;
    $configurado = $idOrganigrama && array_key_exists((string) $idOrganigrama, $permisos);
    $panelesRol = $configurado ? $permisos[(string) $idOrganigrama] : $panelesIniciales;
    $nombresPaneles = array_map(static fn ($panel) => $panelesDisponibles[$panel] ?? $panel, $panelesRol);
?>
<tr><th scope="row"><?= admin_e($nombreRol) ?></th><td><?= admin_e($nombresPaneles ? implode(', ', $nombresPaneles) : 'Sin paneles habilitados') ?><?php if (!$configurado): ?><div class="text-secondary">Accesos iniciales pendientes de cargar.</div><?php endif; ?></td><td><?php if ($idOrganigrama): ?><a class="btn btn-sm btn-outline-primary" href="admin-empleos.php?puesto=<?= $idOrganigrama ?>#configurar-rol">Configurar</a><?php else: ?>Pendiente de carga<?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endforeach; ?></section>
<section class="panel" id="crear-rol"><h2 class="h5">Crear un nuevo rol</h2>
<form method="post"><input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="accion" value="crear_rol">
<label class="form-label" for="nombreNuevoRol">Nombre del rol</label><input class="form-control mb-3" id="nombreNuevoRol" name="nombre" maxlength="100" required value="<?= admin_e($error && ($_POST['accion'] ?? '') === 'crear_rol' ? ($_POST['nombre'] ?? '') : '') ?>">
<fieldset><legend class="h6">Paneles que podrá ver</legend><div class="row g-3 mb-3"><?php foreach(admin_secciones_permisos() as $pagina=>$nombre): ?><label class="col-md-6 col-lg-4"><input type="checkbox" name="paneles[]" value="<?= admin_e($pagina) ?>" <?= $error && ($_POST['accion'] ?? '') === 'crear_rol' && is_array($_POST['paneles'] ?? null) && in_array($pagina, $_POST['paneles'], true) ? 'checked' : '' ?>> <?= admin_e($nombre) ?></label><?php endforeach; ?></div></fieldset>
<button class="btn btn-primary">Crear rol con estos paneles</button></form></section>
<section class="panel" id="configurar-rol"><h2 class="h5">Editar nombre y paneles de un rol</h2>
<form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-3"><label class="flex-grow-1">Rol<select class="form-select" name="puesto" required><option value="">Seleccionar rol</option><?php foreach($puestos as $puesto): ?><option value="<?= (int)$puesto['id_puesto'] ?>" <?= $puestoId===$puesto['id_puesto'] || $puestoId===(int)$puesto['id_puesto'] ? 'selected' : '' ?>><?= admin_e($puesto['nombre']) ?></option><?php endforeach; ?></select></label><button class="btn btn-primary">Configurar</button></form>
<?php if(in_array($puestoId,array_map('intval',array_column($puestos,'id_puesto')),true)): ?>
<form method="post"><input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="accion" value="permisos"><input type="hidden" name="puesto" value="<?= $puestoId ?>">
<label class="form-label" for="nombreEditarRol">Nombre del rol</label><input class="form-control mb-3" id="nombreEditarRol" name="nombre" maxlength="100" required value="<?= admin_e($error && ($_POST['accion'] ?? '') === 'permisos' ? ($_POST['nombre'] ?? '') : ($rolActual['nombre'] ?? '')) ?>" <?= ($rolActual['nombre'] ?? '') === 'Administrador' ? 'readonly' : '' ?>>
<p>Estos accesos se comparten entre todos los empleados que tengan este rol. Tablas SQL e Implementación de Empleos quedan reservadas a GPUFIX.</p>
<div class="row g-3 mb-3"><?php foreach(admin_secciones_permisos() as $pagina=>$nombre): ?><label class="col-md-6 col-lg-4"><input type="checkbox" name="paneles[]" value="<?= admin_e($pagina) ?>" <?= in_array($pagina, $error && ($_POST['accion'] ?? '') === 'permisos' && is_array($_POST['paneles'] ?? null) ? $_POST['paneles'] : ($permisos[(string)$puestoId] ?? []), true)?'checked':'' ?>> <?= admin_e($nombre) ?></label><?php endforeach; ?></div>
<button class="btn btn-primary">Guardar cambios del rol</button></form><?php endif; ?></section>
<section class="panel"><h2 class="h5">Asignar un rol a cada empleado</h2>
<?php foreach($empleados as $empleado): ?><form method="post" class="row g-2 align-items-end mb-3"><input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="accion" value="asignar"><input type="hidden" name="empleado" value="<?= (int)$empleado['id_empleado'] ?>"><div class="col-md-5"><strong><?= admin_e($empleado['nombre'].' '.$empleado['apellido']) ?></strong><div><?= admin_e($empleado['email']) ?></div></div><label class="col-md">Rol<select class="form-select" name="puesto" required><?php foreach($puestos as $puesto): ?><option value="<?= (int)$puesto['id_puesto'] ?>" <?= $puesto['id_puesto']==$empleado['id_puesto']?'selected':'' ?>><?= admin_e($puesto['nombre']) ?></option><?php endforeach; ?></select></label><div class="col-md-auto"><button class="btn btn-primary">Asignar rol</button></div></form><?php endforeach; ?>
<?php if(!$empleados): ?><p>Todavía no hay otros empleados.</p><?php endif; ?></section>
<?php admin_layout_fin(); ?>
