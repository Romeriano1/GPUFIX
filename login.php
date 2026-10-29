<?php
session_start();

$loginError = $_SESSION["login_error_flash"] ?? "";
$correoIngresado = $_SESSION["login_correo_flash"] ?? "";
$registroCreado = !empty($_SESSION["registro_creado"]);
unset($_SESSION["login_error_flash"], $_SESSION["login_correo_flash"], $_SESSION["registro_creado"]);
$requeridoCarrito = (($_GET["requerido"] ?? "") === "carrito") || (($_POST["requerido"] ?? "") === "carrito");
$requeridoReparacion = (($_GET['requerido'] ?? '') === 'reparacion') || (($_POST['requerido'] ?? '') === 'reparacion');
$contextoLogin = $requeridoReparacion ? 'reparacion' : ($requeridoCarrito ? 'carrito' : '');
$destinoCarrito = !empty($_SESSION["carrito_pendiente"])
    ? "carrito.php?continuar_pendiente=1"
    : "carrito.php";
$destinoLogin = $requeridoReparacion ? 'reparar.php#consulta-reparacion' : (($requeridoCarrito || !empty($_SESSION['carrito_pendiente'])) ? $destinoCarrito : 'mi-cuenta.php');

if (!empty($_SESSION["usuario_id"])) {
    header('Location: ' . $destinoLogin);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $correoIngresado = strtolower(trim($_POST["correo"] ?? ""));
    $contrasenaIngresada = $_POST["contrasena"] ?? "";

    if ($correoIngresado === "" || $contrasenaIngresada === "") {
        $loginError = "datos";
    } else {
        try {
            require_once __DIR__ . "/conexion.php";
            $consulta = $conexion->prepare(
                "SELECT id_correo, `contraseña`, nombre, apellido FROM usuarios WHERE correo = ? LIMIT 1"
            );
            $consulta->bind_param("s", $correoIngresado);
            $consulta->execute();
            $consulta->bind_result($usuarioId, $contrasenaGuardada, $nombreUsuario, $apellidoUsuario);
            $usuarioEncontrado = $consulta->fetch();
            $consulta->close();

            $contrasenaValida = $usuarioEncontrado && password_verify($contrasenaIngresada, $contrasenaGuardada);

            if (!$contrasenaValida) {
                $loginError = "credenciales";
            } else {
                if (password_needs_rehash($contrasenaGuardada, PASSWORD_DEFAULT)) {
                    $nuevoHash = password_hash($contrasenaIngresada, PASSWORD_DEFAULT);
                    if ($nuevoHash !== false) {
                        $actualizarHash = $conexion->prepare(
                            "UPDATE usuarios SET `contraseña` = ? WHERE id_correo = ?"
                        );
                        $actualizarHash->bind_param("si", $nuevoHash, $usuarioId);
                        $actualizarHash->execute();
                        $actualizarHash->close();
                    }
                }

                session_regenerate_id(true);
                $_SESSION["usuario_id"] = (int) $usuarioId;
                $_SESSION["usuario_nombre"] = $nombreUsuario;
                $_SESSION["usuario_apellido"] = $apellidoUsuario;

                $conexion->close();
                header('Location: ' . $destinoLogin);
                exit;
            }
            $conexion->close();
        } catch (Throwable $error) {
            error_log("Error al iniciar sesión: " . $error->getMessage());
            $loginError = "conexion";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $loginError !== "") {
    $_SESSION["login_error_flash"] = $loginError;
    $_SESSION["login_correo_flash"] = $correoIngresado;
    header('Location: login.php' . ($contextoLogin !== '' ? '?requerido=' . $contextoLogin : ''));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - GPU FIX & STORE</title>
    <link rel="stylesheet" href="style.css?v=20261029-buscador">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous">
</head>
<body class="pagina-login">

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

<main class="login-main">
    <section class="login-contenedor">
        <div class="login-formulario">
            <h2>Iniciar sesión</h2>
            <p class="login-descripcion">Ingresá a tu cuenta de GPU FIX & STORE</p>

            <?php if ($requeridoCarrito): ?>
                <div class="login-aviso">Iniciá sesión para entrar al carrito.</div>
            <?php endif; ?>

            <?php if (($_GET["registrado"] ?? "") === "1" && $registroCreado): ?>
                <div class="login-aviso">
                    Tu cuenta fue creada. Ya podés iniciar sesión con tu correo y contraseña.
                </div>
            <?php endif; ?>

            <?php if ($loginError !== ""): ?>
                <div class="login-error">
                    <?php
                    if ($loginError === "datos") {
                        echo "Completá todos los campos.";
                    } elseif ($loginError === "credenciales") {
                        echo "El correo o la contraseña son incorrectos.";
                    } elseif ($loginError === "conexion") {
                        echo "No pudimos conectar con la base de datos. Intentá nuevamente más tarde.";
                    } else {
                        echo "No se pudo iniciar sesión.";
                    }
                    ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" class="login-form" action="login.php" method="POST" novalidate>
                <?php if ($contextoLogin !== ''): ?>
                    <input type="hidden" name="requerido" value="<?= htmlspecialchars($contextoLogin, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <div class="login-campo">
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" placeholder="Ingresá tu correo" autocomplete="email" aria-describedby="correoError" value="<?php echo htmlspecialchars($correoIngresado, ENT_QUOTES, "UTF-8"); ?>">
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
    <p>¿No tenés cuenta?</p>
    <a class="login-registro-boton" href="registro.php<?php echo $contextoLogin !== '' ? '?requerido=' . $contextoLogin : ''; ?>">Registrate</a>

    <p class="mt-4">¿Trabajás en GPU FIX & STORE?</p>
    <a class="login-registro-boton" href="login-empleado.php">Soy empleado</a>
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


