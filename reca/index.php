<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPU FIX & STORE</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous">
    <link rel="stylesheet" href="style.css?v=20261023-navbar">


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
                        <a class="nav-link active" href="index.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="productos.php">Productos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reparar.php">Reparar GPU</a>
                    </li>

                </ul>
            </div>

            <div class="navbar-centro">
                <form class="search-form" action="busqueda.php" method="GET" role="search">
                    <input class="form-control" type="search" name="buscar"
                        placeholder="Buscar GPU" aria-label="Buscar GPU">
                    <button class="btn" type="submit">Buscar</button>
                </form>
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
        <section class="buscados">
            <h2>Los más buscados</h2>

            <div id="carruselProductos" class="carousel slide"
                data-bs-ride="carousel" data-bs-interval="5000">
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <a href="producto-gpu.php?id=10">
                            <img src="https://i.ibb.co/fYmP0g9N/rtx5090-Maorus.png" class="carrusel-imagen" alt="Aorus RTX 5090 Master Ice 32GB">
                        </a>
                    </div>

                    <div class="carousel-item">
                        <a href="producto-gpu.php?id=15">
                            <img src="https://i.ibb.co/pjZY0nn5/rtx3060asus.png" class="carrusel-imagen" alt="ASUS RTX 3060 Dual V2 OC 12GB">
                        </a>
                    </div>

                    <div class="carousel-item">
                        <a href="producto-gpu.php?id=13">
                            <img src="https://i.ibb.co/zWCMT08N/rx9070sapphire.png" class="carrusel-imagen" alt="Sapphire RX 9070 Pulse 16GB">
                        </a>
                    </div>

                    <div class="carousel-item">
                        <a href="producto-gpu.php?id=18">
                            <img src="https://i.ibb.co/RkfFXM3z/rx9070tipowercolor.png" class="carrusel-imagen" alt="PowerColor RX 9070 XT Hellhound OC 16GB">
                        </a>
                    </div>

                    <div class="carousel-item">
                        <a href="producto-gpu.php?id=19">
                            <img src="https://i.ibb.co/XxGWdzyt/rtx3080inno3d.webp" class="carrusel-imagen" alt="INNO3D RTX 3080 X3 LHR 10GB">
                        </a>
                    </div>
                </div>

                <button class="carousel-control-prev" type="button"
                    data-bs-target="#carruselProductos" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                    <span class="visually-hidden">Anterior</span>
                </button>

                <button class="carousel-control-next" type="button"
                    data-bs-target="#carruselProductos" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span>
                    <span class="visually-hidden">Siguiente</span>
                </button>
            </div>
        </section>

        <section class="marcas-section">
            <div class="marcas-encabezado">
                <h2>Conocé nuestras marcas</h2>
                <p>Encontrá tu próxima GPU entre las marcas que más te gustan.</p>
            </div>

            <div class="marcas-grid">
                <a href="busqueda.php?buscar=Gigabyte" class="marca-boton">
                    <span>GIGABYTE</span>
                    <span class="marca-flecha" aria-hidden="true">↗</span>
                </a>
                <a href="busqueda.php?buscar=Aorus" class="marca-boton">
                    <span>AORUS</span>
                    <span class="marca-flecha" aria-hidden="true">↗</span>
                </a>
                <a href="busqueda.php?buscar=Sapphire" class="marca-boton">
                    <span>SAPPHIRE</span>
                    <span class="marca-flecha" aria-hidden="true">↗</span>
                </a>
                <a href="busqueda.php?buscar=ASUS" class="marca-boton">
                    <span>ASUS</span>
                    <span class="marca-flecha" aria-hidden="true">↗</span>
                </a>
                <a href="busqueda.php?buscar=PowerColor" class="marca-boton">
                    <span>POWERCOLOR</span>
                    <span class="marca-flecha" aria-hidden="true">↗</span>
                </a>
                <a href="busqueda.php?buscar=INNO3D" class="marca-boton">
                    <span>INNO3D</span>
                    <span class="marca-flecha" aria-hidden="true">↗</span>
                </a>
                <a href="busqueda.php?buscar=MSI" class="marca-boton">
                    <span>MSI</span>
                    <span class="marca-flecha" aria-hidden="true">↗</span>
                </a>
                <a href="busqueda.php?buscar=ZOTAC" class="marca-boton">
                    <span>ZOTAC</span>
                    <span class="marca-flecha" aria-hidden="true">↗</span>
                </a>
            </div>
        </section>
    </main>

    <footer>
        <p>GPU FIX & STORE - Reparación y venta de GPUs</p>
    </footer>

    <script src="script.js?v=20261021-9"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>


