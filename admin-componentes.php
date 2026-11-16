<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
if (($_GET['modo'] ?? '') === 'datos' && admin_es_cuenta_principal($conexion)) {
    header('Location: admin-tablas.php?tabla=componente', true, 303);
    exit;
}
require_once __DIR__ . '/sucursales.php';
admin_validar_csrf();
$puedeGestionar = admin_puede_gestionar_componentes($conexion);
$transaccionActiva = false;
$sucursales = admin_sucursales_disponibles($conexion);
$idSucursal = (string)($_GET['sucursal'] ?? $sucursales[0]['id_sucursal'] ?? '');
$error = '';
$mensaje = $_SESSION['mensaje_stock_componentes'] ?? '';
unset($_SESSION['mensaje_stock_componentes']);
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') $idSucursal = is_string($_POST['id_sucursal'] ?? null) ? $_POST['id_sucursal'] : '';
    if ($idSucursal !== '') admin_validar_sucursal_empleado($conexion, $idSucursal);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$puedeGestionar) { http_response_code(403); throw new RuntimeException('Solo el Jefe Técnico y administración pueden crear componentes o modificar su stock.'); }
        $accionComponente = is_string($_POST['accion'] ?? null) ? $_POST['accion'] : '';
        if (in_array($accionComponente, ['crear_componente', 'editar_componente'], true)) {
            $nombreComponente = is_string($_POST['nombre'] ?? null) ? trim($_POST['nombre']) : '';
            $descripcionComponente = is_string($_POST['descripcion'] ?? null) ? trim($_POST['descripcion']) : '';
            $idFicha = filter_input(INPUT_POST, 'id_componente', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if ($nombreComponente === '' || mb_strlen($nombreComponente, 'UTF-8') > 150 || $descripcionComponente === '' || mb_strlen($descripcionComponente, 'UTF-8') > 3000) throw new RuntimeException('Completá nombre (hasta 150 caracteres) y descripción (hasta 3000).');
            if ($accionComponente === 'crear_componente') {
                $guardarFicha = $conexion->prepare('INSERT INTO componente(nombre,descripcion) VALUES(?,?)');
                $guardarFicha->bind_param('ss', $nombreComponente, $descripcionComponente);
            } else {
                if (!$idFicha) throw new RuntimeException('Seleccioná un componente válido.');
                $guardarFicha = $conexion->prepare('UPDATE componente SET nombre=?,descripcion=? WHERE id_componente=?');
                $guardarFicha->bind_param('ssi', $nombreComponente, $descripcionComponente, $idFicha);
            }
            $guardarFicha->execute(); $guardarFicha->close();
            $_SESSION['mensaje_stock_componentes'] = 'Ficha del componente guardada.';
            header('Location: admin-componentes.php?sucursal='.rawurlencode($idSucursal),true,303); exit;
        }
        if ($accionComponente !== 'guardar_stock') throw new RuntimeException('No se reconoció la acción.');
        $idComponente = filter_input(INPUT_POST,'id_componente',FILTER_VALIDATE_INT);
        $cantidad = filter_input(INPUT_POST,'cantidad',FILTER_VALIDATE_INT);
        if (!$idComponente || $cantidad === false || $cantidad === null || $cantidad < 0 || $cantidad > 2147483647) throw new RuntimeException('Indicá un componente y una cantidad válida.');
        admin_validar_sucursal_empleado($conexion, $idSucursal);
        $conexion->begin_transaction();
        $transaccionActiva = true;
        $consulta = $conexion->prepare('SELECT id_componente FROM componente WHERE id_componente=? FOR UPDATE');
        $consulta->bind_param('i',$idComponente); $consulta->execute();
        if (!$consulta->get_result()->fetch_row()) throw new RuntimeException('El componente ya no existe.');
        $consulta = $conexion->prepare('SELECT id_stock FROM stock WHERE id_componente=? AND id_sucursal=? ORDER BY id_stock FOR UPDATE');
        $consulta->bind_param('is',$idComponente,$idSucursal); $consulta->execute();
        $filas = $consulta->get_result()->fetch_all(MYSQLI_ASSOC);
        if (!$filas) {
            $estado = $cantidad > 0 ? 'Disponible' : 'Sin stock';
            $consulta = $conexion->prepare('INSERT INTO stock(id_componente,id_sucursal,cantidad,estado) VALUES(?,?,?,?)');
            $consulta->bind_param('isis',$idComponente,$idSucursal,$cantidad,$estado); $consulta->execute();
        } else foreach ($filas as $indice=>$fila) {
            $unidades = $indice === 0 ? $cantidad : 0;
            $estado = $unidades > 0 ? 'Disponible' : 'Sin stock';
            $consulta = $conexion->prepare('UPDATE stock SET cantidad=?,estado=? WHERE id_stock=?');
            $consulta->bind_param('isi',$unidades,$estado,$fila['id_stock']); $consulta->execute();
        }
        $conexion->commit();
        $transaccionActiva = false;
        $_SESSION['mensaje_stock_componentes'] = 'Stock de la sucursal actualizado.';
        header('Location: admin-componentes.php?sucursal='.rawurlencode($idSucursal),true,303); exit;
    }
} catch (Throwable $fallo) {
    if ($transaccionActiva) $conexion->rollback();
    $idSucursal = (string)($sucursales[0]['id_sucursal'] ?? '');
    error_log('Stock componentes: '.$fallo->getMessage());
    $error = $fallo instanceof RuntimeException ? $fallo->getMessage() : 'No se pudo actualizar el stock.';
}
$consulta = $conexion->prepare('SELECT c.id_componente,c.nombre,c.descripcion,COALESCE(SUM(s.cantidad),0) cantidad FROM componente c LEFT JOIN stock s ON s.id_componente=c.id_componente AND s.id_sucursal=? GROUP BY c.id_componente,c.nombre,c.descripcion ORDER BY c.nombre');
$consulta->bind_param('s',$idSucursal); $consulta->execute();
$componentes = $consulta->get_result()->fetch_all(MYSQLI_ASSOC);
admin_layout_inicio('Componentes y repuestos');
?>
<h1 class="h3">Componentes y repuestos</h1>
<?php if (admin_puede_pedir_stock($conexion) && admin_puede_ver($conexion,'admin-solicitudes-stock.php')): ?><a class="btn btn-primary my-3" href="admin-solicitudes-stock.php">Solicitar componentes al depósito</a><?php endif; ?>
<?php if ($mensaje): ?><div class="alert alert-success"><?= admin_e($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= admin_e($error) ?></div><?php endif; ?>
<?php if ($puedeGestionar): ?>
<section class="panel"><h2 class="h5">Nuevo componente</h2>
<form method="post" class="row g-3">
    <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="accion" value="crear_componente"><input type="hidden" name="id_sucursal" value="<?= admin_e($idSucursal) ?>">
    <div class="col-md-5"><label class="form-label" for="nuevoComponente">Nombre</label><input class="form-control" id="nuevoComponente" name="nombre" maxlength="150" required></div>
    <div class="col-md-7"><label class="form-label" for="descripcionComponente">Descripción y compatibilidad</label><textarea class="form-control" id="descripcionComponente" name="descripcion" maxlength="3000" rows="2" required></textarea></div>
    <div><button class="btn btn-primary">Crear componente</button></div>
</form></section>
<?php else: ?><p>Podés consultar las existencias. Los ajustes de stock corresponden al Jefe Técnico y administración.</p><?php endif; ?>
<?php if (admin_puede_ver($conexion, 'admin-reparaciones.php')): ?><a class="btn btn-outline-primary" href="<?= admin_e(admin_url_panel('reparaciones')) ?>#ordenes-reparacion">Asignar componentes a un trabajo</a><?php endif; ?>
<section class="panel">
<form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-3"><div><label class="form-label" for="sucursalComponente">Sucursal</label><select class="form-select" id="sucursalComponente" name="sucursal" required><option value="">Seleccionar sucursal</option><?php foreach ($sucursales as $sucursal): ?><option value="<?= (int)$sucursal['id_sucursal'] ?>" <?= (string)$sucursal['id_sucursal']===$idSucursal ? 'selected' : '' ?>><?= admin_e($sucursal['nombre']) ?></option><?php endforeach; ?></select></div><button class="btn btn-outline-primary">Ver stock</button></form>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Componente</th><th>Descripción</th><th>Stock de la sucursal</th><?php if ($puedeGestionar): ?><th>Ficha</th><?php endif; ?></tr></thead><tbody>
<?php foreach($componentes as $fila): ?><tr><td><?= admin_e($fila['nombre']) ?></td><td><?= admin_e($fila['descripcion']) ?></td><td>
<?php if ($puedeGestionar): ?>
<form method="post" class="d-flex gap-2"><input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="accion" value="guardar_stock"><input type="hidden" name="id_sucursal" value="<?= admin_e($idSucursal) ?>"><input type="hidden" name="id_componente" value="<?= (int)$fila['id_componente'] ?>"><input class="form-control" type="number" name="cantidad" min="0" max="2147483647" value="<?= (int)$fila['cantidad'] ?>" aria-label="<?= admin_e('Cantidad total de '.$fila['nombre']) ?>" required><button class="btn btn-primary">Guardar</button></form>
<?php else: ?><?= (int)$fila['cantidad'] ?> unidades<?php endif; ?></td>
<?php if ($puedeGestionar): ?><td><details><summary class="btn btn-sm btn-outline-primary">Editar ficha</summary><form method="post" class="d-grid gap-2 mt-3">
<input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="accion" value="editar_componente"><input type="hidden" name="id_sucursal" value="<?= admin_e($idSucursal) ?>"><input type="hidden" name="id_componente" value="<?= (int)$fila['id_componente'] ?>">
<label>Nombre<input class="form-control" name="nombre" maxlength="150" required value="<?= admin_e($fila['nombre']) ?>"></label><label>Descripción<textarea class="form-control" name="descripcion" maxlength="3000" rows="3" required><?= admin_e($fila['descripcion']) ?></textarea></label><button class="btn btn-primary">Guardar ficha</button></form></details></td><?php endif; ?></tr><?php endforeach; ?>
<?php if(!$componentes): ?><tr><td colspan="<?= $puedeGestionar ? 4 : 3 ?>">Todavía no hay componentes.</td></tr><?php endif; ?></tbody></table></div>
<?php if ($puedeGestionar): ?><p class="form-text">La cantidad guardada reemplaza el total de esa sucursal. Usá 0 para dejar el componente sin stock.</p><?php endif; ?>
</section>
<?php admin_layout_fin(); ?>
