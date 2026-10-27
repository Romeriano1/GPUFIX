<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
require_once __DIR__ . '/conexion.php';

function opcionesMarcaProducto(mysqli $conexion): array
{
    $opciones = [];
    $proveedores = $conexion->query("SELECT DISTINCT TRIM(razon_social) AS nombre FROM proveedor WHERE TRIM(razon_social) <> '' ORDER BY nombre");
    while ($proveedor = $proveedores->fetch_assoc()) $opciones[] = $proveedor['nombre'];
    $proveedores->free();
    return $opciones ?: ['Gigabyte', 'Aorus', 'Sapphire', 'ASUS', 'PowerColor', 'INNO3D', 'MSI', 'Zotac'];
}

function buscarMarcaProducto(string $marca, array $opciones): ?string
{
    foreach ($opciones as $opcion) {
        if (mb_strtolower($marca, 'UTF-8') === mb_strtolower($opcion, 'UTF-8')) return $opcion;
    }
    return null;
}

function filasEspecificacionesProducto(string $texto): array
{
    $filas = [];
    foreach (preg_split('/\R|\s*\|\s*(?=[^:|\r\n]{1,100}:)/u', $texto) as $linea) {
        $linea = trim($linea);
        if ($linea === '') continue;
        $partes = explode(':', $linea, 2);
        $filas[] = count($partes) === 2
            ? ['nombre' => trim($partes[0]), 'valor' => trim($partes[1])]
            : ['nombre' => 'Detalle', 'valor' => $linea];
    }
    return $filas;
}

function textoEspecificacionesProducto(array $nombres, array $valores): string
{
    if (count($nombres) !== count($valores) || count($nombres) > 200) {
        throw new RuntimeException('Revisá las filas de especificaciones ingresadas.');
    }
    $lineas = [];
    $valores = array_values($valores);
    foreach (array_values($nombres) as $indice => $nombre) {
        $valor = $valores[$indice];
        if (!is_string($nombre) || !is_string($valor)) throw new RuntimeException('Las especificaciones no son válidas.');
        $nombre = rtrim(trim($nombre), ": \t");
        $valor = trim($valor);
        if ($valor === '') continue;
        if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 100 || mb_strlen($valor, 'UTF-8') > 1000
            || preg_match('/[\r\n:]/', $nombre) || preg_match('/[\r\n]/', $valor)) {
            throw new RuntimeException('Cada especificación necesita una característica de hasta 100 caracteres y un detalle de hasta 1000, en una sola línea.');
        }
        $lineas[] = $nombre . ': ' . $valor;
    }
    $texto = implode("\n", $lineas);
    if ($texto === '' || strlen($texto) > 65535) {
        throw new RuntimeException('Completá al menos una especificación y no superes el tamaño permitido para la ficha.');
    }
    return $texto;
}

function guardarStockProducto(mysqli $conexion, int $idProducto, int $cantidadNueva): void
{
    $consulta = $conexion->prepare('SELECT id_stock_gpu, cantidad FROM stock_gpu WHERE id_gpu = ? ORDER BY id_stock_gpu FOR UPDATE');
    $consulta->bind_param('i', $idProducto);
    $consulta->execute();
    $filas = $consulta->get_result()->fetch_all(MYSQLI_ASSOC);
    $consulta->close();
    if (!$filas) {
        $estado = $cantidadNueva > 0 ? 'Disponible' : 'Sin stock';
        $ubicacion = 'A';
        $crear = $conexion->prepare('INSERT INTO stock_gpu (id_gpu, estado, ubicacion, cantidad) VALUES (?, ?, ?, ?)');
        $crear->bind_param('issi', $idProducto, $estado, $ubicacion, $cantidadNueva);
        $crear->execute();
        $crear->close();
        return;
    }
    $cantidades = array_map(static fn ($fila) => max(0, (int) $fila['cantidad']), $filas);
    $diferencia = $cantidadNueva - array_sum($cantidades);
    if ($diferencia >= 0) {
        $cantidades[0] += $diferencia;
    } else {
        $porQuitar = -$diferencia;
        for ($indice = count($cantidades) - 1; $indice >= 0 && $porQuitar > 0; $indice--) {
            $quitar = min($porQuitar, $cantidades[$indice]);
            $cantidades[$indice] -= $quitar;
            $porQuitar -= $quitar;
        }
    }
    $actualizar = $conexion->prepare('UPDATE stock_gpu SET cantidad = ?, estado = ? WHERE id_stock_gpu = ?');
    foreach ($filas as $indice => $fila) {
        $cantidad = $cantidades[$indice];
        $estado = $cantidad > 0 ? 'Disponible' : 'Sin stock';
        $idStock = (int) $fila['id_stock_gpu'];
        $actualizar->bind_param('isi', $cantidad, $estado, $idStock);
        $actualizar->execute();
    }
    $actualizar->close();
}

