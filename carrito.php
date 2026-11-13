<?php
session_start();

if (empty($_SESSION["csrf_carrito"])) {
    $_SESSION["csrf_carrito"] = bin2hex(random_bytes(32));
}
$csrfCarrito = $_SESSION["csrf_carrito"];
$mensajeCarrito = (string) ($_SESSION["mensaje_carrito"] ?? "");
unset($_SESSION["mensaje_carrito"]);

$escapar = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
$formatearPrecio = static fn ($precio): string => $precio === null || $precio === ""
    ? "Consultar precio"
    : "$ " . number_format((float) $precio, 2, ",", ".");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tokenEnviado = (string) ($_POST["csrf_carrito"] ?? "");
    if (!hash_equals($csrfCarrito, $tokenEnviado)) {
        $_SESSION["mensaje_carrito"] = "La solicitud venció. Volvé a intentarlo.";
        header("Location: carrito.php");
        exit;
    }

    $accion = (string) ($_POST["accion"] ?? "");
    $idGpu = filter_var($_POST["id_gpu"] ?? null, FILTER_VALIDATE_INT);

    if (in_array($accion, ['actualizar', 'quitar', 'eliminar'], true)) {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: login.php?requerido=carrito');
            exit;
        }
        $cantidadQuitar = filter_var($_POST['cantidad_quitar'] ?? null, FILTER_VALIDATE_INT);
        $cantidadElegida = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT);
        if (!$idGpu || $idGpu < 1 || ($accion === 'quitar' && (!$cantidadQuitar || $cantidadQuitar < 1))
            || ($accion === 'actualizar' && ($cantidadElegida === false || $cantidadElegida < 0))) {
            $_SESSION['mensaje_carrito'] = 'Seleccioná un producto y una cantidad válida.';
            header('Location: carrito.php', true, 303);
            exit;
        }
        $transaccionActiva = false;
        try {
            require_once __DIR__ . '/conexion.php';
            $conexion->begin_transaction();
            $transaccionActiva = true;
            $usuarioId = (int) $_SESSION['usuario_id'];
            $buscarCarrito = $conexion->prepare('SELECT ca.id_carrito FROM carrito ca INNER JOIN cliente c ON c.id_cliente=ca.id_cliente WHERE c.id_correo=? FOR UPDATE');
            $buscarCarrito->bind_param('i', $usuarioId);
            $buscarCarrito->execute();
            $carritoPropio = $buscarCarrito->get_result()->fetch_assoc();
            if (!$carritoPropio) throw new RuntimeException('Tu carrito ya no está disponible.');
            $idCarrito = (int) $carritoPropio['id_carrito'];
            if ($accion === 'actualizar') {
                $buscarStock = $conexion->prepare('SELECT cantidad FROM stock_gpu WHERE id_gpu=? FOR UPDATE');
                $buscarStock->bind_param('i', $idGpu);
                $buscarStock->execute();
                $stockDisponible = 0;
                foreach ($buscarStock->get_result() as $stock) $stockDisponible += max(0, (int) $stock['cantidad']);
                $buscarStock->close();
            }
            $buscarDetalle = $conexion->prepare('SELECT id_detalle_carrito,cantidad FROM detalle_carrito WHERE id_carrito=? AND id_gpu=? FOR UPDATE');
            $buscarDetalle->bind_param('ii', $idCarrito, $idGpu);
            $buscarDetalle->execute();
            $detalle = $buscarDetalle->get_result()->fetch_assoc();
            if (!$detalle) throw new RuntimeException('Ese producto ya no está en tu carrito.');
            $cantidadActual = (int) $detalle['cantidad'];
            if ($accion === 'quitar' && $cantidadQuitar > $cantidadActual) throw new RuntimeException('La cantidad del carrito cambió. Podés quitar hasta ' . $cantidadActual . ' unidades.');
            $cantidadRestante = $accion === 'actualizar' ? (int) $cantidadElegida : ($accion === 'eliminar' ? 0 : $cantidadActual - $cantidadQuitar);
            if ($accion === 'actualizar' && $cantidadRestante > $cantidadActual && $cantidadRestante > $stockDisponible) {
                throw new RuntimeException('No hay stock suficiente para esa cantidad. El carrito conserva la cantidad anterior.');
            }
            $idDetalle = (int) $detalle['id_detalle_carrito'];
            if ($cantidadRestante === 0) {
                $guardar = $conexion->prepare('DELETE FROM detalle_carrito WHERE id_detalle_carrito=?');
                $guardar->bind_param('i', $idDetalle);
            } else {
                $guardar = $conexion->prepare('UPDATE detalle_carrito SET cantidad=? WHERE id_detalle_carrito=?');
                $guardar->bind_param('ii', $cantidadRestante, $idDetalle);
            }
            $guardar->execute();
            $conexion->commit();
            $transaccionActiva = false;
            $_SESSION['mensaje_carrito'] = $cantidadRestante === 0 ? 'Producto quitado del carrito.' : 'Cantidad actualizada a ' . $cantidadRestante . ' unidades.';
        } catch (Throwable $error) {
            if ($transaccionActiva) $conexion->rollback();
            error_log('Error al quitar unidades del carrito: ' . $error->getMessage());
            $_SESSION['mensaje_carrito'] = $error instanceof mysqli_sql_exception ? 'No pudimos actualizar el carrito. Intentá nuevamente.' : $error->getMessage();
        }
        header('Location: carrito.php', true, 303);
        exit;
    }

    $tipo = (string) ($_POST["tipo"] ?? "agregar");
    $cantidadSolicitada = filter_var($_POST["cantidad"] ?? null, FILTER_VALIDATE_INT);

    if ($accion !== "agregar" || !in_array($tipo, ["agregar", "comprar"], true) || !$idGpu || !$cantidadSolicitada || $cantidadSolicitada < 1) {
        $_SESSION["mensaje_carrito"] = "No pudimos identificar el producto o la cantidad.";
        header("Location: carrito.php");
        exit;
    }

    if (empty($_SESSION["usuario_id"])) {
        $_SESSION["carrito_pendiente"] = [
            "id_gpu" => (int) $idGpu,
            "cantidad" => (int) $cantidadSolicitada,
            "tipo" => $tipo,
        ];
        header("Location: login.php?requerido=carrito");
        exit;
    }

    $transaccionActiva = false;
    try {
        require_once __DIR__ . "/conexion.php";
        $conexion->begin_transaction();
        $transaccionActiva = true;

        $usuarioId = (int) $_SESSION["usuario_id"];
        $consultaCliente = $conexion->prepare("SELECT id_cliente FROM cliente WHERE id_correo = ? LIMIT 1");
        $consultaCliente->bind_param("i", $usuarioId);
        $consultaCliente->execute();
        $consultaCliente->bind_result($idCliente);
        $clienteEncontrado = $consultaCliente->fetch();
        $consultaCliente->close();

        if (!$clienteEncontrado) {
            throw new RuntimeException("cliente_no_encontrado");
        }

        $crearCarrito = $conexion->prepare(
            "INSERT INTO carrito (id_cliente) VALUES (?) ON DUPLICATE KEY UPDATE id_carrito = LAST_INSERT_ID(id_carrito)"
        );
        $crearCarrito->bind_param("i", $idCliente);
        $crearCarrito->execute();
        $idCarrito = (int) $conexion->insert_id;
        $crearCarrito->close();

        if ($idCarrito < 1) {
            throw new RuntimeException("carrito_no_disponible");
        }

        $consultaStock = $conexion->prepare("SELECT cantidad FROM stock_gpu WHERE id_gpu = ? FOR UPDATE");
        $consultaStock->bind_param("i", $idGpu);
        $consultaStock->execute();
        $consultaStock->bind_result($cantidadEnStock);
        $stockTotal = 0;
        $registrosStock = 0;
        while ($consultaStock->fetch()) {
            $stockTotal += max(0, (int) $cantidadEnStock);
            $registrosStock++;
        }
        $consultaStock->close();

        if ($registrosStock === 0 || $stockTotal < 1) {
            throw new RuntimeException("sin_stock");
        }

        $consultaDetalle = $conexion->prepare(
            "SELECT cantidad FROM detalle_carrito WHERE id_carrito = ? AND id_gpu = ? LIMIT 1 FOR UPDATE"
        );
        $consultaDetalle->bind_param("ii", $idCarrito, $idGpu);
        $consultaDetalle->execute();
        $consultaDetalle->bind_result($cantidadEnCarrito);
        $detalleExiste = $consultaDetalle->fetch();
        $consultaDetalle->close();

        $cantidadFinal = ($detalleExiste ? (int) $cantidadEnCarrito : 0) + (int) $cantidadSolicitada;
        if ($cantidadFinal > $stockTotal) {
            throw new RuntimeException("stock_insuficiente");
        }

        if ($detalleExiste) {
            $actualizarDetalle = $conexion->prepare(
                "UPDATE detalle_carrito SET cantidad = ? WHERE id_carrito = ? AND id_gpu = ?"
            );
            $actualizarDetalle->bind_param("iii", $cantidadFinal, $idCarrito, $idGpu);
            $actualizarDetalle->execute();
            $actualizarDetalle->close();
        } else {
            $agregarDetalle = $conexion->prepare(
                "INSERT INTO detalle_carrito (id_carrito, id_gpu, cantidad) VALUES (?, ?, ?)"
            );
            $agregarDetalle->bind_param("iii", $idCarrito, $idGpu, $cantidadSolicitada);
            $agregarDetalle->execute();
            $agregarDetalle->close();
        }

        $conexion->commit();
        $transaccionActiva = false;
        unset($_SESSION["carrito_pendiente"]);
        $_SESSION["mensaje_carrito"] = $tipo === "comprar"
            ? "Producto agregado. Revisá tu carrito para continuar con la compra."
            : "Producto agregado al carrito.";
        $conexion->close();
    } catch (Throwable $error) {
        if (isset($conexion)) {
            try {
                if ($transaccionActiva) {
                    $conexion->rollback();
                }
                $conexion->close();
            } catch (Throwable $errorRollback) {
                // La conexión o la transacción ya pueden haberse cerrado.
            }
        }

        error_log("Error al agregar GPU al carrito: " . $error->getMessage());
        unset($_SESSION["carrito_pendiente"]);
        $mensajeCarritoError = "No pudimos agregar el producto. Revisá la conexión con la base de datos e intentá nuevamente.";
        if ($error->getMessage() === "sin_stock") {
            $mensajeCarritoError = "Este producto no tiene stock disponible.";
        } elseif ($error->getMessage() === "stock_insuficiente") {
            $cantidadActualCarrito = $detalleExiste ? (int) $cantidadEnCarrito : 0;
            $cantidadAdicionalDisponible = max(0, $stockTotal - $cantidadActualCarrito);
            $mensajeCarritoError = 'No se agregó el producto. Ya tenés ' . $cantidadActualCarrito . ' unidades en el carrito. '
                . ($cantidadAdicionalDisponible > 0 ? 'Podés agregar hasta ' . $cantidadAdicionalDisponible . ' más.' : 'No podés agregar más unidades con el stock actual.');
        } elseif ($error->getMessage() === "cliente_no_encontrado") {
            $mensajeCarritoError = "No encontramos el perfil de cliente asociado a tu cuenta.";
        }
        $_SESSION["mensaje_carrito"] = $mensajeCarritoError;
    }

    header("Location: carrito.php");
    exit;
}

