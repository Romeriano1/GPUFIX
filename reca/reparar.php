<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reparar GPU - GPU FIX & STORE</title>

    <link rel="stylesheet" href="style.css?v=20261023-navbar">
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
                        <a class="nav-link active" href="reparar.php">
                            Reparar GPU
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

        <section class="reparaciones">

            <h2>Reparación de GPU</h2>

            <p class="reparaciones-intro">
                Para solicitar una reparación, acercate personalmente a cualquiera de nuestras sucursales.
                Allí recibiremos tu GPU y registraremos la reparación para que puedas consultar su estado
                desde tu cuenta.
            </p>

        </section>

        <section class="seguimiento">

            <h2>Mis reparaciones</h2>

            <div class="acceso-reparaciones">

                <h3>Iniciá sesión para ver tus reparaciones</h3>

                <p>
                    Accedé a tu cuenta para consultar el estado de tus GPUs,
                    los diagnósticos y el seguimiento de cada reparación.
                </p>

                <a href="login.php" class="boton-iniciar-sesion">
                    Iniciar sesión
                </a>

            </div>

        </section>

        <section class="reparaciones">

            <h2 class="titulo-sucursales">
                Nuestras sucursales
            </h2>

            <div class="sucursales">

                <article class="sucursal">

                    <h3>Sucursal Rivadavia</h3>

                    <p>
                        Av. Rivadavia 5376, Local 4
                    </p>

                    <div class="mapa">

                        <iframe
                            src="https://www.google.com/maps?q=Av.+Rivadavia+5376,+Buenos+Aires,+Argentina&output=embed"
                            loading="lazy"
                            allowfullscreen
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>

                    </div>

                    <a class="boton-maps"
                        href="https://www.google.com/maps/search/?api=1&query=Av.+Rivadavia+5376,+Buenos+Aires,+Argentina"
                        target="_blank"
                        rel="noopener noreferrer">
                        Ver en Google Maps
                    </a>

                </article>

                <article class="sucursal">

                    <h3>Sucursal Florida</h3>

                    <p>
                        Florida 537, CABA
                    </p>

                    <div class="mapa">

                        <iframe
                            src="https://www.google.com/maps?q=Florida+537,+Buenos+Aires,+Argentina&output=embed"
                            loading="lazy"
                            allowfullscreen
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>

                    </div>

                    <a class="boton-maps"
                        href="https://www.google.com/maps/search/?api=1&query=Florida+537,+Buenos+Aires,+Argentina"
                        target="_blank"
                        rel="noopener noreferrer">
                        Ver en Google Maps
                    </a>

                </article>

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
