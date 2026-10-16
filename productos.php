
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - GPU FIX & STORE</title>

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
                        <a class="nav-link active" href="productos.php">
                            Productos
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="reparar.php">
                            Reparar GPU
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="carrito.php">
                            Ver carrito
                        </a>
                    </li>

                </ul>

            </div>

            <div class="navbar-centro">

                <form class="search-form" action="busqueda.php" method="GET" role="search">

                    <input
                        class="form-control"
                        type="search"
                        name="buscar"
                        placeholder="Buscar GPU"
                        aria-label="Buscar GPU">

                    <button class="btn" type="submit">
                        Buscar
                    </button>

                </form>

            </div>

            <div class="navbar-derecha">

                <button id="themeButton" class="theme-button" type="button" aria-label="Cambiar tema">
                    ☀️
                </button>

                <div class="cuenta-contenedor">

                    <a href="login.php" class="cuenta-button" aria-label="Iniciar sesión">
                        👤
                    </a>

                </div>

            </div>

        </nav>

    </header>

    <main>

        <section class="buscados">

            <h2>Todos nuestros productos</h2>

            <div class="productos">

                <div class="producto">

                    <a href="rx7600.php">
                        <img src="img/rx7600.png" alt="RX 7600">
                    </a>

                    <h3>RX 7600</h3>

                </div>

                <div class="producto">

                    <a href="rx9070.php">
                        <img src="img/rx9070.png" alt="RX 9070">
                    </a>

                    <h3>RX 9070</h3>

                </div>

            </div>

        </section>

    </main>

    <footer>

        <p>GPU FIX & STORE - Reparación y venta de GPUs</p>

    </footer>

    <script src="script.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>