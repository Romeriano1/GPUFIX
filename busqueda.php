<?php
session_start();
if (empty($_SESSION["csrf_carrito"])) {
    $_SESSION["csrf_carrito"] = bin2hex(random_bytes(32));
}
$csrfCarrito = $_SESSION["csrf_carrito"];

$busqueda = trim((string) ($_GET["buscar"] ?? ""));
$productos = [];
$errorProductos = false;
if ($busqueda !== "") {
    try {
        require_once __DIR__ . "/conexion.php";
        $termino = "%" . $busqueda . "%";
        $terminoNormalizado = "%" . preg_replace('/[\s._-]+/u', '', strtolower($busqueda)) . "%";
        $consulta = $conexion->prepare(
            "SELECT gpu.id_gpu, gpu.marca, gpu.modelo, gpu.imagen, gpu.precio_especial, gpu.pagina, " .
            "COUNT(stock_gpu.id_stock_gpu) AS registros_stock, " .
            "COALESCE(SUM(stock_gpu.cantidad), 0) AS cantidad " .
            "FROM gpu LEFT JOIN stock_gpu ON stock_gpu.id_gpu = gpu.id_gpu " .
            "WHERE gpu.pagina IS NOT NULL AND gpu.pagina <> '' " .
            "AND (gpu.marca LIKE ? OR gpu.modelo LIKE ? " .
            "OR REPLACE(REPLACE(REPLACE(LOWER(gpu.marca), ' ', ''), '-', ''), '.', '') LIKE ? " .
            "OR REPLACE(REPLACE(REPLACE(LOWER(gpu.modelo), ' ', ''), '-', ''), '.', '') LIKE ?) " .
            "GROUP BY gpu.id_gpu, gpu.marca, gpu.modelo, gpu.imagen, gpu.precio_especial, gpu.pagina " .
            "ORDER BY gpu.marca, gpu.modelo"
        );
        $consulta->bind_param("ssss", $termino, $termino, $terminoNormalizado, $terminoNormalizado);
        $consulta->execute();
        $consulta->bind_result($id, $marca, $modelo, $imagen, $precio, $pagina, $registrosStock, $cantidad);
        while ($consulta->fetch()) {
            $productos[] = [
                "id" => (int) $id,
                "marca" => (string) $marca,
                "modelo" => (string) $modelo,
                "imagen" => (string) ($imagen ?? ""),
                "precio" => $precio,
                "pagina" => (string) $pagina,
                "registros_stock" => (int) $registrosStock,
                "cantidad" => max(0, (int) $cantidad),
            ];
        }
        $consulta->close();
        $conexion->close();
    } catch (Throwable $error) {
        $errorProductos = true;
        error_log("Error al buscar productos GPU: " . $error->getMessage());
        if (isset($conexion)) {
            try { $conexion->close(); } catch (Throwable $errorCierre) {}
        }
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
    <title>Resultados - GPU FIX &amp; STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=20261023-navbar">
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
            <div class="navbar-centro">
                <form class="search-form" action="busqueda.php" method="GET" role="search">
                    <input class="form-control" id="campoBusqueda" type="search" name="buscar" value="<?= $escapar($busqueda) ?>" placeholder="Buscar GPU" aria-label="Buscar GPU">
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
            <h2><?= $busqueda === "" ? "Buscá una GPU" : "Resultados para: " . $escapar($busqueda) ?></h2>
            <?php if ($errorProductos): ?>
                <p class="mensaje-productos">No se pudieron cargar los productos. Revisá la conexión y la estructura de la base de datos.</p>
            <?php elseif ($busqueda !== "" && !$productos): ?>
                <p class="mensaje-productos">No se encontraron productos.</p>
            <?php elseif ($productos): ?>
                <div class="productos" id="resultados">
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
