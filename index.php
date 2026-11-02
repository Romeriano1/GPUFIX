<?php
session_start();
$productosCarrusel = [];
try {
    require_once __DIR__ . '/conexion.php';
    $resultadoCarrusel = $conexion->query('SELECT id_gpu, marca, modelo, imagen FROM gpu WHERE id_gpu IN (10, 15, 13, 18, 19) ORDER BY FIELD(id_gpu, 10, 15, 13, 18, 19)');
    $productosCarrusel = $resultadoCarrusel->fetch_all(MYSQLI_ASSOC);
    $resultadoCarrusel->free();
    $conexion->close();
} catch (Throwable $errorCarrusel) {
    error_log('Error al consultar carrusel: ' . $errorCarrusel->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPU FIX & STORE</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous">
    <link rel="stylesheet" href="style.css?v=20261029-consultas">


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

            <?php if ($productosCarrusel): ?>
            <div id="carruselProductos" class="carousel slide"
                data-bs-ride="carousel" data-bs-interval="5000">
                <div class="carousel-inner">
                    <?php foreach ($productosCarrusel as $indiceCarrusel => $productoCarrusel): ?>
                    <div class="carousel-item<?= $indiceCarrusel === 0 ? ' active' : '' ?>">
                        <a href="producto-gpu.php?id=<?= (int) $productoCarrusel['id_gpu'] ?>">
                            <img src="<?= htmlspecialchars((string) $productoCarrusel['imagen'], ENT_QUOTES, 'UTF-8') ?>" class="carrusel-imagen" alt="<?= htmlspecialchars($productoCarrusel['marca'] . ' ' . $productoCarrusel['modelo'], ENT_QUOTES, 'UTF-8') ?>">
                        </a>
                    </div>
                    <?php endforeach; ?>
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
            <?php else: ?>
                <p>No hay productos destacados disponibles.</p>
            <?php endif; ?>
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
        <section class="reviews-clientes" aria-labelledby="titulo-reviews">
            <h2 id="titulo-reviews">Reviews de clientes</h2>
            <div class="reviews-grid">
                <article class="review-cliente">
                    <h3>Lucia Perez</h3>
                    <p>Buenos precios!</p>
                </article>
                <article class="review-cliente">
                    <h3>Leonel Sanchez</h3>
                    <p>La repararon y va mejor que cuando la compré</p>
                </article>
                <article class="review-cliente">
                    <h3>Esteban Quito</h3>
                    <p>Encontrar esta página me cambió la vida</p>
                </article>
                <article class="review-cliente">
                    <h3>Rosa Saracho</h3>
                    <p>el profe de estos chicos debería aprobarlos!</p>
                </article>
            </div>
        </section>
    </main>

    <footer class="footer-gpufix">
    <div class="footer-contenido">

        <!-- Información de GPUFIX -->
        <div class="footer-columna footer-presentacion">
            <h2>GPU FIX <span>& STORE</span></h2>
            <p>
                Especialistas en venta, reparación y mantenimiento
                de placas de video.
            </p>

            <div class="footer-redes">
                <span>Seguinos en nuestras redes</span>
                <div class="footer-redes-iconos">

    <!-- Instagram -->
    <a href="https://www.instagram.com/" target="_blank"
       rel="noopener noreferrer" aria-label="Instagram">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3" y="3" width="18" height="18" rx="5"
                  fill="none" stroke="currentColor" stroke-width="2"/>
            <circle cx="12" cy="12" r="4"
                    fill="none" stroke="currentColor" stroke-width="2"/>
            <circle cx="17.5" cy="6.5" r="1.2" fill="currentColor"/>
        </svg>
    </a>

    <!-- Facebook -->
    <a href="https://www.facebook.com/" target="_blank"
       rel="noopener noreferrer" aria-label="Facebook">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path fill="currentColor"
                  d="M13.5 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.3-1.5 1.6-1.5h1.6V3.6c-.8-.1-1.6-.2-2.5-.2-2.5 0-4.2 1.5-4.2 4.3v2.2H7.2V13H10v8z"/>
        </svg>
    </a>

    <!-- TikTok -->
    <a href="https://www.tiktok.com/" target="_blank"
       rel="noopener noreferrer" aria-label="TikTok">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path fill="currentColor"
                  d="M19.6 8.1a7.1 7.1 0 0 1-4.3-1.4v8.1a6.2 6.2 0 1 1-5.4-6.1v3.2a3.1 3.1 0 1 0 2.2 3V2.5h3.2a4.4 4.4 0 0 0 4.3 3.5z"/>
        </svg>
    </a>

</div>
            </div>
        </div>

        <!-- Reseñas -->
        <div class="footer-columna">
            <h3>Experiencia GPUFIX</h3>
            <p>Tu opinión nos ayuda a seguir mejorando.</p>

            <div class="footer-estrellas" aria-label="Cinco estrellas">
                ★★★★★
            </div>

            <a href="#titulo-reviews" class="footer-enlace-destacado">
                Conocé las opiniones de nuestros clientes →
            </a>
        </div>

        <!-- Contacto -->
        <div class="footer-columna">
            <h3>Contacto</h3>

            <ul class="footer-lista">
                <li>
                    <span class="footer-icono">✉</span>
                    <span>Correo electrónico</span>
                </li>
                <li>
                    <span class="footer-icono">☏</span>
                    <span>WhatsApp</span>
                </li>
                <li>
                    <span class="footer-icono">◎</span>
                    <span>Instagram</span>
                </li>
                <li>
                    <span class="footer-icono">◷</span>
                    <span>Atención al cliente</span>
                </li>
            </ul>
        </div>

    </div>

    <!-- Marcas -->
    
<!-- Marcas -->
<div class="footer-seccion">
    <h3>Trabajamos con las principales marcas</h3>

    <div class="footer-marcas">

        <div class="footer-logo-marca">
            <img src="imagenes/marcas/amd.svg" alt="AMD">
            <span>AMD</span>
        </div>

        <div class="footer-logo-marca">
            <img src="imagenes/marcas/nvidia.svg" alt="NVIDIA">
            <span>NVIDIA</span>
        </div>

        <div class="footer-logo-marca">
            <img src="imagenes/marcas/asus.svg" alt="ASUS">
            <span>ASUS</span>
        </div>

        <div class="footer-logo-marca">
            <img src="imagenes/marcas/msi.svg" alt="MSI">
            <span>MSI</span>
        </div>

        <div class="footer-logo-marca">
            <img src="imagenes/marcas/gigabyte-logo.svg" alt="GIGABYTE">
            <span>GIGABYTE</span>
        </div>

        <div class="footer-logo-marca">
            <img src="imagenes/marcas/aorus-vertical-logo.svg" alt="AORUS">
            <span>AORUS</span>
        </div>

        <div class="footer-logo-marca">
    <img class="logo-claro"
         src="imagenes/marcas/ZOTAC_LightGrey_Logo.png"
         alt="ZOTAC">
    <span>ZOTAC</span>
</div>


        <div class="footer-logo-marca">
    <img class="logo-claro"
         src="imagenes/marcas/sapphirewhite-web.png"
         alt="SAPPHIRE">
    <span>SAPPHIRE</span>
</div>

        <div class="footer-logo-marca">
            <img src="imagenes/marcas/Powercolor_2024_logo.svg.webp" alt="PowerColor">
            <span>POWERCOLOR</span>
        </div>

        <div class="footer-logo-marca">
            <img src="imagenes/marcas/Inno3d_logo.svg.webp" alt="Inno3D">
            <span>INNO3D</span>
        </div>

    </div>
</div>
    
<!-- Medios de pago -->
<div class="footer-seccion">
    <h3>Medios de pago</h3>

    <div class="footer-pagos">

        <div class="footer-logo-pago">
            <img src="imagenes/pagos/visa.svg" alt="Visa">
            <span>VISA</span>
        </div>

        <div class="footer-logo-pago">
            <img src="imagenes/pagos/mastercard.svg" alt="Mastercard">
            <span>Mastercard</span>
        </div>

        <div class="footer-logo-pago">
            <img src="imagenes/pagos/americanexpress.svg" alt="American Express">
            <span>American Express</span>
        </div>

        <div class="footer-logo-pago">
            <img src="imagenes/pagos/mercadopago.svg" alt="Mercado Pago">
            <span>Mercado Pago</span>
        </div>

        <div class="footer-logo-pago">
            <img src="imagenes/pagos/naranja-x.png" alt="Naranja X">
            <span>Naranja X</span>
        </div>

        <div class="footer-logo-pago">
            <img src="imagenes/pagos/paypal.svg" alt="PayPal">
            <span>PayPal</span>
        </div>

    </div>
</div>


    <!-- Copyright -->
    <div class="footer-inferior">
        <p>© 2026 GPU FIX & STORE. Todos los derechos reservados.</p>
        <p>Venta y reparación de placas de video.</p>
    </div>
</footer>
    <script src="script.js?v=20261021-9"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>


