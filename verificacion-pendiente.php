<?php
session_start();

if (!empty($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

$pendienteCorreo = (string) ($_SESSION["verificacion_pendiente_correo"] ?? "");
$pendienteMailEnviado = $_SESSION["verificacion_pendiente_mail"] ?? null;
$consultandoEstado = (($_GET["estado"] ?? "") === "1");

if (!filter_var($pendienteCorreo, FILTER_VALIDATE_EMAIL)) {
    if ($consultandoEstado) {
        header("Content-Type: application/json");
        echo json_encode(["verificado" => false]);
        exit;
    }
    header("Location: login.php");
    exit;
}

// Si el correo se confirmó desde otro dispositivo, se crea la sesión en esta página también.
$verificado = false;
try {
    require_once __DIR__ . "/conexion.php";
    $consulta = $conexion->prepare(
        "SELECT id_correo, nombre, apellido FROM usuarios WHERE correo = ? AND correo_verificado = 1 LIMIT 1"
    );
    $consulta->bind_param("s", $pendienteCorreo);
    $consulta->execute();
    $consulta->bind_result($usuarioId, $nombreUsuario, $apellidoUsuario);
    $encontrado = $consulta->fetch();
    $consulta->close();
    $conexion->close();

    if ($encontrado) {
        unset($_SESSION["verificacion_pendiente_correo"], $_SESSION["verificacion_pendiente_mail"]);
        session_regenerate_id(true);
        $_SESSION["usuario_id"] = (int) $usuarioId;
        $_SESSION["usuario_nombre"] = $nombreUsuario;
        $_SESSION["usuario_apellido"] = $apellidoUsuario;
        $verificado = true;
    }
} catch (Throwable $error) {
    error_log("Error al consultar verificación pendiente: " . $error->getMessage());
}

if ($consultandoEstado) {
    header("Content-Type: application/json");
    echo json_encode(["verificado" => $verificado]);
    exit;
}

if ($verificado) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificá tu correo - GPU FIX & STORE</title>
    <link rel="stylesheet" href="style.css?v=20261021-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous">
</head>
<body class="pagina-login">

<header>
    <nav class="navbar">
        <div class="navbar-izquierda">
            <a class="navbar-brand" href="index.php">GPU FIX & STORE</a>
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                <li class="nav-item"><a class="nav-link" href="productos.php">Productos</a></li>
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

<main class="login-main">
    <section class="login-contenedor">
        <div class="login-formulario">
            <h2>Confirmá tu correo para activar la cuenta</h2>

            <?php if ($pendienteMailEnviado === true): ?>
                <p class="login-descripcion">
                    Guardamos tu registro temporalmente y te enviamos un enlace de confirmación a
                    <strong><?= htmlspecialchars($pendienteCorreo, ENT_QUOTES, "UTF-8") ?></strong>.
                    Al confirmarlo, se creará tu cuenta. El enlace vence en 24 horas.
                </p>
                <div class="login-aviso" role="status">
                    Cuando confirmes el correo, vas a ver la página de confirmación de tu cuenta.
                </div>
            <?php else: ?>
                <p class="login-descripcion">
                    Guardamos tu registro temporalmente con <strong><?= htmlspecialchars($pendienteCorreo, ENT_QUOTES, "UTF-8") ?></strong>,
                    pero no pudimos enviar el correo de confirmación.
                </p>
                <div class="login-error" role="alert">
                    No pudimos enviar el correo de confirmación. Revisá la configuración del correo y, cuando esté corregida, pedí otro enlace.
                </div>
            <?php endif; ?>

            <div class="login-enlaces">
                <p>¿No te llegó? Revisá la carpeta de correo no deseado o</p>
                <a class="login-registro-boton" href="reenviar-confirmacion.php">Reenviar confirmación</a>
            </div>
        </div>
    </section>
</main>

<footer class="login-footer">
    <p>GPU FIX & STORE - Reparación y venta de GPUs</p>
</footer>

<script src="script.js?v=20261021-9"></script>
<script>
// Consulta cada 5 segundos si la cuenta ya se verificó; si es así, muestra la confirmación.
setInterval(function () {
    fetch("verificacion-pendiente.php?estado=1", { cache: "no-store" })
        .then(function (respuesta) { return respuesta.json(); })
        .then(function (datos) {
            if (datos.verificado) {
                window.location.href = "mailconfirmado.php";
            }
        })
        .catch(function () {});
}, 5000);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
