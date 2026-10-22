<?php
session_start();
$confirmacion = "invalida";
$token = trim((string) ($_GET["token"] ?? ""));

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    try {
        require_once __DIR__ . "/conexion.php";
        $tokenHash = hash("sha256", $token);
        $consulta = $conexion->prepare(
            "SELECT id_correo, nombre, apellido FROM USUARIOS WHERE token_verificacion = ? AND token_expira > NOW() AND correo_verificado = 0 LIMIT 1"
        );
        $consulta->bind_param("s", $tokenHash);
        $consulta->execute();
        $consulta->bind_result($usuarioId, $nombreUsuario, $apellidoUsuario);
        $encontrado = $consulta->fetch();
        $consulta->close();

        if ($encontrado) {
            $actualizar = $conexion->prepare(
                "UPDATE USUARIOS SET correo_verificado = 1, token_verificacion = NULL, token_expira = NULL WHERE id_correo = ? AND correo_verificado = 0"
            );
            $actualizar->bind_param("i", $usuarioId);
            $actualizar->execute();
            $confirmacion = $actualizar->affected_rows === 1 ? "confirmada" : "invalida";
            $actualizar->close();
        }
        $conexion->close();

        // Cuenta confirmada: se inicia sesión y se envía al inicio.
        if ($confirmacion === "confirmada") {
            unset($_SESSION["verificacion_pendiente_id"], $_SESSION["verificacion_pendiente_correo"], $_SESSION["verificacion_pendiente_mail"]);
            session_regenerate_id(true);
            $_SESSION["usuario_id"] = (int) $usuarioId;
            $_SESSION["usuario_nombre"] = $nombreUsuario;
            $_SESSION["usuario_apellido"] = $apellidoUsuario;
            header("Location: index.php");
            exit;
        }
    } catch (Throwable $error) {
        error_log("Error al confirmar correo: " . $error->getMessage());
        $confirmacion = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de correo - GPU FIX & STORE</title>
    <link rel="stylesheet" href="style.css?v=20261021-8">
</head>
<body class="pagina-login">
<main class="login-main">
    <section class="login-contenedor">
        <div class="login-formulario">
            <h2>Confirmación de correo</h2>
            <?php if ($confirmacion === "confirmada"): ?>
                <p class="login-aviso">Tu correo quedó confirmado. Ya podés iniciar sesión.</p>
            <?php elseif ($confirmacion === "error"): ?>
                <p class="login-error">No pudimos completar la confirmación. Intentá nuevamente más tarde.</p>
            <?php else: ?>
                <p class="login-error">El enlace no es válido o venció. Podés pedir uno nuevo.</p>
                <a href="reenviar-confirmacion.php">Reenviar confirmación</a>
            <?php endif; ?>
            <p><a href="login.php">Ir a iniciar sesión</a></p>
        </div>
    </section>
</main>
</body>
</html>
