<?php
session_start();

if (empty($_SESSION["usuario_id"])) {
    header("Location: login.php?requerido=carrito");
    exit;
}

$usuarioId = (int) $_SESSION["usuario_id"];
$errorPago = (string) ($_SESSION["error_pago"] ?? "");
$direccionFacturacion = (string) ($_SESSION["direccion_pago"] ?? "");
unset($_SESSION["error_pago"], $_SESSION["direccion_pago"]);
if (empty($_SESSION["csrf_pago"])) {
    $_SESSION["csrf_pago"] = bin2hex(random_bytes(32));
}
$csrfPago = $_SESSION["csrf_pago"];
$datosCliente = null;
$itemsPago = [];
$compraConfirmada = null;
$totalCentavos = 0;
$errorCarga = false;
$transaccionActiva = false;
$problemaCarrito = "";
$idCompra = filter_input(INPUT_GET, "compra", FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) ?: 0;

function escaparPago($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function centavosPago($importe): ?int
{
    if (!is_string($importe) || !preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/', $importe, $partes)) {
        return null;
    }
    return (int) $partes[1] * 100 + (int) str_pad($partes[2] ?? "", 2, "0");
}

function decimalPago(int $centavos): string
{
    return intdiv($centavos, 100) . "." . str_pad((string) ($centavos % 100), 2, "0", STR_PAD_LEFT);
}

function formatoPago(int $centavos): string
{
    return "$ " . number_format($centavos / 100, 2, ",", ".");
}

function articulosPago(mysqli $conexion, int $idCarrito, bool $bloquear = false): array
{
    $consulta = $conexion->prepare(
        "SELECT d.id_gpu, d.cantidad, g.marca, g.modelo, g.imagen, g.precio_especial, " .
        "(SELECT COALESCE(SUM(s.cantidad), 0) FROM stock_gpu s WHERE s.id_gpu = g.id_gpu) AS stock " .
        "FROM detalle_carrito d INNER JOIN gpu g ON g.id_gpu = d.id_gpu " .
        "WHERE d.id_carrito = ? ORDER BY d.id_gpu" . ($bloquear ? " FOR UPDATE" : "")
    );
    $consulta->bind_param("i", $idCarrito);
    $consulta->execute();
    $articulos = $consulta->get_result()->fetch_all(MYSQLI_ASSOC);
    $consulta->close();
    return $articulos;
}

function resumenPago(array $articulos): string
{
    return hash("sha256", json_encode(array_map(static fn ($item) => [
        (int) $item["id_gpu"], (int) $item["cantidad"], (string) $item["precio_especial"],
    ], $articulos), JSON_THROW_ON_ERROR));
}

function validarArticulosPago(array $articulos): int
{
    if (!$articulos) {
        throw new RuntimeException("Tu carrito está vacío. Agregá un producto para continuar.");
    }
    $total = 0;
    foreach ($articulos as $item) {
        $precio = centavosPago($item["precio_especial"]);
        $cantidad = (int) $item["cantidad"];
        if ($precio === null || $precio <= 0) {
            throw new RuntimeException("Hay un producto sin un precio válido. Volvé al carrito para revisarlo.");
        }
        if ($cantidad < 1 || $cantidad > (int) $item["stock"]) {
            throw new RuntimeException("La cantidad de " . $item["modelo"] . " supera el stock disponible. Revisá tu carrito.");
        }
        if ($cantidad > intdiv(999999999999 - $total, $precio)) {
            throw new RuntimeException("El importe de esta compra supera el máximo permitido.");
        }
        $total += $precio * $cantidad;
    }
    return $total;
}

try {
    require_once __DIR__ . "/conexion.php";
    $consultaCliente = $conexion->prepare(
        "SELECT c.id_cliente, c.direccion, u.nombre, u.apellido, u.correo, u.dni, u.telefono " .
        "FROM cliente c INNER JOIN usuarios u ON u.id_correo = c.id_correo WHERE u.id_correo = ? LIMIT 1"
    );
    $consultaCliente->bind_param("i", $usuarioId);
    $consultaCliente->execute();
    $datosCliente = $consultaCliente->get_result()->fetch_assoc();
    $consultaCliente->close();
    if (!$datosCliente) {
        header("Location: cerrar-sesion.php");
        exit;
    }
    $idCliente = (int) $datosCliente["id_cliente"];
    if ($direccionFacturacion === "") {
        $direccionFacturacion = (string) ($datosCliente["direccion"] ?? "");
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $direccionEnviada = $_POST["direccion"] ?? "";
        $direccionFacturacion = is_string($direccionEnviada) ? trim($direccionEnviada) : "";
        try {
            $csrfEnviado = $_POST["csrf_pago"] ?? null;
            if (!is_string($csrfEnviado) || !hash_equals($csrfPago, $csrfEnviado)) {
                throw new RuntimeException("La solicitud venció. Revisá el resumen y volvé a confirmar.");
            }
            if (mb_strlen($direccionFacturacion, "UTF-8") < 8 || mb_strlen($direccionFacturacion, "UTF-8") > 255) {
                throw new RuntimeException("Ingresá una dirección de facturación de entre 8 y 255 caracteres.");
            }
            if (!preg_match('/^[0-9]{7,8}$/', (string) $datosCliente["dni"])) {
                throw new RuntimeException("Tu cuenta necesita un DNI válido para completar la compra.");
            }
            $conexion->begin_transaction();
            $transaccionActiva = true;
            $consultaCarrito = $conexion->prepare("SELECT id_carrito FROM carrito WHERE id_cliente = ? FOR UPDATE");
            $consultaCarrito->bind_param("i", $idCliente);
            $consultaCarrito->execute();
            $carrito = $consultaCarrito->get_result()->fetch_assoc();
            $consultaCarrito->close();
            $idCarrito = (int) ($carrito["id_carrito"] ?? 0);
            $itemsPago = articulosPago($conexion, $idCarrito, true);
            $totalCentavos = validarArticulosPago($itemsPago);
            $resumenEnviado = $_POST["resumen_pago"] ?? null;
            if (!is_string($resumenEnviado) || !hash_equals((string) ($_SESSION["resumen_pago"] ?? ""), $resumenEnviado)
                || !hash_equals(resumenPago($itemsPago), $resumenEnviado)) {
                throw new RuntimeException("Cambió el carrito o alguno de sus precios. Revisá el resumen actualizado antes de confirmar.");
            }

            $stockPorProducto = [];
            foreach ($itemsPago as $item) {
                $idGpu = (int) $item["id_gpu"];
                $consultaStock = $conexion->prepare("SELECT id_stock_gpu, cantidad FROM stock_gpu WHERE id_gpu = ? ORDER BY id_stock_gpu FOR UPDATE");
                $consultaStock->bind_param("i", $idGpu);
                $consultaStock->execute();
                $filasStock = $consultaStock->get_result()->fetch_all(MYSQLI_ASSOC);
                $consultaStock->close();
                $stockDisponible = array_sum(array_map(static fn ($fila) => max(0, (int) $fila["cantidad"]), $filasStock));
                if ($stockDisponible < (int) $item["cantidad"]) {
                    throw new RuntimeException("Ya no hay suficiente stock de " . $item["modelo"] . ". Revisá tu carrito.");
                }
                $stockPorProducto[$idGpu] = $filasStock;
            }

            $totalVenta = decimalPago($totalCentavos);
            $crearVenta = $conexion->prepare("INSERT INTO gpu_ventas (id_cliente, precio_total, metodo_pago, estado) VALUES (?, ?, 'Simulacion', 'Simulada')");
            $crearVenta->bind_param("is", $idCliente, $totalVenta);
            $crearVenta->execute();
            $idVenta = (int) $conexion->insert_id;
            $crearVenta->close();
            $numeroComprobante = "SIM-" . str_pad((string) $idVenta, 8, "0", STR_PAD_LEFT);
            $crearFactura = $conexion->prepare("INSERT INTO factura (id_cliente, id_gpu_ventas, numero_factura, total) VALUES (?, ?, ?, ?)");
            $crearFactura->bind_param("iiss", $idCliente, $idVenta, $numeroComprobante, $totalVenta);
            $crearFactura->execute();
            $idFactura = (int) $conexion->insert_id;
            $crearFactura->close();
            foreach ($itemsPago as $item) {
                $idGpu = (int) $item["id_gpu"];
                $cantidad = (int) $item["cantidad"];
                $precioUnitario = decimalPago(centavosPago($item["precio_especial"]));
                $subtotal = decimalPago(centavosPago($item["precio_especial"]) * $cantidad);
                $descripcion = $item["marca"] . " " . $item["modelo"];
                $crearDetalle = $conexion->prepare("INSERT INTO detalle_venta (id_gpu_ventas, id_gpu, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
                $crearDetalle->bind_param("iiiss", $idVenta, $idGpu, $cantidad, $precioUnitario, $subtotal);
                $crearDetalle->execute();
                $crearDetalle->close();
                $crearDetalleFactura = $conexion->prepare("INSERT INTO detalle_factura (id_factura, descripcion, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
                $crearDetalleFactura->bind_param("isiss", $idFactura, $descripcion, $cantidad, $precioUnitario, $subtotal);
                $crearDetalleFactura->execute();
                $crearDetalleFactura->close();
                $cantidadPendiente = $cantidad;
                foreach ($stockPorProducto[$idGpu] as $filaStock) {
                    if ($cantidadPendiente === 0) break;
                    $cantidadAnterior = max(0, (int) $filaStock["cantidad"]);
                    $descuento = min($cantidadPendiente, $cantidadAnterior);
                    if ($descuento === 0) continue;
                    $cantidadRestante = $cantidadAnterior - $descuento;
                    $estadoStock = $cantidadRestante === 0 ? "Sin stock" : "Disponible";
                    $idStock = (int) $filaStock["id_stock_gpu"];
                    $descontarStock = $conexion->prepare("UPDATE stock_gpu SET cantidad = ?, estado = ? WHERE id_stock_gpu = ?");
                    $descontarStock->bind_param("isi", $cantidadRestante, $estadoStock, $idStock);
                    $descontarStock->execute();
                    $descontarStock->close();
                    $cantidadPendiente -= $descuento;
                }
            }
            $guardarDireccion = $conexion->prepare("UPDATE cliente SET direccion = ? WHERE id_cliente = ?");
            $guardarDireccion->bind_param("si", $direccionFacturacion, $idCliente);
            $guardarDireccion->execute();
            $guardarDireccion->close();
            $vaciarCarrito = $conexion->prepare("DELETE FROM detalle_carrito WHERE id_carrito = ?");
            $vaciarCarrito->bind_param("i", $idCarrito);
            $vaciarCarrito->execute();
            $vaciarCarrito->close();
            $actualizarCarrito = $conexion->prepare("UPDATE carrito SET fecha_actualizacion = NOW() WHERE id_carrito = ?");
            $actualizarCarrito->bind_param("i", $idCarrito);
            $actualizarCarrito->execute();
            $actualizarCarrito->close();
            $conexion->commit();
            $transaccionActiva = false;
            unset($_SESSION["csrf_pago"], $_SESSION["resumen_pago"]);
            header("Location: pagar.php?compra=" . $idVenta, true, 303);
            exit;
        } catch (Throwable $error) {
            if ($transaccionActiva) {
                $conexion->rollback();
                $transaccionActiva = false;
            }
            error_log("Error al confirmar compra simulada: " . $error->getMessage());
            $_SESSION["error_pago"] = $error instanceof mysqli_sql_exception
                ? "No pudimos guardar la compra. Intentá nuevamente."
                : ($error instanceof RuntimeException ? $error->getMessage() : "No pudimos completar la compra. Intentá nuevamente.");
            $_SESSION["direccion_pago"] = $direccionFacturacion;
            header("Location: pagar.php", true, 303);
            exit;
        }
    }

    if ($idCompra > 0) {
        $consultaCompra = $conexion->prepare(
            "SELECT f.id_factura, f.numero_factura, f.fecha_emision, f.total FROM factura f " .
            "INNER JOIN gpu_ventas v ON v.id_gpu_ventas = f.id_gpu_ventas " .
            "WHERE v.id_gpu_ventas = ? AND v.id_cliente = ? AND f.id_cliente = ? AND v.estado = 'Simulada' LIMIT 1"
        );
        $consultaCompra->bind_param("iii", $idCompra, $idCliente, $idCliente);
        $consultaCompra->execute();
        $compraConfirmada = $consultaCompra->get_result()->fetch_assoc();
        $consultaCompra->close();
        if (!$compraConfirmada) {
            http_response_code(404);
            $errorPago = "No encontramos esa compra en tu cuenta.";
        } else {
            $idFactura = (int) $compraConfirmada["id_factura"];
            $consultaDetalles = $conexion->prepare("SELECT descripcion, cantidad, precio_unitario, subtotal FROM detalle_factura WHERE id_factura = ? ORDER BY id_detalle_factura");
            $consultaDetalles->bind_param("i", $idFactura);
            $consultaDetalles->execute();
            $compraConfirmada["items"] = $consultaDetalles->get_result()->fetch_all(MYSQLI_ASSOC);
            $consultaDetalles->close();
        }
    } else {
        $consultaCarrito = $conexion->prepare("SELECT id_carrito FROM carrito WHERE id_cliente = ? LIMIT 1");
        $consultaCarrito->bind_param("i", $idCliente);
        $consultaCarrito->execute();
        $carrito = $consultaCarrito->get_result()->fetch_assoc();
        $consultaCarrito->close();
        $itemsPago = articulosPago($conexion, (int) ($carrito["id_carrito"] ?? 0));
        try {
            $totalCentavos = validarArticulosPago($itemsPago);
            $_SESSION["resumen_pago"] = resumenPago($itemsPago);
        } catch (RuntimeException $error) {
            $problemaCarrito = $error->getMessage();
            unset($_SESSION["resumen_pago"]);
        }
    }
    $conexion->close();
} catch (Throwable $error) {
    $errorCarga = true;
    http_response_code(503);
    error_log("Error al cargar el pago: " . $error->getMessage());
    $errorPago = "No pudimos cargar los datos de tu compra. Intentá nuevamente más tarde.";
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
    <title>Finalizar compra - GPU FIX &amp; STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=20261026-pago">
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
    <main class="pago-main">
        <?php if ($errorPago !== ""): ?><p class="pago-error" role="alert"><?= escaparPago($errorPago) ?></p><?php endif; ?>
        <?php if ($compraConfirmada): ?>
            <section class="pago-panel pago-confirmado">
                <span class="pago-etiqueta">COMPRA SIMULADA</span>
                <h1>Tu compra quedó registrada</h1>
                <p>Gracias, <?= escaparPago($datosCliente["nombre"]) ?>. El pago fue simulado y no se realizó ningún cobro real.</p>
                <dl class="pago-datos">
                    <div><dt>Comprobante</dt><dd><?= escaparPago($compraConfirmada["numero_factura"]) ?></dd></div>
                    <div><dt>Fecha</dt><dd><?= escaparPago(date("d/m/Y H:i", strtotime($compraConfirmada["fecha_emision"]))) ?></dd></div>
                </dl>
                <ul class="pago-items">
                    <?php foreach ($compraConfirmada["items"] as $detalle): ?>
                        <li><div><strong><?= escaparPago($detalle["descripcion"]) ?></strong><span>Cantidad: <?= (int) $detalle["cantidad"] ?></span></div><strong><?= formatoPago(centavosPago($detalle["subtotal"])) ?></strong></li>
                    <?php endforeach; ?>
                </ul>
                <div class="pago-total"><span>Total simulado</span><strong><?= formatoPago(centavosPago($compraConfirmada["total"])) ?></strong></div>
                <div class="pago-acciones"><a href="mi-cuenta.php" class="pago-boton">Ver mi cuenta</a><a href="productos.php" class="pago-volver">Seguir comprando</a></div>
            </section>
        <?php elseif (!$errorCarga && $idCompra === 0 && $itemsPago): ?>
            <div class="pago-encabezado"><a href="carrito.php" class="pago-volver">← Volver al carrito</a><h1>Finalizá tu compra</h1><p>Revisá tus productos y completá los datos de facturación.</p></div>
            <div class="pago-layout">
                <section class="pago-panel">
                    <h2>Datos de facturación</h2>
                    <dl class="pago-datos">
                        <div><dt>Nombre y apellido</dt><dd><?= escaparPago($datosCliente["nombre"] . " " . $datosCliente["apellido"]) ?></dd></div>
                        <div><dt>DNI</dt><dd><?= escaparPago($datosCliente["dni"]) ?></dd></div>
                        <div><dt>Correo electrónico</dt><dd><?= escaparPago($datosCliente["correo"]) ?></dd></div>
                        <?php if ($datosCliente["telefono"]): ?><div><dt>Teléfono</dt><dd><?= escaparPago($datosCliente["telefono"]) ?></dd></div><?php endif; ?>
                    </dl>
                    <form action="pagar.php" method="post" class="pago-formulario">
                        <input type="hidden" name="csrf_pago" value="<?= escaparPago($csrfPago) ?>">
                        <input type="hidden" name="resumen_pago" value="<?= escaparPago($_SESSION["resumen_pago"] ?? "") ?>">
                        <div><label for="direccionFacturacion">Dirección de facturación</label><textarea id="direccionFacturacion" name="direccion" rows="3" minlength="8" maxlength="255" autocomplete="billing street-address" placeholder="Calle, número, localidad, provincia y código postal" required><?= escaparPago($direccionFacturacion) ?></textarea></div>
                        <div class="pago-simulacion"><strong>Pago de prueba</strong><p>Esta compra es una simulación. No necesitás ingresar una tarjeta ni realizar una transferencia.</p></div>
                        <?php if ($problemaCarrito): ?><p class="pago-error" role="alert"><?= escaparPago($problemaCarrito) ?></p><?php endif; ?>
                        <button type="submit" class="pago-boton" <?= $problemaCarrito ? "disabled" : "" ?>>Confirmar compra simulada</button>
                    </form>
                </section>
                <aside class="pago-panel pago-resumen" aria-label="Resumen de la compra">
                    <h2>Resumen de tu compra</h2>
                    <ul class="pago-items">
                        <?php foreach ($itemsPago as $item): ?>
                            <li>
                                <?php if ($item["imagen"]): ?><a href="producto-gpu.php?id=<?= (int) $item["id_gpu"] ?>"><img src="<?= escaparPago($item["imagen"]) ?>" alt="<?= escaparPago($item["modelo"]) ?>"></a><?php endif; ?>
                                <div><strong><?= escaparPago($item["marca"] . " " . $item["modelo"]) ?></strong><span>Cantidad: <?= (int) $item["cantidad"] ?></span><span><?= centavosPago($item["precio_especial"]) !== null ? formatoPago(centavosPago($item["precio_especial"])) . " por unidad" : "Precio no disponible" ?></span></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (!$problemaCarrito): ?><div class="pago-total"><span>Total</span><strong><?= formatoPago($totalCentavos) ?></strong></div><?php endif; ?>
                    <p class="pago-aclaracion">Garantía de 1 año en todos los productos.</p>
                </aside>
            </div>
        <?php else: ?>
            <section class="pago-panel pago-confirmado"><h1><?= $errorCarga || $idCompra > 0 ? "Compra no disponible" : "Tu carrito está vacío" ?></h1><p><?= $errorCarga || $idCompra > 0 ? "Podés volver al carrito para continuar." : "Agregá un producto para comenzar tu compra." ?></p><a href="carrito.php" class="pago-volver">Volver al carrito</a></section>
        <?php endif; ?>
    </main>
    <footer><p>GPU FIX &amp; STORE - Reparación y venta de GPUs</p></footer>
    <script src="script.js?v=20261022-1"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
