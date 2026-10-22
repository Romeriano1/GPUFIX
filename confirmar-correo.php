<?php
session_start();
$confirmacion = "invalida";
$token = trim((string) ($_GET["token"] ?? ""));

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $transaccionActiva = false;
    try {
        require_once __DIR__ . "/conexion.php";
        $tokenHash = hash("sha256", $token);
        $conexion->begin_transaction();
        $transaccionActiva = true;
        $consulta = $conexion->prepare(
            "SELECT id_pendiente, correo, `contraseña`, nombre, apellido, dni, telefono FROM registro_pendiente WHERE token_verificacion = ? AND token_expira > NOW() LIMIT 1 FOR UPDATE"
        );
        $consulta->bind_param("s", $tokenHash);
        $consulta->execute();
        $consulta->bind_result($pendienteId, $correoUsuario, $contrasenaUsuario, $nombreUsuario, $apellidoUsuario, $dniUsuario, $telefonoUsuario);
        $encontrado = $consulta->fetch();
        $consulta->close();

        if ($encontrado) {
            $crearUsuario = $conexion->prepare(
                "INSERT INTO usuarios (correo, `contraseña`, nombre, apellido, dni, telefono, correo_verificado, token_verificacion, token_expira) VALUES (?, ?, ?, ?, ?, ?, 1, NULL, NULL)"
            );
            $crearUsuario->bind_param("ssssss", $correoUsuario, $contrasenaUsuario, $nombreUsuario, $apellidoUsuario, $dniUsuario, $telefonoUsuario);
            $crearUsuario->execute();
            $usuarioId = (int) $conexion->insert_id;
            $crearUsuario->close();

            $crearCliente = $conexion->prepare("INSERT INTO cliente (id_correo) VALUES (?)");
            $crearCliente->bind_param("i", $usuarioId);
            $crearCliente->execute();
            $crearCliente->close();

            $eliminarPendiente = $conexion->prepare("DELETE FROM registro_pendiente WHERE id_pendiente = ? AND token_verificacion = ?");
            $eliminarPendiente->bind_param("is", $pendienteId, $tokenHash);
            $eliminarPendiente->execute();
            $pendienteEliminado = $eliminarPendiente->affected_rows === 1;
            $eliminarPendiente->close();

            if (!$pendienteEliminado) {
                throw new RuntimeException("El registro pendiente ya no está disponible.");
            }

            $conexion->commit();
            $transaccionActiva = false;
            $confirmacion = "confirmada";
        } else {
            $limpiarExpirado = $conexion->prepare("DELETE FROM registro_pendiente WHERE token_verificacion = ? AND token_expira <= NOW()");
            $limpiarExpirado->bind_param("s", $tokenHash);
            $limpiarExpirado->execute();
            $limpiarExpirado->close();
            $conexion->commit();
            $transaccionActiva = false;
        }
        $conexion->close();

        // Cuenta confirmada: iniciar sesión y mostrar la página de éxito.
        if ($confirmacion === "confirmada") {
            unset($_SESSION["verificacion_pendiente_correo"], $_SESSION["verificacion_pendiente_mail"]);
            session_regenerate_id(true);
            $_SESSION["usuario_id"] = (int) $usuarioId;
            $_SESSION["usuario_nombre"] = $nombreUsuario;
            $_SESSION["usuario_apellido"] = $apellidoUsuario;
            header("Location: mailconfirmado.php");
            exit;
        }
    } catch (Throwable $error) {
        if (isset($conexion)) {
            try {
                if ($transaccionActiva) {
                    $conexion->rollback();
                }
                $conexion->close();
            } catch (Throwable $errorRollback) {
                // La conexión ya puede haberse cerrado.
            }
        }
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
            <?php if ($confirmacion === "error"): ?>
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
