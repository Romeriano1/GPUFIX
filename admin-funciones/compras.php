<?php
if (!defined('GPUFIX_TABLA_ACCIONES')) { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/admin-comun.php';
admin_requerir_acceso();
require_once dirname(__DIR__) . '/admin-edicion-datos.php';
require_once dirname(__DIR__) . '/compras-proveedores.php';
admin_validar_csrf();
$error = '';
$sucursalesCompra = sucursales_listar($conexion);
$mensaje = (string) ($_SESSION['mensaje_compra_proveedor'] ?? '');
unset($_SESSION['mensaje_compra_proveedor']);
if (empty($_SESSION['token_recepcion_compra'])) $_SESSION['token_recepcion_compra'] = bin2hex(random_bytes(32));
$proveedorIngresado = '';
$filasFormulario = [['producto' => '', 'cantidad' => '1', 'costo' => '']];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaccion = false;
    try {
        $token = is_string($_POST['token_recepcion'] ?? null) ? $_POST['token_recepcion'] : '';
        if ($token !== '' && isset($_SESSION['recepciones_completadas'][$token])) {
            $_SESSION['mensaje_compra_proveedor'] = 'Esta compra ya estaba registrada. No se volvió a sumar stock.';
            header('Location: ' . admin_url_panel('compras') . '#compra-' . (int) $_SESSION['recepciones_completadas'][$token], true, 303);
            exit;
        }
        if (!hash_equals($_SESSION['token_recepcion_compra'], $token)) throw new RuntimeException('La solicitud venció. Recargá la página antes de registrar la compra.');
        $proveedorIngresado = is_string($_POST['id_proveedor'] ?? null) ? $_POST['id_proveedor'] : '';
        $proveedor = filter_var($proveedorIngresado, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$proveedor) throw new RuntimeException('Seleccioná el proveedor de esta compra.');
        $productos = is_array($_POST['producto'] ?? null) ? array_values($_POST['producto']) : [];
        $cantidades = is_array($_POST['cantidad'] ?? null) ? array_values($_POST['cantidad']) : [];
        $costos = is_array($_POST['costo'] ?? null) ? array_values($_POST['costo']) : [];
        $filasFormulario = [];
        foreach (array_slice($productos, 0, 50) as $i => $producto) {
            $filasFormulario[] = ['producto' => is_string($producto) ? $producto : '', 'cantidad' => is_string($cantidades[$i] ?? null) ? $cantidades[$i] : '', 'costo' => is_string($costos[$i] ?? null) ? $costos[$i] : ''];
        }
        if (!$filasFormulario) $filasFormulario = [['producto' => '', 'cantidad' => '1', 'costo' => '']];
        $compra = compras_validar_lineas($productos, $cantidades, $costos);
        $conexion->begin_transaction();
        $transaccion = true;
        $idCompra = compras_registrar_recepcion($conexion, (int) $proveedor, (int) $_SESSION['empleado_id'], $compra, is_string($_POST['ubicacion'] ?? null) ? $_POST['ubicacion'] : '');
        $conexion->commit();
        $transaccion = false;
        $_SESSION['recepciones_completadas'][$token] = $idCompra;
        if (count($_SESSION['recepciones_completadas']) > 20) array_shift($_SESSION['recepciones_completadas']);
        $_SESSION['token_recepcion_compra'] = bin2hex(random_bytes(32));
        $_SESSION['mensaje_compra_proveedor'] = 'Compra #' . $idCompra . ' recibida. Las unidades ya se sumaron al stock.';
        header('Location: ' . admin_url_panel('compras') . '#compra-' . $idCompra, true, 303);
        exit;
    } catch (Throwable $fallo) {
        if ($transaccion) $conexion->rollback();
        error_log('Error al registrar compra a proveedor: ' . $fallo->getMessage());
        $error = $fallo instanceof mysqli_sql_exception ? 'No pudimos registrar la compra. No se modificó el stock. Revisá los datos e intentá nuevamente.' : $fallo->getMessage();
    }
}
$proveedores = $conexion->query('SELECT id_proveedor, razon_social FROM proveedor ORDER BY razon_social')->fetch_all(MYSQLI_ASSOC);
$productos = $conexion->query("SELECT CONCAT('gpu:', id_gpu) AS valor, CONCAT(marca, ' ', modelo) AS nombre FROM gpu ORDER BY marca, modelo")->fetch_all(MYSQLI_ASSOC);
$componentes = $conexion->query("SELECT CONCAT('componente:', id_componente) AS valor, nombre FROM componente ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
function compras_opciones_producto(array $productos, array $componentes, string $seleccion): void {
    echo '<option value="">Seleccionar producto</option>';
    foreach (['Placas de video' => $productos, 'Componentes' => $componentes] as $grupo => $opciones) {
        if (!$opciones) continue;
        echo '<optgroup label="' . admin_e($grupo) . '">';
        foreach ($opciones as $opcion) echo '<option value="' . admin_e($opcion['valor']) . '"' . ($seleccion === $opcion['valor'] ? ' selected' : '') . '>' . admin_e($opcion['nombre']) . '</option>';
        echo '</optgroup>';
    }
}
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$totalCompras = (int) $conexion->query('SELECT COUNT(*) FROM orden_compra')->fetch_row()[0];
$paginas = max(1, (int) ceil($totalCompras / 20));
$pagina = min($pagina, $paginas);
$compras = $conexion->query('SELECT o.*, p.razon_social, e.nombre, e.apellido FROM orden_compra o INNER JOIN proveedor p ON p.id_proveedor = o.id_proveedor INNER JOIN empleado e ON e.id_empleado = o.id_empleado_recepcion ORDER BY o.id_orden_compra DESC LIMIT 20 OFFSET ' . (($pagina - 1) * 20))->fetch_all(MYSQLI_ASSOC);
$detallesPorCompra = [];
if ($compras) {
    $ids = implode(',', array_map(static fn(array $compra): int => (int) $compra['id_orden_compra'], $compras));
    $detalles = $conexion->query("SELECT d.*, COALESCE(d.descripcion, CONCAT(g.marca, ' ', g.modelo), c.nombre, 'Producto') AS nombre_producto FROM detalle_compra d LEFT JOIN gpu g ON g.id_gpu = d.id_gpu LEFT JOIN componente c ON c.id_componente = d.id_componente WHERE d.id_orden_compra IN ($ids) ORDER BY d.id_detalle_compra");
    while ($detalle = $detalles->fetch_assoc()) $detallesPorCompra[$detalle['id_orden_compra']][] = $detalle;
}
admin_layout_inicio('Compras a proveedores');
?>
<h1 class="h3 mt-3">Compras a proveedores</h1>
<p class="text-secondary">Registrá los productos que recibiste de un proveedor. Al confirmar, sus cantidades se suman al stock actual.</p>
<?php if ($mensaje !== ''): ?><div class="alert alert-success" role="status"><?= admin_e($mensaje) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
<section class="panel" aria-labelledby="tituloRecepcion">
    <h2 class="h5 mb-3" id="tituloRecepcion">Registrar compra recibida</h2>
    <?php if (!$proveedores || (!$productos && !$componentes)): ?>
        <p>Para registrar una compra, primero cargá un <a href="<?= admin_e(admin_url_panel('proveedores')) ?>">proveedor</a> y un <a href="<?= admin_e(admin_url_panel('productos')) ?>">producto</a>.</p>
    <?php else: ?>
    <form method="post" action="<?= admin_e(admin_url_panel('compras')) ?>" id="formularioCompraProveedor">
        <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
        <input type="hidden" name="token_recepcion" value="<?= admin_e($_SESSION['token_recepcion_compra']) ?>">
        <div class="mb-4"><label class="form-label" for="proveedorCompra">Proveedor</label><select class="form-select" id="proveedorCompra" name="id_proveedor" required>
            <option value="">Seleccionar proveedor</option>
            <?php foreach ($proveedores as $proveedor): ?><option value="<?= (int) $proveedor['id_proveedor'] ?>" <?= $proveedorIngresado === (string) $proveedor['id_proveedor'] ? 'selected' : '' ?>><?= admin_e($proveedor['razon_social']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="mb-3"><label class="form-label" for="sucursalCompra">Sucursal que recibe el stock</label><select class="form-select" id="sucursalCompra" name="ubicacion" required><option value="">Seleccionar sucursal</option><?php foreach ($sucursalesCompra as $local): ?><option value="<?= admin_e($local['ubicacion']) ?>"><?= admin_e($local['nombre']) ?></option><?php endforeach; ?></select></div>
        <div id="lineasCompra" class="d-grid gap-3">
            <?php foreach ($filasFormulario as $fila): ?>
            <div class="fila-compra border rounded p-3"><div class="row g-3 align-items-end">
                <div class="col-lg-5"><label class="form-label d-block">Producto<select name="producto[]" class="form-select mt-2" required><?php compras_opciones_producto($productos, $componentes, $fila['producto']); ?></select></label></div>
                <div class="col-sm-4 col-lg-2"><label class="form-label d-block">Cantidad recibida<input class="form-control mt-2" type="number" name="cantidad[]" min="1" max="1000000" step="1" required value="<?= admin_e($fila['cantidad']) ?>"></label></div>
                <div class="col-sm-5 col-lg-3"><label class="form-label d-block">Costo unitario ($)<input class="form-control mt-2" name="costo[]" inputmode="decimal" pattern="[0-9]{1,10}([.,][0-9]{1,2})?" maxlength="13" required value="<?= admin_e($fila['costo']) ?>"></label></div>
                <div class="col-sm-3 col-lg-2 mb-2"><button class="btn btn-outline-danger" type="button" data-quitar-producto>Quitar</button></div>
            </div></div>
            <?php endforeach; ?>
        </div>
        <div class="d-flex flex-wrap gap-3 align-items-center my-3"><button class="btn btn-outline-primary" type="button" id="agregarProductoCompra">Agregar producto</button><strong id="totalCompra" aria-live="polite">Total: $0,00</strong></div>
        <p class="form-text">El costo es lo que pagaste al proveedor; no cambia el precio de venta. Registrá esta compra una sola vez, cuando recibas los productos.</p>
        <button class="btn btn-primary" type="submit">Confirmar recepción y sumar stock</button>
    </form>
    <?php endif; ?>
</section>
<section class="panel" aria-labelledby="tituloHistorialCompras">
    <h2 class="h5 mb-3" id="tituloHistorialCompras">Compras registradas</h2>
    <?php if (!$compras): ?><p class="mb-0">Todavía no hay compras a proveedores registradas.</p><?php endif; ?>
    <?php foreach ($compras as $compra): ?>
    <details class="border rounded p-3 mb-3" id="compra-<?= (int) $compra['id_orden_compra'] ?>">
        <summary>Compra #<?= (int) $compra['id_orden_compra'] ?> · <?= admin_e($compra['razon_social']) ?> · $<?= number_format((float) $compra['total'], 2, ',', '.') ?> · <?= admin_e($compra['estado'] ?? 'Sin estado') ?></summary>
        <p class="small text-secondary mt-3">Fecha: <?= admin_e($compra['fecha_orden']) ?> · Recibida por: <?= admin_e($compra['nombre'] . ' ' . $compra['apellido']) ?></p>
        <div class="table-responsive"><table class="table"><thead><tr><th>Producto</th><th>Cantidad</th><th>Costo unitario</th><th>Subtotal</th></tr></thead><tbody>
            <?php foreach ($detallesPorCompra[$compra['id_orden_compra']] ?? [] as $detalle): ?><tr><td><?= admin_e($detalle['nombre_producto']) ?></td><td><?= (int) $detalle['cantidad'] ?></td><td>$<?= number_format((float) $detalle['precio_unitario'], 2, ',', '.') ?></td><td>$<?= number_format((int) $detalle['cantidad'] * (float) $detalle['precio_unitario'], 2, ',', '.') ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </details>
    <?php endforeach; ?>
    <nav class="d-flex gap-3" aria-label="Páginas de compras"><?php if ($pagina > 1): ?><a href="<?= admin_e(admin_url_panel('compras')) ?>&pagina=<?= $pagina - 1 ?>#tituloHistorialCompras">Anterior</a><?php endif; ?><span>Página <?= $pagina ?> de <?= $paginas ?></span><?php if ($pagina < $paginas): ?><a href="<?= admin_e(admin_url_panel('compras')) ?>&pagina=<?= $pagina + 1 ?>#tituloHistorialCompras">Siguiente</a><?php endif; ?></nav>
</section>
<script>
(() => {
    const formulario = document.getElementById('formularioCompraProveedor');
    if (!formulario) return;
    const lineas = document.getElementById('lineasCompra');
    const agregar = document.getElementById('agregarProductoCompra');
    const plantilla = lineas.querySelector('.fila-compra').cloneNode(true);
    function actualizar() {
        let total = 0;
        const filas = lineas.querySelectorAll('.fila-compra');
        filas.forEach(fila => {
            total += (Number(fila.querySelector('[name="cantidad[]"]').value) || 0) * (Number(fila.querySelector('[name="costo[]"]').value.replace(',', '.')) || 0);
            fila.querySelector('[data-quitar-producto]').disabled = filas.length === 1;
        });
        document.getElementById('totalCompra').textContent = 'Total: ' + total.toLocaleString('es-AR', {style:'currency', currency:'ARS'});
        agregar.disabled = filas.length >= 50;
    }
    agregar.addEventListener('click', () => {
        if (lineas.children.length >= 50) return;
        const fila = plantilla.cloneNode(true);
        fila.querySelector('select').value = '';
        fila.querySelector('[name="cantidad[]"]').value = '1';
        fila.querySelector('[name="costo[]"]').value = '';
        lineas.appendChild(fila);
        actualizar();
    });
    lineas.addEventListener('click', evento => {
        const quitar = evento.target.closest('[data-quitar-producto]');
        if (quitar && lineas.children.length > 1) { quitar.closest('.fila-compra').remove(); actualizar(); }
    });
    lineas.addEventListener('input', actualizar);
    formulario.addEventListener('submit', () => { formulario.querySelector('[type="submit"]').disabled = true; });
    actualizar();
})();
</script>
<?php $conexion->close(); admin_layout_fin(); ?>
