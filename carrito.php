<?php
session_start();

if (empty($_SESSION["usuario_id"])) {
    header("Location: login.php?requerido=carrito");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito - GPU FIX & STORE</title>

    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

    <header>

        <nav class="navbar">

            <div class="navbar-izquierda">

                <a class="navbar-brand" href="index.php">
                    GPU FIX & STORE
                </a>

                <ul class="navbar-nav">

                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            Inicio
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="productos.php">
                            Productos
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="reparar.php">
                            Reparar GPU
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link active" href="carrito.php">
                            Ver carrito
                        </a>
                    </li>

                </ul>

            </div>

            <div class="navbar-derecha">

                <button id="themeButton" class="theme-button" type="button" aria-label="Cambiar tema">
                    ☀️
                </button>

                <?php include __DIR__ . "/menu-perfil.php"; ?>

            </div>

        </nav>

    </header>

    <main>

        <section class="carrito-vacio">

            <h2>Tu carrito está vacío</h2>

            <p>
                Todavía no agregaste ningún producto a tu carrito.
            </p>

            <p>
                Explorá nuestros productos y encontrá la GPU que estás buscando.
            </p>

            <a href="productos.php" class="boton-maps">
                Ver productos
            </a>

        </section>

    </main>

    <footer>

        <p>GPU FIX & STORE - Reparación y venta de GPUs</p>

    </footer>

    <script src="script.js?v=20261021-9"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
