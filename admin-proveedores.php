<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
require_once __DIR__ . '/conexion.php';

$mensaje = (string) ($_SESSION['mensaje_proveedor_admin'] ?? '');
unset($_SESSION['mensaje_proveedor_admin']);
$error = '';
$nombreProveedor = '';
$transaccionActiva = false;
$idProveedor = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$modoEdicion = isset($_GET['editar']);
$mostrarFormulario = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validar_csrf();
    $accion = is_string($_POST['accion'] ?? null) ? $_POST['accion'] : '';
    $idProveedor = filter_input(INPUT_POST, 'id_proveedor', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $modoEdicion = $accion === 'editar';
    $nombreProveedor = is_string($_POST['nombre'] ?? null) ? trim($_POST['nombre']) : '';

    try {
        if (!in_array($accion, ['agregar', 'editar', 'eliminar'], true)
            || (in_array($accion, ['editar', 'eliminar'], true) && !$idProveedor)) {
            throw new RuntimeException('Seleccioná un proveedor válido.');
        }
        if ($accion !== 'eliminar' && ($nombreProveedor === '' || mb_strlen($nombreProveedor, 'UTF-8') > 100)) {
            throw new RuntimeException('Ingresá un nombre de proveedor de hasta 100 caracteres.');
        }
        $conexion->begin_transaction();
        $transaccionActiva = true;
        if ($accion !== 'agregar') {
            $buscar = $conexion->prepare('SELECT id_proveedor FROM proveedor WHERE id_proveedor = ? FOR UPDATE');
            $buscar->bind_param('i', $idProveedor);
            $buscar->execute();
            $existe = $buscar->get_result()->fetch_assoc();
            $buscar->close();
            if (!$existe) throw new RuntimeException('El proveedor seleccionado ya no existe.');
        }
        if ($accion === 'eliminar') {
            $buscarCompra = $conexion->prepare('SELECT id_orden_compra FROM orden_compra WHERE id_proveedor = ? LIMIT 1');
            $buscarCompra->bind_param('i', $idProveedor);
            $buscarCompra->execute();
            $tieneCompras = $buscarCompra->get_result()->fetch_assoc();
            $buscarCompra->close();
            if ($tieneCompras) throw new RuntimeException('Este proveedor tiene compras asociadas y no se puede eliminar.');
            $eliminar = $conexion->prepare('DELETE FROM proveedor WHERE id_proveedor = ?');
            $eliminar->bind_param('i', $idProveedor);
            $eliminar->execute();
            $eliminar->close();
            $conexion->commit();
            $transaccionActiva = false;
            $_SESSION['mensaje_proveedor_admin'] = 'Proveedor eliminado.';
            header('Location: admin-proveedores.php#lista-proveedores', true, 303);
            exit;
        }

        $excluirId = $accion === 'editar' ? $idProveedor : 0;
        $buscarNombre = $conexion->prepare('SELECT id_proveedor FROM proveedor WHERE LOWER(TRIM(razon_social)) = LOWER(?) AND id_proveedor <> ? LIMIT 1 FOR UPDATE');
        $buscarNombre->bind_param('si', $nombreProveedor, $excluirId);
        $buscarNombre->execute();
        $duplicado = $buscarNombre->get_result()->fetch_assoc();
        $buscarNombre->close();
        if ($duplicado) throw new RuntimeException('Ya existe un proveedor con ese nombre.');

        if ($accion === 'agregar') {
            $guardar = $conexion->prepare('INSERT INTO proveedor (razon_social) VALUES (?)');
            $guardar->bind_param('s', $nombreProveedor);
        } else {
            $guardar = $conexion->prepare('UPDATE proveedor SET razon_social = ? WHERE id_proveedor = ?');
            $guardar->bind_param('si', $nombreProveedor, $idProveedor);
        }
        $guardar->execute();
        if ($accion === 'agregar') $idProveedor = (int) $conexion->insert_id;
        $guardar->close();
        $conexion->commit();
        $transaccionActiva = false;
        $_SESSION['mensaje_proveedor_admin'] = $accion === 'agregar' ? 'Proveedor agregado.' : 'Proveedor actualizado.';
        header('Location: admin-proveedores.php?editar=' . $idProveedor . '#ficha-proveedor', true, 303);
        exit;
    } catch (Throwable $fallo) {
        if ($transaccionActiva) $conexion->rollback();
        error_log('Error al guardar proveedor: ' . $fallo->getMessage());
        $error = $fallo instanceof mysqli_sql_exception ? 'No pudimos guardar el cambio. Intentá nuevamente.' : $fallo->getMessage();
    }
} elseif ($modoEdicion) {
    $buscar = $conexion->prepare('SELECT razon_social FROM proveedor WHERE id_proveedor = ?');
    $buscar->bind_param('i', $idProveedor);
    $buscar->execute();
    $proveedor = $buscar->get_result()->fetch_assoc();
    $buscar->close();
    if (!$proveedor) {
        http_response_code(404);
        $error = 'El proveedor seleccionado no existe.';
        $mostrarFormulario = false;
    } else {
        $nombreProveedor = $proveedor['razon_social'];
    }
}