if (!empty($_SESSION["usuario_id"]) && !empty($_SESSION["carrito_pendiente"]) && !isset($_GET["continuar_pendiente"])) {
    header("Location: carrito.php?continuar_pendiente=1");
    exit;
}

if (isset($_GET["continuar_pendiente"])) {
    $pendiente = $_SESSION["carrito_pendiente"] ?? null;
    if (empty($_SESSION["usuario_id"])) {
        header("Location: login.php?requerido=carrito");
        exit;
    }
    if (!is_array($pendiente) || empty($pendiente["id_gpu"]) || empty($pendiente["cantidad"])) {
        unset($_SESSION["carrito_pendiente"]);
        header("Location: carrito.php");
        exit;
    }

    $tipoPendiente = in_array(($pendiente["tipo"] ?? ""), ["agregar", "comprar"], true)
        ? $pendiente["tipo"]
        : "agregar";
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Continuando al carrito - GPU FIX &amp; STORE</title>
    </head>
    <body>
        <p>Agregando el producto a tu carrito…</p>
        <form id="continuarCarrito" action="carrito.php" method="post">
            <input type="hidden" name="accion" value="agregar">
            <input type="hidden" name="id_gpu" value="<?= (int) $pendiente["id_gpu"] ?>">
            <input type="hidden" name="cantidad" value="<?= (int) $pendiente["cantidad"] ?>">
            <input type="hidden" name="tipo" value="<?= $escapar($tipoPendiente) ?>">
            <input type="hidden" name="csrf_carrito" value="<?= $escapar($csrfCarrito) ?>">
            <button type="submit">Continuar</button>
        </form>
        <script>document.getElementById("continuarCarrito").submit();</script>
    </body>
    </html>
    <?php
    exit;
}

