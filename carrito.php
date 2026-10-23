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

    if ($accion === "eliminar") {
        if (!$idGpu || $idGpu < 1) {
            $_SESSION["mensaje_carrito"] = "No pudimos identificar el producto que querés eliminar.";
            header("Location: carrito.php");
            exit;
        }

        if (empty($_SESSION["usuario_id"])) {
            header("Location: login.php?requerido=carrito");
            exit;
        }

        try {
            require_once __DIR__ . "/conexion.php";
            $usuarioId = (int) $_SESSION["usuario_id"];
            $eliminarDetalle = $conexion->prepare(
                "DELETE d FROM detalle_carrito AS d " .
                "INNER JOIN carrito AS ca ON ca.id_carrito = d.id_carrito " .
                "INNER JOIN cliente AS c ON c.id_cliente = ca.id_cliente " .
                "WHERE c.id_correo = ? AND d.id_gpu = ?"
            );
            $eliminarDetalle->bind_param("ii", $usuarioId, $idGpu);
            $eliminarDetalle->execute();
            $_SESSION["mensaje_carrito"] = $eliminarDetalle->affected_rows > 0
                ? "Producto eliminado del carrito."
                : "Ese producto ya no estaba en tu carrito.";
            $eliminarDetalle->close();
            $conexion->close();
        } catch (Throwable $error) {
            error_log("Error al eliminar GPU del carrito: " . $error->getMessage());
            if (isset($conexion)) {
                try { $conexion->close(); } catch (Throwable $errorCierre) {}
            }
            $_SESSION["mensaje_carrito"] = "No pudimos eliminar el producto. Revisá la conexión con la base de datos e intentá nuevamente.";
        }

        header("Location: carrito.php");
        exit;
    }

    $tipo = (string) ($_POST["tipo"] ?? "agregar");
    $cantidadSolicitada = filter_var($_POST["cantidad"] ?? 1, FILTER_VALIDATE_INT);

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
            $mensajeCarritoError = "No hay suficiente stock para esa cantidad.";
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
        "SELECT d.id_gpu, d.cantidad, g.marca, g.modelo, g.imagen, g.precio_especial, g.pagina, " .
        "COALESCE(SUM(s.cantidad), 0) AS stock " .
        "FROM cliente c " .
        "INNER JOIN carrito ca ON ca.id_cliente = c.id_cliente " .
        "INNER JOIN detalle_carrito d ON d.id_carrito = ca.id_carrito " .
        "INNER JOIN gpu g ON g.id_gpu = d.id_gpu " .
        "LEFT JOIN stock_gpu s ON s.id_gpu = g.id_gpu " .
        "WHERE c.id_correo = ? " .
        "GROUP BY d.id_gpu, d.cantidad, g.marca, g.modelo, g.imagen, g.precio_especial, g.pagina " .
        "ORDER BY g.marca, g.modelo"
    );
    $consultaCarrito->bind_param("i", $usuarioId);
    $consultaCarrito->execute();
    $consultaCarrito->bind_result($idGpu, $cantidadItem, $marcaItem, $modeloItem, $imagenItem, $precioItem, $paginaItem, $stockItem);
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
            "pagina" => (string) ($paginaItem ?? ""),
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
    <link rel="stylesheet" href="style.css?v=20261023-navbar">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="navbar-izquierda">
                <a class="navbar-brand" href="index.php">GPU FIX &amp; STORE</a>
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="productos.php">Productos</a></li>
                    <li class="nav-item"><a class="nav-link" href="reparar.php">Reparar GPU</a></li>
                </ul>
            </div>
            <div class="navbar-derecha">
                <button id="themeButton" class="theme-button" type="button" aria-label="Cambiar tema">☀️</button>
                <?php include __DIR__ . "/menu-perfil.php"; ?>
            </div>
        </nav>
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
                                <p>Cantidad: <?= (int) $item["cantidad"] ?></p>
                                <p><?= $item["stock"] > 0 ? "Hay stock" : "No hay stock" ?></p>
                                <form action="carrito.php" method="post" class="carrito-item-accion">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id_gpu" value="<?= (int) $item["id"] ?>">
                                    <input type="hidden" name="csrf_carrito" value="<?= $escapar($csrfCarrito) ?>">
                                    <button type="submit" class="boton-eliminar-carrito" aria-label="Eliminar <?= $escapar($item["modelo"]) ?> del carrito">Eliminar del carrito</button>
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
                <button type="button" class="boton-comprar">Comprar</button>
                <?php if (!$totalCompleto): ?>
                    <p class="acciones-compra-nota">Hay productos sin precio cargado; el total final queda pendiente.</p>
                <?php endif; ?>
                <a href="productos.php" class="boton-maps">Seguir comprando</a>
            </section>
        <?php endif; ?>
    </main>

    <footer><p>GPU FIX &amp; STORE - Reparación y venta de GPUs</p></footer>
    <script src="script.js?v=20261022-1"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