$proveedores = $conexion->query('SELECT p.id_proveedor, p.razon_social, EXISTS (SELECT 1 FROM orden_compra o WHERE o.id_proveedor = p.id_proveedor) AS tiene_compras FROM proveedor p ORDER BY p.razon_social, p.id_proveedor');
admin_layout_inicio('Proveedores');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
    <h1 class="h3 mb-0">Proveedores</h1>
    <a class="btn btn-primary" href="admin-proveedores.php#ficha-proveedor">Agregar proveedor</a>
</div>
<p class="text-secondary mt-2">Estos nombres aparecen en el selector de marca al cargar un producto.</p>
<?php if ($mensaje !== ''): ?><div class="alert alert-success" role="status"><?= admin_e($mensaje) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= admin_e($error) ?></div><?php endif; ?>

<?php if ($mostrarFormulario): ?>
<section class="panel" id="ficha-proveedor" aria-labelledby="tituloProveedor">
    <h2 class="h5 mb-3" id="tituloProveedor"><?= $modoEdicion ? 'Editar proveedor' : 'Agregar proveedor' ?></h2>
    <form method="post" action="admin-proveedores.php<?= $modoEdicion ? '?editar=' . $idProveedor : '' ?>" class="row g-3 align-items-end">
        <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
        <input type="hidden" name="accion" value="<?= $modoEdicion ? 'editar' : 'agregar' ?>">
        <input type="hidden" name="id_proveedor" value="<?= $idProveedor ?>">
        <div class="col-md-8"><label for="nombreProveedor" class="form-label">Nombre del proveedor</label><input id="nombreProveedor" class="form-control" name="nombre" maxlength="100" value="<?= admin_e($nombreProveedor) ?>" required></div>
        <div class="col-md-auto"><button class="btn btn-primary" type="submit">Guardar proveedor</button></div>
        <?php if ($modoEdicion): ?><div class="col-md-auto"><a class="btn btn-outline-secondary" href="admin-proveedores.php#lista-proveedores">Cancelar</a></div><?php endif; ?>
    </form>
</section>
<?php endif; ?>

<section class="panel" id="lista-proveedores" aria-labelledby="tituloListaProveedores">
    <h2 class="h5 mb-3" id="tituloListaProveedores">Proveedores guardados</h2>
    <div class="table-responsive"><table class="table table-striped align-middle">
        <thead><tr><th>Nombre</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php if ($proveedores->num_rows === 0): ?>
            <tr><td colspan="2" class="text-secondary py-4">Todavía no hay proveedores guardados.</td></tr>
        <?php else: while ($proveedor = $proveedores->fetch_assoc()): ?>
            <tr>
                <td><?= admin_e($proveedor['razon_social']) ?></td>
                <td><div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-sm btn-outline-primary" href="admin-proveedores.php?editar=<?= (int) $proveedor['id_proveedor'] ?>#ficha-proveedor">Editar</a>
                    <form method="post" action="admin-proveedores.php" onsubmit="return confirm('¿Eliminar este proveedor?');">
                        <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id_proveedor" value="<?= (int) $proveedor['id_proveedor'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit" <?= $proveedor['tiene_compras'] ? 'disabled title="Tiene compras asociadas"' : '' ?>>Eliminar</button>
                    </form>
                </div></td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table></div>
</section>
<?php
$proveedores->free();
$conexion->close();
admin_layout_fin();

