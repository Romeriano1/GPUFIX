<?php
session_start();

$hostSolicitud = strtolower((string) ($_SERVER["HTTP_HOST"] ?? ""));
$hostSolicitud = preg_replace('/:\\d+$/', "", $hostSolicitud);
if (!in_array($hostSolicitud, ["localhost", "127.0.0.1"], true)) {
    http_response_code(404);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    exit;
}

$tokenEnviado = (string) ($_POST["csrf_sesion_provisoria"] ?? "");
$tokenSesion = (string) ($_SESSION["csrf_sesion_provisoria"] ?? "");
if ($tokenSesion === "" || !hash_equals($tokenSesion, $tokenEnviado)) {
    http_response_code(403);
    exit("La solicitud venció. Volvé a cargar la página.");
}

try {
    require_once __DIR__ . "/conexion.php";
    $correoProvisorio = "gpufixstore@gmail.com";
    $consulta = $conexion->prepare(
        "SELECT id_correo, nombre, apellido FROM usuarios WHERE correo = ? AND correo_verificado = 1 LIMIT 1"
    );
    $consulta->bind_param("s", $correoProvisorio);
    $consulta->execute();
    $consulta->bind_result($usuarioId, $nombreUsuario, $apellidoUsuario);
    $usuarioEncontrado = $consulta->fetch();
    $consulta->close();

    if (!$usuarioEncontrado) {
        throw new RuntimeException("La cuenta provisoria no está creada o no está verificada.");
    }

    session_regenerate_id(true);
    $_SESSION["usuario_id"] = (int) $usuarioId;
    $_SESSION["usuario_nombre"] = (string) $nombreUsuario;
    $_SESSION["usuario_apellido"] = (string) $apellidoUsuario;
    unset($_SESSION["csrf_sesion_provisoria"]);

    $destino = !empty($_SESSION["carrito_pendiente"])
        ? "carrito.php?continuar_pendiente=1"
        : "mi-cuenta.php";
    $conexion->close();
    header("Location: " . $destino);
    exit;
} catch (Throwable $error) {
    error_log("Error al iniciar sesión provisoria: " . $error->getMessage());
    if (isset($conexion)) {
        try { $conexion->close(); } catch (Throwable $errorCierre) {}
    }
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sesión provisoria - GPU FIX &amp; STORE</title>
    </head>
    <body>
        <p>No se pudo iniciar la sesión provisoria. Revisá que la cuenta exista y esté verificada.</p>
        <a href="index.php">Volver al inicio</a>
    </body>
    </html>
    <?php
}
