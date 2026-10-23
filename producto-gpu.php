<?php
session_start();

$producto = null;
$errorConsulta = false;
$paginaProducto = basename((string) ($_SERVER["SCRIPT_NAME"] ?? ""));

try {
    require_once __DIR__ . "/conexion.php";
    $consulta = $conexion->prepare(
        "SELECT gpu.id_gpu, gpu.marca, gpu.modelo, gpu.imagen, gpu.precio_especial, " .
        "gpu.especificaciones, " .
        "COUNT(stock_gpu.id_stock_gpu) AS registros_stock, " .
        "COALESCE(SUM(stock_gpu.cantidad), 0) AS cantidad " .
        "FROM gpu LEFT JOIN stock_gpu ON stock_gpu.id_gpu = gpu.id_gpu " .
        "WHERE gpu.pagina = ? " .
        "GROUP BY gpu.id_gpu, gpu.marca, gpu.modelo, gpu.imagen, gpu.precio_especial, " .
        "gpu.especificaciones LIMIT 1"
    );
    $consulta->bind_param("s", $paginaProducto);
    $consulta->execute();
    $consulta->bind_result(
        $idProducto,
        $marcaProducto,
        $modeloProducto,
        $imagenGuardada,
        $precioEspecial,
        $especificacionesProducto,
        $registrosStock,
        $cantidadStock
    );

    if ($consulta->fetch()) {
        $producto = [
            "id" => (int) $idProducto,
            "marca" => (string) $marcaProducto,
            "modelo" => (string) $modeloProducto,
            "imagen" => (string) ($imagenGuardada ?? ""),
            "precio" => $precioEspecial,
            "especificaciones" => (string) ($especificacionesProducto ?? ""),
            "registros_stock" => (int) $registrosStock,
            "cantidad" => max(0, (int) $cantidadStock),
        ];
    }

    $consulta->close();
    $conexion->close();
} catch (Throwable $error) {
    $errorConsulta = true;
    error_log("Error al consultar producto GPU: " . $error->getMessage());
    if (isset($conexion)) {
        try {
            $conexion->close();
        } catch (Throwable $errorCierre) {
            // La conexión puede no haberse abierto.
        }
    }
}

if ($producto === null && !$errorConsulta) {
    http_response_code(404);
}

$escapar = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
$formatearPrecio = static function ($precio): string {
    return $precio === null || $precio === "" ? "Consultar precio" : "$ " . number_format((float) $precio, 2, ",", ".");
};
$lineasEspecificaciones = $producto
    ? array_values(array_filter(array_map("trim", preg_split("/\\R/", $producto["especificaciones"]))))
    : [];
$hayStockRegistrado = $producto && $producto["registros_stock"] > 0;
$stockDisponible = $producto ? $producto["cantidad"] : 0;
$sinStock = $hayStockRegistrado && $stockDisponible === 0;
$stockPendiente = $producto && !$hayStockRegistrado;
$stockConsultadoError = $errorConsulta;
$compraDeshabilitada = $stockConsultadoError || $stockPendiente || $sinStock;
$tituloProducto = $producto
    ? strtoupper("PLACA DE VIDEO " . $producto["modelo"] . " " . $producto["marca"])
    : "Producto no disponible";