$marcasDisponibles = opcionesMarcaProducto($conexion);
$marcaActualProducto = null;
$mensaje = (string) ($_SESSION['mensaje_producto_admin'] ?? '');
unset($_SESSION['mensaje_producto_admin']);
$error = '';
$transaccionActiva = false;
$idProducto = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$modoEdicion = isset($_GET['editar']);
$mostrarFormulario = true;
$datos = ['marca' => '', 'modelo' => '', 'precio' => '', 'imagen' => '', 'cantidad' => '0'];
$filasEspecificaciones = array_map(static fn ($nombre) => ['nombre' => $nombre, 'valor' => ''], [
    'GPU', 'Arquitectura', 'Procesadores de flujo', 'Memoria', 'Interfaz', 'Salidas', 'Fuente recomendada', 'Refrigeración',
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validar_csrf();
    $accion = is_string($_POST['accion'] ?? null) ? $_POST['accion'] : '';
    $modoEdicion = $accion === 'editar';
    $idProducto = filter_input(INPUT_POST, 'id_gpu', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    foreach ($datos as $campo => $valor) $datos[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
    $nombres = is_array($_POST['especificacion_nombre'] ?? null) ? array_values($_POST['especificacion_nombre']) : [];
    $valores = is_array($_POST['especificacion_valor'] ?? null) ? array_values($_POST['especificacion_valor']) : [];
    $filasEspecificaciones = [];
    for ($indice = 0; $indice < min(200, max(count($nombres), count($valores))); $indice++) {
        $filasEspecificaciones[] = [
            'nombre' => is_string($nombres[$indice] ?? null) ? $nombres[$indice] : '',
            'valor' => is_string($valores[$indice] ?? null) ? $valores[$indice] : '',
        ];
    }
    try {
        if (!in_array($accion, ['agregar', 'editar'], true) || ($modoEdicion && !$idProducto)) {
            throw new RuntimeException('Seleccioná un producto válido para guardar los cambios.');
        }
        if ($datos['marca'] === '' || mb_strlen($datos['marca'], 'UTF-8') > 100
            || $datos['modelo'] === '' || mb_strlen($datos['modelo'], 'UTF-8') > 150) {
            throw new RuntimeException('Completá la marca y el modelo del producto.');
        }
        if ($modoEdicion) {
            $consultarMarca = $conexion->prepare('SELECT marca FROM gpu WHERE id_gpu = ? LIMIT 1');
            $consultarMarca->bind_param('i', $idProducto);
            $consultarMarca->execute();
            $marcaActualProducto = $consultarMarca->get_result()->fetch_assoc()['marca'] ?? null;
            $consultarMarca->close();
        }
        $opcionesPermitidas = $marcasDisponibles;
        if ($marcaActualProducto !== null) $opcionesPermitidas[] = $marcaActualProducto;
        $marcaSeleccionada = buscarMarcaProducto($datos['marca'], $opcionesPermitidas);
        if ($marcaSeleccionada === null) throw new RuntimeException('Seleccioná una marca o proveedor de la lista.');
        $datos['marca'] = $marcaSeleccionada;
        $precio = str_replace(',', '.', $datos['precio']);
        if (!preg_match('/^[0-9]{1,12}(?:\.[0-9]{1,2})?$/', $precio)) {
            throw new RuntimeException('Ingresá un precio válido, con hasta dos decimales.');
        }
        $cantidad = filter_var($datos['cantidad'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
        if ($cantidad === false) throw new RuntimeException('El stock debe ser un número entero entre 0 y 2147483647.');
        $esquemaImagen = strtolower((string) parse_url($datos['imagen'], PHP_URL_SCHEME));
        if (mb_strlen($datos['imagen'], 'UTF-8') > 500 || !filter_var($datos['imagen'], FILTER_VALIDATE_URL)
            || !in_array($esquemaImagen, ['http', 'https'], true)) {
            throw new RuntimeException('Ingresá un enlace directo HTTP o HTTPS para la imagen, de hasta 500 caracteres.');
        }
        $especificaciones = textoEspecificacionesProducto($nombres, $valores);
        $conexion->begin_transaction();
        $transaccionActiva = true;
        if ($modoEdicion) {
            $buscar = $conexion->prepare('SELECT id_gpu FROM gpu WHERE id_gpu = ? FOR UPDATE');
            $buscar->bind_param('i', $idProducto);
            $buscar->execute();
            $existe = $buscar->get_result()->fetch_assoc();
            $buscar->close();
            if (!$existe) throw new RuntimeException('El producto seleccionado ya no existe.');
            $guardar = $conexion->prepare('UPDATE gpu SET marca = ?, modelo = ?, precio_especial = ?, especificaciones = ?, imagen = ? WHERE id_gpu = ?');
            $guardar->bind_param('sssssi', $datos['marca'], $datos['modelo'], $precio, $especificaciones, $datos['imagen'], $idProducto);
        } else {
            $guardar = $conexion->prepare('INSERT INTO gpu (marca, modelo, precio_especial, especificaciones, imagen) VALUES (?, ?, ?, ?, ?)');
            $guardar->bind_param('sssss', $datos['marca'], $datos['modelo'], $precio, $especificaciones, $datos['imagen']);
        }
        $guardar->execute();
        if (!$modoEdicion) $idProducto = (int) $conexion->insert_id;
        $guardar->close();
        guardarStockProducto($conexion, $idProducto, $cantidad);
        $conexion->commit();
        $transaccionActiva = false;
        $_SESSION['mensaje_producto_admin'] = $modoEdicion ? 'Ficha del producto actualizada.' : 'Producto agregado al catálogo.';
        header('Location: admin-productos.php?editar=' . $idProducto . '#ficha-producto', true, 303);
        exit;
    } catch (Throwable $fallo) {
        if ($transaccionActiva) $conexion->rollback();
        error_log('Error al guardar producto: ' . $fallo->getMessage());
        $error = $fallo instanceof mysqli_sql_exception ? 'No pudimos guardar el producto. Intentá nuevamente.' : $fallo->getMessage();
    }
} elseif ($modoEdicion) {
    $buscar = $conexion->prepare('SELECT g.*, (SELECT COALESCE(SUM(s.cantidad), 0) FROM stock_gpu s WHERE s.id_gpu = g.id_gpu) AS cantidad FROM gpu g WHERE g.id_gpu = ? LIMIT 1');
    $buscar->bind_param('i', $idProducto);
    $buscar->execute();
    $producto = $buscar->get_result()->fetch_assoc();
    $buscar->close();
    if (!$producto) {
        http_response_code(404);
        $error = 'El producto seleccionado no existe.';
        $mostrarFormulario = false;
    } else {
        $datos = ['marca' => $producto['marca'], 'modelo' => $producto['modelo'], 'precio' => (string) ($producto['precio_especial'] ?? ''), 'imagen' => (string) ($producto['imagen'] ?? ''), 'cantidad' => (string) $producto['cantidad']];
        $marcaActualProducto = $producto['marca'];
        $filasEspecificaciones = filasEspecificacionesProducto((string) ($producto['especificaciones'] ?? ''));
    }
}
if (!$filasEspecificaciones) $filasEspecificaciones[] = ['nombre' => '', 'valor' => ''];
$marcasSelector = $marcasDisponibles;
if ($marcaActualProducto !== null && buscarMarcaProducto($marcaActualProducto, $marcasSelector) === null) {
    $marcasSelector[] = $marcaActualProducto;
}
$catalogo = $conexion->query('SELECT g.id_gpu, g.marca, g.modelo, g.precio_especial, g.imagen, (SELECT COALESCE(SUM(s.cantidad), 0) FROM stock_gpu s WHERE s.id_gpu = g.id_gpu) AS cantidad FROM gpu g ORDER BY g.id_gpu DESC');
admin_layout_inicio('Productos y stock');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
    <h1 class="h3 mb-0">Productos y stock</h1>
    <a class="btn btn-primary" href="admin-productos.php#ficha-producto">Agregar producto</a>
</div>
<p class="text-secondary mt-2">Cargá o editá la ficha completa. Las especificaciones usan el mismo formato del catálogo.</p>
<?php if ($mensaje !== ''): ?><div class="alert alert-success" role="status"><?= admin_e($mensaje) ?></div><?php endif; ?>

<?php if ($mostrarFormulario): ?>
<section class="panel" id="ficha-producto" aria-labelledby="tituloFicha">
    <h2 class="h5 mb-3" id="tituloFicha"><?= $modoEdicion ? 'Editar ficha del producto #' . $idProducto : 'Agregar producto' ?></h2>
    <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
    <form method="post" action="admin-productos.php<?= $modoEdicion ? '?editar=' . $idProducto : '' ?>#ficha-producto" class="row g-3">
        <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
        <input type="hidden" name="accion" value="<?= $modoEdicion ? 'editar' : 'agregar' ?>">
        <input type="hidden" name="id_gpu" value="<?= $idProducto ?>">
        <div class="col-md-4">
            <label for="productoMarca" class="form-label">Marca</label>
            <select id="productoMarca" class="form-select" name="marca" required>
                <option value="" disabled <?= buscarMarcaProducto($datos['marca'], $marcasSelector) === null ? 'selected' : '' ?>>Seleccionar marca</option>
                <?php foreach ($marcasSelector as $marca): ?>
                    <option value="<?= admin_e($marca) ?>" <?= mb_strtolower($datos['marca'], 'UTF-8') === mb_strtolower($marca, 'UTF-8') ? 'selected' : '' ?> <?= mb_strlen($marca, 'UTF-8') > 100 ? 'disabled' : '' ?>><?= admin_e($marca) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-8"><label for="productoModelo" class="form-label">Modelo y nombre del producto</label><input id="productoModelo" class="form-control" name="modelo" maxlength="150" value="<?= admin_e($datos['modelo']) ?>" required></div>
        <div class="col-md-4"><label for="productoPrecio" class="form-label">Precio ($)</label><input id="productoPrecio" class="form-control" name="precio" type="text" inputmode="decimal" maxlength="15" pattern="[0-9]+([.,][0-9]{1,2})?" value="<?= admin_e($datos['precio']) ?>" required></div>
        <div class="col-md-4"><label for="productoCantidad" class="form-label">Stock disponible</label><input id="productoCantidad" class="form-control" name="cantidad" type="number" min="0" max="2147483647" step="1" value="<?= admin_e($datos['cantidad']) ?>" required></div>
        <div class="col-12"><label for="productoImagen" class="form-label">Enlace directo de la imagen</label><input id="productoImagen" class="form-control" name="imagen" type="url" maxlength="500" value="<?= admin_e($datos['imagen']) ?>" required><div class="form-text">Usá el enlace que abre la imagen, no la página de presentación de ImgBB.</div></div>
        <fieldset class="col-12">
            <legend class="h6 mt-2">Especificaciones técnicas</legend>
            <p class="small text-secondary">Cada fila se muestra como <strong>Característica: detalle</strong>. Podés cambiar los nombres, agregar filas y quitar las que no correspondan.</p>
            <div id="filasEspecificaciones" class="d-grid gap-2">
                <?php foreach ($filasEspecificaciones as $indice => $fila): ?>
                    <div class="row g-2 align-items-start" data-especificacion>
                        <div class="col-md-4"><label class="visually-hidden" for="especificacionNombre-<?= $indice ?>">Característica <?= $indice + 1 ?></label><input id="especificacionNombre-<?= $indice ?>" class="form-control" name="especificacion_nombre[]" maxlength="100" value="<?= admin_e($fila['nombre']) ?>"></div>
                        <div class="col-md-6"><label class="visually-hidden" for="especificacionValor-<?= $indice ?>">Detalle <?= $indice + 1 ?></label><input id="especificacionValor-<?= $indice ?>" class="form-control" name="especificacion_valor[]" maxlength="1000" value="<?= admin_e($fila['valor']) ?>"></div>
                        <div class="col-md-2"><button class="btn btn-outline-danger w-100" type="button" data-quitar-especificacion aria-label="Quitar especificación <?= $indice + 1 ?>">Quitar</button></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-3" id="agregarEspecificacion">Agregar especificación</button>
        </fieldset>
        <div class="col-12 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary"><?= $modoEdicion ? 'Guardar ficha' : 'Agregar producto' ?></button>
            <?php if ($modoEdicion): ?><a class="btn btn-outline-secondary" href="producto-gpu.php?id=<?= $idProducto ?>" target="_blank" rel="noopener">Abrir ficha</a><a class="btn btn-outline-secondary" href="admin-productos.php#catalogo-productos">Cancelar edición</a><?php endif; ?>
        </div>
    </form>
</section>
<?php elseif ($error !== ''): ?><div class="alert alert-danger mt-3" role="alert"><?= admin_e($error) ?></div><?php endif; ?>

<section class="panel" id="catalogo-productos" aria-labelledby="tituloCatalogo">
    <h2 class="h5 mb-3" id="tituloCatalogo">Catálogo actual</h2>
    <div class="table-responsive"><table class="table table-striped align-middle">
        <thead><tr><th>ID</th><th>Producto</th><th>Precio</th><th>Stock</th><th>Editar</th></tr></thead>
        <tbody>
        <?php while ($productoCatalogo = $catalogo->fetch_assoc()): ?>
            <tr>
                <td><?= (int) $productoCatalogo['id_gpu'] ?></td>
                <td><a href="producto-gpu.php?id=<?= (int) $productoCatalogo['id_gpu'] ?>" target="_blank" rel="noopener"><?= admin_e($productoCatalogo['marca'] . ' ' . $productoCatalogo['modelo']) ?></a></td>
                <td><?= $productoCatalogo['precio_especial'] === null ? 'Sin precio' : '$ ' . number_format((float) $productoCatalogo['precio_especial'], 2, ',', '.') ?></td>
                <td><?= (int) $productoCatalogo['cantidad'] ?></td>
                <td><a class="btn btn-sm btn-outline-primary" href="admin-productos.php?editar=<?= (int) $productoCatalogo['id_gpu'] ?>#ficha-producto">Editar ficha</a></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table></div>
</section>
<template id="plantillaEspecificacion">
    <div class="row g-2 align-items-start" data-especificacion>
        <div class="col-md-4"><label class="visually-hidden" data-etiqueta-nombre></label><input class="form-control" name="especificacion_nombre[]" maxlength="100"></div>
        <div class="col-md-6"><label class="visually-hidden" data-etiqueta-valor></label><input class="form-control" name="especificacion_valor[]" maxlength="1000"></div>
        <div class="col-md-2"><button class="btn btn-outline-danger w-100" type="button" data-quitar-especificacion>Quitar</button></div>
    </div>
</template>
<script>
(function () {
    const filas = document.getElementById('filasEspecificaciones');
    const agregar = document.getElementById('agregarEspecificacion');
    const plantilla = document.getElementById('plantillaEspecificacion');
    if (!filas || !agregar || !plantilla) return;
    let siguiente = filas.querySelectorAll('[data-especificacion]').length;
    agregar.addEventListener('click', function () {
        if (filas.querySelectorAll('[data-especificacion]').length >= 200) return;
        const contenido = plantilla.content.cloneNode(true);
        const nombre = contenido.querySelector('[name="especificacion_nombre[]"]');
        const valor = contenido.querySelector('[name="especificacion_valor[]"]');
        const etiquetaNombre = contenido.querySelector('[data-etiqueta-nombre]');
        const etiquetaValor = contenido.querySelector('[data-etiqueta-valor]');
        nombre.id = 'especificacionNombre-' + siguiente;
        valor.id = 'especificacionValor-' + siguiente;
        etiquetaNombre.htmlFor = nombre.id;
        etiquetaNombre.textContent = 'Característica ' + (siguiente + 1);
        etiquetaValor.htmlFor = valor.id;
        etiquetaValor.textContent = 'Detalle ' + (siguiente + 1);
        contenido.querySelector('[data-quitar-especificacion]').setAttribute('aria-label', 'Quitar especificación ' + (siguiente + 1));
        siguiente++;
        filas.appendChild(contenido);
        nombre.focus();
    });
    filas.addEventListener('click', function (evento) {
        const boton = evento.target.closest('[data-quitar-especificacion]');
        if (!boton) return;
        const fila = boton.closest('[data-especificacion]');
        if (filas.querySelectorAll('[data-especificacion]').length === 1) {
            fila.querySelectorAll('input').forEach(function (campo) { campo.value = ''; });
        } else {
            fila.remove();
        }
    });
})();
</script>
<?php
$catalogo->free();
$conexion->close();
admin_layout_fin();