if (empty($_SESSION["usuario_id"])) {
    header("Location: login.php?requerido=carrito");
    exit;
}

$itemsCarrito = [];
$errorCarrito = false;
$totalCarrito = 0.0;
$totalCompleto = true;
try {
    require_once __DIR__ . "/conexion.php";
    $usuarioId = (int) $_SESSION["usuario_id"];
    $consultaCarrito = $conexion->prepare(
        "SELECT d.id_gpu, d.cantidad, g.marca, g.modelo, g.imagen, g.precio_especial, " .
        "COALESCE(SUM(s.cantidad), 0) AS stock " .
        "FROM cliente c " .
        "INNER JOIN carrito ca ON ca.id_cliente = c.id_cliente " .
        "INNER JOIN detalle_carrito d ON d.id_carrito = ca.id_carrito " .
        "INNER JOIN gpu g ON g.id_gpu = d.id_gpu " .
        "LEFT JOIN stock_gpu s ON s.id_gpu = g.id_gpu " .
        "WHERE c.id_correo = ? " .
        "GROUP BY d.id_gpu, d.cantidad, g.marca, g.modelo, g.imagen, g.precio_especial " .
        "ORDER BY g.marca, g.modelo"
    );
    $consultaCarrito->bind_param("i", $usuarioId);
    $consultaCarrito->execute();
    $consultaCarrito->bind_result($idGpu, $cantidadItem, $marcaItem, $modeloItem, $imagenItem, $precioItem, $stockItem);
    while ($consultaCarrito->fetch()) {
        $precioNumerico = $precioItem === null || $precioItem === "" ? null : (float) $precioItem;
        $subtotal = $precioNumerico === null ? null : $precioNumerico * (int) $cantidadItem;
        if ($subtotal === null) {
            $totalCompleto = false;
        } else {
            $totalCarrito += $subtotal;
        }
        $itemsCarrito[] = [
            "id" => (int) $idGpu,
            "cantidad" => (int) $cantidadItem,
            "marca" => (string) $marcaItem,
            "modelo" => (string) $modeloItem,
            "imagen" => (string) ($imagenItem ?? ""),
            "pagina" => "producto-gpu.php?id=" . (int) $idGpu,
            "precio" => $precioItem,
            "subtotal" => $subtotal,
            "stock" => max(0, (int) $stockItem),
        ];
    }
    $consultaCarrito->close();
    $conexion->close();
} catch (Throwable $error) {
    $errorCarrito = true;
    error_log("Error al cargar el carrito: " . $error->getMessage());
    if (isset($conexion)) {
        try { $conexion->close(); } catch (Throwable $errorCierre) {}
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito - GPU FIX &amp; STORE</title>
    <link rel="stylesheet" href="style.css?v=20261113-cantidad">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <header>
        <?php include __DIR__ . "/navbar.php"; ?>
    </header>

    <main>
        <?php if ($mensajeCarrito !== ""): ?>
            <p class="mensaje-carrito" role="status"><?= $escapar($mensajeCarrito) ?></p>
        <?php endif; ?>

        <?php if ($errorCarrito): ?>
            <section class="carrito-vacio">
                <h2>No se pudo cargar el carrito</h2>
                <p>Revisá la conexión con la base de datos e intentá nuevamente.</p>
            </section>
        <?php elseif (!$itemsCarrito): ?>
            <section class="carrito-vacio">
                <h2>Tu carrito está vacío</h2>
                <p>Todavía no agregaste ningún producto a tu carrito.</p>
                <p>Explorá nuestros productos y encontrá la GPU que estás buscando.</p>
                <a href="productos.php" class="boton-maps">Ver productos</a>
            </section>
        <?php else: ?>
            <section class="carrito-contenido">
                <h2>Tu carrito</h2>
                <div class="carrito-lista">
                    <?php foreach ($itemsCarrito as $item): ?>
                        <article class="carrito-item">
                            <?php if ($item["imagen"] !== ""): ?>
                                <a href="<?= $escapar($item["pagina"]) ?>" class="carrito-item-imagen">
                                    <img src="<?= $escapar($item["imagen"]) ?>" alt="<?= $escapar($item["modelo"]) ?>">
                                </a>
                            <?php endif; ?>
                            <div class="carrito-item-detalle">
                                <p class="producto-marca"><?= $escapar($item["marca"]) ?></p>
                                <h3><?= $escapar($item["modelo"]) ?></h3>
                                <p><?= $item["stock"] > 0 ? "Hay stock" : "No hay stock" ?></p>
                                <form action="carrito.php" method="post" class="carrito-item-accion">
                                    <input type="hidden" name="id_gpu" value="<?= (int) $item["id"] ?>">
                                    <input type="hidden" name="csrf_carrito" value="<?= $escapar($csrfCarrito) ?>">
                                    <input type="hidden" name="accion" value="actualizar">
                                    <label class="carrito-cantidad-label" for="cantidad-<?= (int)$item['id'] ?>">Cantidad</label>
                                    <div class="selector-cantidad" data-carrito-cantidad>
                                        <button type="button" data-cambio="-1" aria-label="Reducir cantidad">−</button>
                                        <input id="cantidad-<?= (int)$item['id'] ?>" type="number" name="cantidad" value="<?= (int)$item['cantidad'] ?>" min="0" max="<?= max((int)$item['stock'], (int)$item['cantidad']) ?>" step="1" required>
                                        <button type="button" data-cambio="1" aria-label="Aumentar cantidad">+</button>
                                    </div>
                                </form>
                            </div>
                            <div class="carrito-item-precio">
                                <span>Precio unitario</span>
                                <strong><?= $escapar($formatearPrecio($item["precio"])) ?></strong>
                                <span>Subtotal</span>
                                <strong><?= $item["subtotal"] === null ? "Consultar precio" : $escapar($formatearPrecio($item["subtotal"])) ?></strong>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="carrito-resumen">
                    <span>Total<?= $totalCompleto ? "" : " parcial" ?></span>
                    <strong><?= $escapar($formatearPrecio($totalCarrito)) ?></strong>
                </div>
                <div class="carrito-acciones">
                    <a href="pagar.php" class="boton-comprar">Comprar</a>
                    <a href="productos.php" class="boton-maps">Seguir comprando</a>
                </div>
                <?php if (!$totalCompleto): ?>
                    <p class="acciones-compra-nota">Hay productos sin precio cargado; el total final queda pendiente.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <footer><p>GPU FIX &amp; STORE - Reparación y venta de GPUs</p></footer>
    <script src="script.js?v=20261113-cantidad-directa"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