$imagenProducto = $producto && $producto["imagen"] !== "" ? $producto["imagen"] : null;
$resolucionOrientativa = "Depende del juego, la calidad gráfica y el monitor.";
$fuenteRecomendada = null;
if ($producto) {
    if (preg_match('/\b(?:RX|RTX)\s*(\d{4})\b/i', $producto["modelo"], $coincidenciaModeloGpu)) {
        switch ($coincidenciaModeloGpu[1]) {
            case "7600":
            case "3060":
                $resolucionOrientativa = "Full HD (1080p), según el juego y su configuración.";
                break;
            case "6700":
            case "7700":
                $resolucionOrientativa = "1440p, según el juego y su configuración.";
                break;
            case "9070":
                $resolucionOrientativa = "1440p o 4K, según el juego y su configuración.";
                break;
            case "3080":
                $resolucionOrientativa = "1440p; también puede usarse en 4K ajustando la configuración.";
                break;
            case "4090":
            case "5090":
                $resolucionOrientativa = "4K; el rendimiento depende del juego y su configuración.";
                break;
        }
    }

    if (preg_match('/^Fuente recomendada(?: de referencia)?:\s*(.+)$/mi', $producto["especificaciones"], $coincidenciaFuente)) {
        $fuenteRecomendada = trim($coincidenciaFuente[1]);
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escapar($producto ? $producto["modelo"] : "Producto no disponible") ?> - GPU FIX &amp; STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=20261022-5">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="navbar-izquierda">
                <a class="navbar-brand" href="index.php">GPU FIX &amp; STORE</a>
                <div class="navbar-nav">
                    <a class="nav-link" href="index.php">Inicio</a>
                    <a class="nav-link" href="productos.php">Productos</a>
                    <a class="nav-link" href="reparar.php">Reparar GPU</a>
                </div>
            </div>
            <div class="navbar-centro">
                <form class="search-form" action="busqueda.php" method="GET" role="search">
                    <input class="form-control" type="search" name="buscar" placeholder="Buscar GPU" aria-label="Buscar GPU">
                    <button class="btn" type="submit">Buscar</button>
                </form>
            </div>
            <div class="navbar-derecha">
                <button id="themeButton" class="theme-button" type="button" aria-label="Cambiar tema">☀️</button>
                <?php include __DIR__ . "/menu-perfil.php"; ?>
            </div>
        </nav>
    </header>

    <main>
        <?php if ($producto === null): ?>
            <section class="carrito-vacio">
                <h2><?= $errorConsulta ? "No se pudo cargar el producto" : "Producto no encontrado" ?></h2>
                <p><?= $errorConsulta ? "Revisá la conexión y la migración de la base de datos." : "Este producto todavía no está cargado en la base de datos." ?></p>
                <a href="productos.php" class="boton-maps">Ver productos</a>
            </section>
        <?php else: ?>
            <section class="producto-detalle">
                <div class="producto-imagen">
                    <?php if ($imagenProducto !== null): ?>
                        <img src="<?= $escapar($imagenProducto) ?>" alt="<?= $escapar($tituloProducto) ?>">
                    <?php else: ?>
                        <div class="producto-imagen-vacio producto-imagen-vacio-detalle" aria-hidden="true"></div>
                    <?php endif; ?>
                </div>

                <div class="producto-informacion">
                    <h1><?= $escapar($tituloProducto) ?></h1>
                    <p class="producto-id">ID de producto: <?= (int) $producto["id"] ?></p>

                    <div class="precio">
                        <p>Precio:</p>
                        <strong><?= $escapar($formatearPrecio($producto["precio"])) ?></strong>
                    </div>

                    <div class="stock <?= $stockConsultadoError ? "stock-error" : ($sinStock ? "stock-agotado" : ($stockPendiente ? "stock-pendiente" : "stock-disponible")) ?>" aria-live="polite">
                        <?php if ($stockConsultadoError): ?>
                            <span>No se pudo consultar el inventario de GPU FIX</span>
                        <?php elseif ($stockPendiente): ?>
                            <span>Stock de GPU FIX pendiente de cargar</span>
                        <?php elseif ($sinStock): ?>
                            <span>No hay stock</span>
                        <?php else: ?>
                            <span>Hay stock — <?= (int) $stockDisponible ?> unidades disponibles</span>
                        <?php endif; ?>
                    </div>

                    <div class="cantidad">
                        <p>Cantidad</p>
                        <?php if ($stockPendiente): ?>
                            <span class="cantidad-pendiente">A confirmar</span>
                        <?php else: ?>
                            <div class="selector-cantidad">
                                <button id="menos" type="button" aria-label="Reducir cantidad" <?= ($stockConsultadoError || $stockDisponible <= 1) ? "disabled" : "" ?>>−</button>
                                <span id="cantidad"><?= $stockDisponible > 0 && !$stockConsultadoError ? "1" : "0" ?></span>
                                <button id="mas" type="button" aria-label="Aumentar cantidad" <?= ($stockConsultadoError || $stockDisponible <= 1) ? "disabled" : "" ?>>+</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button class="boton-comprar" type="button" <?= $compraDeshabilitada ? "disabled" : "" ?>>Comprar</button>
                    <button class="boton-carrito" type="button" <?= $compraDeshabilitada ? "disabled" : "" ?>>Agregar al carrito</button>

                    <div class="producto-extra">
                        <div><strong>Envío</strong><p>A consultar</p></div>
                        <div><strong>Garantía</strong><p>1 año</p></div>
                    </div>
                </div>
            </section>

            <section class="informacion-producto">
                <div class="bloque-informacion">
                    <h2>Especificaciones técnicas</h2>
                    <?php foreach ($lineasEspecificaciones as $linea): ?>
                        <?php
                        $partes = explode(":", $linea, 2);
                        $etiqueta = count($partes) === 2 ? trim($partes[0]) : "";
                        $valor = count($partes) === 2 ? trim($partes[1]) : $linea;
                        ?>
                        <p><?php if ($etiqueta !== ""): ?><strong><?= $escapar($etiqueta) ?>:</strong> <?php endif; ?><?= $escapar($valor) ?></p>
                    <?php endforeach; ?>
                </div>

                <div class="bloque-informacion">
                    <h2>Información y recomendaciones</h2>
                    <p><strong>¿Para qué resolución es ideal?</strong> <?= $escapar($resolucionOrientativa) ?></p>
                    <p><strong>¿Sirve para eSports?</strong> Sí; los FPS dependen también del juego, el procesador y el monitor.</p>
                    <p><strong>¿Qué fuente necesito?</strong>
                        <?php if ($fuenteRecomendada !== null): ?>
                            Se recomienda una fuente de <?= $escapar($fuenteRecomendada) ?>.
                        <?php else: ?>
                            Verificá la fuente y los conectores requeridos para el modelo.
                        <?php endif; ?>
                    </p>
                    <p><strong>Antes de actualizar:</strong> verificá el espacio del gabinete y los conectores de alimentación.</p>
                </div>

            </section>
        <?php endif; ?>
    </main>

    <footer><p>© 2026 GPU FIX &amp; STORE</p></footer>

    <?php if ($producto !== null && !$stockPendiente): ?>
        <script>
        (function () {
            const stockDisponible = <?= $stockConsultadoError ? "0" : (int) $stockDisponible ?>;
            const cantidadElemento = document.getElementById("cantidad");
            const botonMenos = document.getElementById("menos");
            const botonMas = document.getElementById("mas");
            if (!cantidadElemento || !botonMenos || !botonMas) return;
            let cantidad = stockDisponible > 0 ? 1 : 0;
            function actualizarSelector() {
                cantidadElemento.textContent = String(cantidad);
                botonMenos.disabled = stockDisponible <= 0 || cantidad <= 1;
                botonMas.disabled = stockDisponible <= 0 || cantidad >= stockDisponible;
            }
            botonMenos.addEventListener("click", function () {
                if (cantidad > 1) { cantidad -= 1; actualizarSelector(); }
            });
            botonMas.addEventListener("click", function () {
                if (cantidad < stockDisponible) { cantidad += 1; actualizarSelector(); }
            });
            actualizarSelector();
        })();
        </script>
    <?php endif; ?>
    <script src="script.js?v=20261022-1"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
