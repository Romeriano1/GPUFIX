<?php
session_start();
if (empty($_SESSION["csrf_carrito"])) {
    $_SESSION["csrf_carrito"] = bin2hex(random_bytes(32));
}
$csrfCarrito = $_SESSION["csrf_carrito"];

$productos = [];
$errorProductos = false;
try {
    require_once __DIR__ . "/conexion.php";
    $consulta = $conexion->prepare(
        "SELECT gpu.id_gpu, gpu.marca, gpu.modelo, gpu.imagen, gpu.precio_especial, " .
        "COUNT(stock_gpu.id_stock_gpu) AS registros_stock, " .
        "COALESCE(SUM(stock_gpu.cantidad), 0) AS cantidad " .
        "FROM gpu LEFT JOIN stock_gpu ON stock_gpu.id_gpu = gpu.id_gpu " .
        "GROUP BY gpu.id_gpu, gpu.marca, gpu.modelo, gpu.imagen, gpu.precio_especial " .
        "ORDER BY gpu.marca, gpu.modelo"
    );
    $consulta->execute();
    $consulta->bind_result($idProducto, $marcaProducto, $modeloProducto, $imagenProducto, $precioProducto, $registrosStock, $cantidadStock);
    while ($consulta->fetch()) {
        $productos[] = [
            "id" => (int) $idProducto,
            "marca" => (string) $marcaProducto,
            "modelo" => (string) $modeloProducto,
            "imagen" => (string) ($imagenProducto ?? ""),
            "precio" => $precioProducto,
            "pagina" => "producto-gpu.php?id=" . (int) $idProducto,
            "registros_stock" => (int) $registrosStock,
            "cantidad" => max(0, (int) $cantidadStock),
        ];
    }
    $consulta->close();
    $conexion->close();
} catch (Throwable $error) {
    $errorProductos = true;
    error_log("Error al listar productos GPU: " . $error->getMessage());
    if (isset($conexion)) {
        try { $conexion->close(); } catch (Throwable $errorCierre) {}
    }
}

$escapar = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
$formatearPrecio = static fn ($precio): string => $precio === null || $precio === ""
    ? "Consultar precio"
    : "$ " . number_format((float) $precio, 2, ",", ".");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - GPU FIX &amp; STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=20261029-buscador">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="navbar-izquierda">
                <a class="navbar-brand" href="index.php">GPU FIX &amp; STORE</a>
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link active" href="productos.php">Productos</a></li>
                    <li class="nav-item"><a class="nav-link" href="reparar.php">Reparar GPU</a></li>
                </ul>
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
        <section class="buscados">
            <h2>Todos nuestros productos</h2>
            <?php if ($errorProductos): ?>
                <p class="mensaje-productos">No se pudieron cargar los productos. Revisá la conexión y la estructura de la base de datos.</p>
            <?php elseif (!$productos): ?>
                <p class="mensaje-productos">Todavía no hay productos cargados.</p>
            <?php else: ?>
                <div class="productos">
                    <?php foreach ($productos as $producto): ?>
                        <?php
                        if ($producto["registros_stock"] === 0) {
                            $estadoStockClase = "stock-pendiente";
                            $estadoStockTexto = "Stock a confirmar";
                        } elseif ($producto["cantidad"] === 0) {
                            $estadoStockClase = "stock-agotado";
                            $estadoStockTexto = "No hay stock";
                        } else {
                            $estadoStockClase = "stock-disponible";
                            $estadoStockTexto = (int) $producto["cantidad"] . " unidades disponibles";
                        }
                        $compraDeshabilitada = $producto["registros_stock"] === 0 || $producto["cantidad"] <= 0;
                        ?>
                        <article class="producto">
                            <a href="<?= $escapar($producto["pagina"]) ?>" class="producto-enlace">
                                <?php if ($producto["imagen"] !== ""): ?>
                                    <img src="<?= $escapar($producto["imagen"]) ?>" alt="<?= $escapar($producto["modelo"]) ?>">
                                <?php else: ?>
                                    <div class="producto-imagen-vacio producto-imagen-vacio-card" aria-hidden="true"></div>
                                <?php endif; ?>
                            </a>
                            <p class="producto-marca"><?= $escapar($producto["marca"]) ?></p>
                            <h3><a class="producto-titulo-enlace" href="<?= $escapar($producto["pagina"]) ?>"><?= $escapar($producto["modelo"]) ?></a></h3>
                            <p class="producto-precio"><?= $escapar($formatearPrecio($producto["precio"])) ?></p>
                            <p class="producto-stock <?= $escapar($estadoStockClase) ?>"><?= $escapar($estadoStockTexto) ?></p>
                            <form action="carrito.php" method="post" class="producto-acciones">
                                <input type="hidden" name="accion" value="agregar">
                                <input type="hidden" name="id_gpu" value="<?= (int) $producto["id"] ?>">
                                <input type="hidden" name="cantidad" value="1">
                                <input type="hidden" name="csrf_carrito" value="<?= $escapar($csrfCarrito) ?>">
                                <button type="submit" name="tipo" value="agregar" class="boton-carrito" <?= $compraDeshabilitada ? "disabled" : "" ?>>Agregar al carrito</button>
                                <button type="submit" name="tipo" value="comprar" class="boton-comprar" <?= $compraDeshabilitada ? "disabled" : "" ?>>Comprar</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer><p>GPU FIX &amp; STORE - Reparación y venta de GPUs</p></footer>
    <script src="script.js?v=20261022-1"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
