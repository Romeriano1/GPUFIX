<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados - GPU FIX & STORE</title>

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
                        id="campoBusqueda"
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

                <?php include __DIR__ . "/menu-perfil.php"; ?>

            </div>

        </nav>

    </header>

    <main>

        <section class="buscados">

            <h2 id="tituloResultados">Resultados de búsqueda</h2>

            <div class="productos" id="resultados"></div>

        </section>

    </main>

    <footer>

        <p>GPU FIX & STORE - Reparación y venta de GPUs</p>

    </footer>

    <script>

        const productos = [
            {
                nombre: "PLACA DE VIDEO RADEON RX 7600 8GB SAPPHIRE PULSE OC",
                imagen: "img/rx7600.png",
                pagina: "rx7600.php"
            },
            {
                nombre: "PLACA DE VIDEO RADEON RX 9070 16GB GIGABYTE GAMING OC",
                imagen: "img/rx9070.png",
                pagina: "rx9070.php"
            }
        ];

        const parametros = new URLSearchParams(window.location.search);
        const busqueda = parametros.get("buscar") || "";
        const termino = busqueda.toLocaleLowerCase().trim();

        const campoBusqueda = document.getElementById("campoBusqueda");
        const tituloResultados = document.getElementById("tituloResultados");
        const contenedor = document.getElementById("resultados");

        campoBusqueda.value = busqueda;

        const resultados = productos.filter(function(producto) {
            return producto.nombre.toLocaleLowerCase().includes(termino);
        });

        if (termino === "") {
            tituloResultados.textContent = "Buscá una GPU";
        } else {
            tituloResultados.textContent = "Resultados para: " + busqueda;
        }

        if (resultados.length === 0 && termino !== "") {
            contenedor.innerHTML = "<p>No se encontraron productos.</p>";
        } else {
            resultados.forEach(function(producto) {
                contenedor.innerHTML += `
                    <div class="producto">
                        <a href="${producto.pagina}">
                            <img src="${producto.imagen}" alt="${producto.nombre}">
                        </a>
                        <h3>${producto.nombre}</h3>
                    </div>
                `;
            });
        }

    </script>

    <script src="script.js?v=20261021-9"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
