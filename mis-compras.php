<?php
session_start();

if (isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - GPU FIX & STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="pagina-login">

<header class="login-header">
    <nav class="navbar navbar-login">
        <div class="navbar-izquierda navbar-login-izquierda">
            <a class="navbar-brand navbar-login-brand" href="index.php">GPU FIX & STORE</a>
            <ul class="navbar-nav navbar-login-enlaces">
                <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                <li class="nav-item"><a class="nav-link" href="productos.php">Productos</a></li>
                <li class="nav-item"><a class="nav-link" href="reparar.php">Reparar GPU</a></li>

            </ul>
        </div>

        <div class="navbar-centro navbar-login-centro">
            <form class="search-form" action="busqueda.php" method="GET" role="search">
                <input class="form-control" type="search" name="buscar" placeholder="Buscar GPU" aria-label="Buscar GPU">
                <button class="btn" type="submit">Buscar</button>
            </form>
        </div>

        <div class="navbar-derecha navbar-login-derecha">
            <button id="themeButton" class="theme-button" type="button" aria-label="Cambiar tema">☀️</button>
                <?php include __DIR__ . "/menu-perfil.php"; ?>
        </div>
    </nav>
</header>

<main class="login-main">
    <section class="login-contenedor">
        <div class="login-formulario">
            <h2>Iniciar sesión</h2>
            <p class="login-descripcion">Ingresá a tu cuenta de GPU FIX & STORE</p>

            <?php if (isset($_GET["error"])): ?>
                <div class="login-error">
                    <?php
                    if ($_GET["error"] === "datos") {
                        echo "Completá todos los campos.";
                    } elseif ($_GET["error"] === "credenciales") {
                        echo "El correo o la contraseña son incorrectos.";
                    } else {
                        echo "No se pudo iniciar sesión.";
                    }
                    ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" class="login-form" action="login.php" method="POST" novalidate>
                <div class="login-campo">
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" placeholder="Ingresá tu correo" autocomplete="email" aria-describedby="correoError">
                    <span id="correoError" class="login-error-campo" role="alert"></span>
                </div>

                <div class="login-campo">
                    <label for="contrasena">Contraseña</label>
                    <input type="password" id="contrasena" name="contrasena" placeholder="Ingresá tu contraseña" autocomplete="current-password" aria-describedby="contrasenaError">
                    <span id="contrasenaError" class="login-error-campo" role="alert"></span>
                </div>

                <button class="login-boton" type="submit">Ingresar</button>
            </form>

            <div class="login-enlaces">
                <p>¿Todavía no tenés una cuenta?</p>
                <a href="registro.php">Crear una cuenta</a>
            </div>
        </div>
    </section>
</main>

<footer class="login-footer">
    <p>GPU FIX & STORE - Reparación y venta de GPUs</p>
</footer>

<script src="script.js?v=20261021-9"></script>
<script>
const loginForm = document.getElementById("loginForm");
const correo = document.getElementById("correo");
const contrasena = document.getElementById("contrasena");
const correoError = document.getElementById("correoError");
const contrasenaError = document.getElementById("contrasenaError");

loginForm.addEventListener("submit", function(event) {
    let hayErrores = false;

    correoError.textContent = "";
    contrasenaError.textContent = "";

    if (correo.value.trim() === "") {
        correoError.textContent = "Ingresá tu correo electrónico.";
        hayErrores = true;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo.value.trim())) {
        correoError.textContent = "Ingresá un correo electrónico válido.";
        hayErrores = true;
    }

    if (contrasena.value === "") {
        contrasenaError.textContent = "Ingresá tu contraseña.";
        hayErrores = true;
    }

    if (hayErrores) {
        event.preventDefault();
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

