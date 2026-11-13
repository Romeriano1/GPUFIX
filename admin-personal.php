<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
admin_validar_csrf();
$principal = admin_es_cuenta_principal($conexion);
$error = '';
$mensaje = $_SESSION['mensaje_personal'] ?? '';
unset($_SESSION['mensaje_personal']);
$id = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;
$nuevo = isset($_GET['nuevo']);
$puestos = $conexion->query('SELECT id_puesto,nombre FROM puesto ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
$datos = ['nombre'=>'','apellido'=>'','dni'=>'','telefono'=>'','email'=>'','id_puesto'=>''];
if ($id) {
    $consulta = $conexion->prepare('SELECT id_empleado,nombre,apellido,dni,telefono,email,id_puesto FROM empleado WHERE id_empleado=?');
    $consulta->bind_param('i', $id); $consulta->execute();
    $datos = $consulta->get_result()->fetch_assoc();
    if (!$datos) { http_response_code(404); exit('El empleado ya no existe.'); }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = filter_input(INPUT_POST, 'empleado', FILTER_VALIDATE_INT) ?: 0;
        $nuevo = $id === 0;
        if ($id === admin_id_principal() && !$principal) throw new RuntimeException('Solo GPUFIX puede modificar la cuenta principal.');
        $conexion->begin_transaction();
        if ($id) {
            $consulta = $conexion->prepare('SELECT id_empleado FROM empleado WHERE id_empleado=? FOR UPDATE');
            $consulta->bind_param('i', $id); $consulta->execute();
            if (!$consulta->get_result()->fetch_row()) throw new RuntimeException('El empleado ya no existe.');
        }
        $accion = $_POST['accion'] ?? '';
        if ($accion === 'eliminar') {
            if (!$principal || $id === admin_id_principal() || $id < 1) throw new RuntimeException('Esta cuenta no se puede eliminar.');
            if (($_POST['confirmar'] ?? '') !== '1') throw new RuntimeException('Confirmá la eliminación del empleado.');
            $consulta = $conexion->prepare('DELETE FROM empleado WHERE id_empleado=?');
            $consulta->bind_param('i', $id); $consulta->execute();
            $mensaje = 'Empleado eliminado.';
        } elseif ($accion === 'guardar') {
            foreach (array_keys($datos) as $campo) {
                if ($campo !== 'id_empleado') $datos[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
            }
            foreach (['nombre','apellido'] as $campo) {
                if (mb_strlen($datos[$campo]) > 100 || !preg_match("/^[\p{L}\p{M}]+(?:[ '\x{2019}-][\p{L}\p{M}]+)*$/uD", $datos[$campo])) throw new RuntimeException('Nombre y apellido deben contener letras, con hasta 100 caracteres.');
            }
            if (!preg_match('/^[0-9]{7,8}$/D', $datos['dni'])) throw new RuntimeException('El DNI debe tener 7 u 8 números.');
            if ($datos['telefono'] === '' || mb_strlen($datos['telefono']) > 30) throw new RuntimeException('Completá el teléfono, con hasta 30 caracteres.');
            $datos['email'] = strtolower($datos['email']);
            if (strlen($datos['email']) > 150 || !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Ingresá un correo válido.');
            $puesto = (int)$datos['id_puesto'];
            if (!in_array($puesto, array_map('intval', array_column($puestos,'id_puesto')), true)) throw new RuntimeException('Seleccioná un puesto existente.');
            $clave = is_string($_POST['contrasena'] ?? null) ? $_POST['contrasena'] : '';
            if ($nuevo || $clave !== '') {
                if (strlen($clave) < 8 || strlen($clave) > 72 || str_contains($clave, "\0")) throw new RuntimeException('La contraseña debe tener entre 8 y 72 caracteres.');
                if ($clave !== ($_POST['confirmacion'] ?? '')) throw new RuntimeException('Las contraseñas no coinciden.');
            }
            if ($nuevo) {
                $hash = password_hash($clave, PASSWORD_DEFAULT);
                $consulta = $conexion->prepare('INSERT INTO empleado(nombre,apellido,dni,telefono,email,id_puesto,`contraseña`) VALUES(?,?,?,?,?,?,?)');
                $consulta->bind_param('sssssis', $datos['nombre'],$datos['apellido'],$datos['dni'],$datos['telefono'],$datos['email'],$puesto,$hash);
            } else {
                $consulta = $conexion->prepare('UPDATE empleado SET nombre=?,apellido=?,dni=?,telefono=?,email=?,id_puesto=? WHERE id_empleado=?');
                $consulta->bind_param('sssssii', $datos['nombre'],$datos['apellido'],$datos['dni'],$datos['telefono'],$datos['email'],$puesto,$id);
            }
            $consulta->execute();
            if ($nuevo) $id = (int)$conexion->insert_id;
            elseif ($clave !== '') {
                $hash = password_hash($clave, PASSWORD_DEFAULT);
                $consulta = $conexion->prepare('UPDATE empleado SET `contraseña`=? WHERE id_empleado=?');
                $consulta->bind_param('si',$hash,$id); $consulta->execute();
            }
            $mensaje = 'Datos del empleado guardados.';
        } else { throw new RuntimeException('Acción no válida.'); }
        $conexion->commit();
        $_SESSION['mensaje_personal'] = $mensaje;
        header('Location: admin-personal.php',true,303); exit;
    } catch (Throwable $fallo) {
        $conexion->rollback();
        error_log('Personal: '.$fallo->getMessage());
        $error = $fallo instanceof mysqli_sql_exception ? ($fallo->getCode() === 1062 ? 'El correo o DNI ya pertenece a otro empleado.' : 'No se pudo guardar. Revisá si el empleado tiene operaciones relacionadas.') : $fallo->getMessage();
    }
}
$empleados = $conexion->query('SELECT e.id_empleado,e.nombre,e.apellido,e.email,e.dni,p.nombre puesto FROM empleado e JOIN puesto p ON p.id_puesto=e.id_puesto ORDER BY e.apellido,e.nombre')->fetch_all(MYSQLI_ASSOC);
admin_layout_inicio('Empleados');
?>
<div class="d-flex flex-wrap gap-3 justify-content-between align-items-center"><h1 class="h3">Empleados</h1><a class="btn btn-primary" href="?nuevo=1#ficha-empleado">Agregar empleado</a></div>
<p>Cuentas de acceso del personal y puestos asignados.</p>
<?php if ($mensaje): ?><div class="alert alert-success"><?= admin_e($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
<?php if (($nuevo || $id) && ($id !== admin_id_principal() || $principal)): ?>
<section class="panel" id="ficha-empleado"><h2 class="h5"><?= $nuevo ? 'Agregar empleado' : 'Editar empleado' ?></h2>
<form method="post" class="row g-3"><input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="accion" value="guardar"><input type="hidden" name="empleado" value="<?= $nuevo ? 0 : $id ?>">
<?php foreach (['nombre'=>'Nombre','apellido'=>'Apellido','dni'=>'DNI','telefono'=>'Teléfono','email'=>'Correo electrónico'] as $campo=>$etiqueta): ?><div class="col-md-6"><label class="form-label" for="personal-<?= $campo ?>"><?= $etiqueta ?></label><input class="form-control" id="personal-<?= $campo ?>" name="<?= $campo ?>" type="<?= $campo === 'email' ? 'email' : 'text' ?>" value="<?= admin_e($datos[$campo]) ?>" maxlength="<?= ['nombre'=>100,'apellido'=>100,'dni'=>8,'telefono'=>30,'email'=>150][$campo] ?>" <?= $campo==='dni' ? 'pattern="[0-9]{7,8}" inputmode="numeric"' : '' ?> required></div><?php endforeach; ?>
<div class="col-md-6"><label class="form-label" for="personal-puesto">Puesto</label><select class="form-select" id="personal-puesto" name="id_puesto" required><option value="">Seleccioná un puesto</option><?php foreach ($puestos as $puesto): ?><option value="<?= (int)$puesto['id_puesto'] ?>" <?= (int)$datos['id_puesto']===(int)$puesto['id_puesto'] ? 'selected' : '' ?>><?= admin_e($puesto['nombre']) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><p class="small text-secondary mb-0"><?= $nuevo ? 'Asigná la contraseña para el acceso de este empleado.' : 'Completá ambos campos para cambiar la contraseña.' ?></p></div>
<div class="col-md-6"><label class="form-label" for="personal-clave">Contraseña</label><input class="form-control" id="personal-clave" name="contrasena" type="password" autocomplete="new-password" minlength="8" maxlength="72" <?= $nuevo ? 'required' : '' ?>></div>
<div class="col-md-6"><label class="form-label" for="personal-confirmacion">Repetir contraseña</label><input class="form-control" id="personal-confirmacion" name="confirmacion" type="password" autocomplete="new-password" minlength="8" maxlength="72" <?= $nuevo ? 'required' : '' ?>></div>
<div class="col-12 d-flex gap-2"><button class="btn btn-primary">Guardar empleado</button><a class="btn btn-outline-secondary" href="admin-personal.php">Cancelar</a></div></form></section>
<?php endif; ?>
<section class="panel"><div class="table-responsive"><table class="table"><thead><tr><th>Empleado</th><th>Correo</th><th>DNI</th><th>Puesto</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($empleados as $empleado): ?><tr><th scope="row"><?= admin_e($empleado['nombre'].' '.$empleado['apellido']) ?></th><td><?= admin_e($empleado['email']) ?></td><td><?= admin_e($empleado['dni']) ?></td><td><?= admin_e($empleado['puesto']) ?></td><td>
<?php if ((int)$empleado['id_empleado'] !== admin_id_principal() || $principal): ?><a class="btn btn-sm btn-outline-primary" href="?editar=<?= (int)$empleado['id_empleado'] ?>#ficha-empleado">Editar</a><?php endif; ?>
<?php if ($principal && (int)$empleado['id_empleado'] !== admin_id_principal()): ?><form method="post" class="mt-2"><input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="empleado" value="<?= (int)$empleado['id_empleado'] ?>"><label class="small d-block"><input type="checkbox" name="confirmar" value="1" required> Confirmar eliminación</label><button class="btn btn-sm btn-outline-danger">Eliminar</button></form><?php endif; ?>
</td></tr><?php endforeach; ?></tbody></table></div></section>
<?php admin_layout_fin(); ?>
