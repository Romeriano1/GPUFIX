<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
if (($_GET['modo'] ?? '') === 'datos' && admin_es_cuenta_principal($conexion)) {
    header('Location: admin-tablas.php?tabla=componente', true, 303);
    exit;
}
require_once __DIR__ . '/sucursales.php';
admin_validar_csrf();
$sucursales = sucursales_listar($conexion);
$idSucursal = (string)($_GET['sucursal'] ?? $sucursales[0]['id_sucursal'] ?? '');
$error = '';
$mensaje = $_SESSION['mensaje_stock_componentes'] ?? '';
unset($_SESSION['mensaje_stock_componentes']);
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') $idSucursal = is_string($_POST['id_sucursal'] ?? null) ? $_POST['id_sucursal'] : '';
    if ($idSucursal !== '') sucursal_validar($conexion, $idSucursal);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $idComponente = filter_input(INPUT_POST,'id_componente',FILTER_VALIDATE_INT);
        $cantidad = filter_input(INPUT_POST,'cantidad',FILTER_VALIDATE_INT);
        if (!$idComponente || $cantidad === false || $cantidad === null || $cantidad < 0) throw new RuntimeException('Indicá un componente y una cantidad válida.');
        sucursal_validar($conexion, $idSucursal);
        $conexion->begin_transaction();
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
        $_SESSION['mensaje_stock_componentes'] = 'Stock de la sucursal actualizado.';
        header('Location: admin-componentes.php?sucursal='.rawurlencode($idSucursal),true,303); exit;
    }
} catch (Throwable $fallo) {
    $conexion->rollback();
    error_log('Stock componentes: '.$fallo->getMessage());
    $error = $fallo instanceof RuntimeException ? $fallo->getMessage() : 'No se pudo actualizar el stock.';
}
$consulta = $conexion->prepare('SELECT c.id_componente,c.nombre,c.descripcion,COALESCE(SUM(s.cantidad),0) cantidad FROM componente c LEFT JOIN stock s ON s.id_componente=c.id_componente AND s.id_sucursal=? GROUP BY c.id_componente,c.nombre,c.descripcion ORDER BY c.nombre');
$consulta->bind_param('s',$idSucursal); $consulta->execute();
$componentes = $consulta->get_result()->fetch_all(MYSQLI_ASSOC);
admin_layout_inicio('Componentes y repuestos');
?>
<h1 class="h3">Componentes y repuestos</h1>
<?php if ($mensaje): ?><div class="alert alert-success"><?= admin_e($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= admin_e($error) ?></div><?php endif; ?>
<section class="panel"><form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-3"><div><label class="form-label" for="sucursalComponente">Sucursal</label><select class="form-select" id="sucursalComponente" name="sucursal" required><option value="">Seleccionar sucursal</option><?php foreach ($sucursales as $sucursal): ?><option value="<?= (int)$sucursal['id_sucursal'] ?>" <?= (string)$sucursal['id_sucursal']===$idSucursal ? 'selected' : '' ?>><?= admin_e($sucursal['nombre']) ?></option><?php endforeach; ?></select></div><button class="btn btn-outline-primary">Ver stock</button></form><div class="table-responsive"><table class="table"><thead><tr><th>Componente</th><th>Descripción</th><th>Stock de la sucursal</th></tr></thead><tbody>
<?php foreach($componentes as $fila): ?><tr><td><?= admin_e($fila['nombre']) ?></td><td><?= admin_e($fila['descripcion']) ?></td><td><form method="post" class="d-flex gap-2"><input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="id_sucursal" value="<?= admin_e($idSucursal) ?>"><input type="hidden" name="id_componente" value="<?= (int)$fila['id_componente'] ?>"><input class="form-control" type="number" name="cantidad" min="0" max="2147483647" value="<?= (int)$fila['cantidad'] ?>" aria-label="<?= admin_e('Cantidad de '.$fila['nombre']) ?>" required><button class="btn btn-primary">Guardar</button></form></td></tr><?php endforeach; ?>
<?php if(!$componentes): ?><tr><td colspan="3">Todavía no hay componentes.</td></tr><?php endif; ?></tbody></table></div></section>
<?php admin_layout_fin(); ?>
