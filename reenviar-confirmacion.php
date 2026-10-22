<?php
session_start();
$mensaje = "";
$correo = strtolower(trim((string) ($_POST["correo"] ?? "")));

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "Ingresá un correo electrónico válido.";
    } else {
        try {
            require_once __DIR__ . "/conexion.php";
            $consulta = $conexion->prepare(
            "SELECT id_correo, nombre, correo_verificado FROM usuarios WHERE correo = ? LIMIT 1"
            );
            $consulta->bind_param("s", $correo);
            $consulta->execute();
            $consulta->bind_result($usuarioId, $nombre, $verificado);
            $encontrado = $consulta->fetch();
            $consulta->close();
            $token = "";
            $enviado = false;
            $sePuedeEnviar = $encontrado && (int) $verificado !== 1;

            if ($sePuedeEnviar) {
                $conexion->begin_transaction();
                try {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash("sha256", $token);
                    $expira = date("Y-m-d H:i:s", time() + 86400);
                    $actualizar = $conexion->prepare(
                        "UPDATE usuarios SET token_verificacion = ?, token_expira = ? WHERE id_correo = ? AND correo_verificado = 0"
                    );
                    $actualizar->bind_param("ssi", $tokenHash, $expira, $usuarioId);
                    $actualizar->execute();
                    $sePuedeEnviar = $actualizar->affected_rows === 1;
                    $actualizar->close();

                    if ($sePuedeEnviar) {
                        require_once __DIR__ . "/correo.php";
                        $enviado = correo_enviar_confirmacion($correo, $nombre, $token);
                    }

                    if ($enviado) {
                        $conexion->commit();
                    } else {
                        $conexion->rollback();
                    }
                } catch (Throwable $error) {
                    $conexion->rollback();
                    throw $error;
                }
            }
            $conexion->close();

            if ($sePuedeEnviar) {
                $mensaje = $enviado
                    ? "Si la cuenta está pendiente de confirmación, enviamos un enlace nuevo. Revisá también la carpeta de correo no deseado."
                    : "La cuenta sigue pendiente de confirmación, pero el envío de correo no está disponible. Revisá la configuración SMTP e intentá de nuevo.";
            } else {
                $mensaje = "Si la cuenta está pendiente de confirmación, enviamos un enlace nuevo. Revisá también la carpeta de correo no deseado.";
            }
        } catch (Throwable $error) {
            error_log("Error al reenviar confirmación: " . $error->getMessage());
            $mensaje = "No pudimos procesar la solicitud. Intentá nuevamente más tarde.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reenviar confirmación - GPU FIX & STORE</title>
    <link rel="stylesheet" href="style.css?v=20261021-8">
</head>
<body class="pagina-login">
<main class="login-main">
    <section class="login-contenedor">
        <div class="login-formulario">
            <h2>Reenviar confirmación</h2>
            <p class="login-descripcion">Ingresá el correo con el que creaste tu cuenta.</p>
            <?php if ($mensaje !== ""): ?><div class="login-aviso" role="status"><?= htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8") ?></div><?php endif; ?>
            <form class="login-form" action="reenviar-confirmacion.php" method="POST">
                <div class="login-campo">
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" autocomplete="email" value="<?= htmlspecialchars($correo, ENT_QUOTES, "UTF-8") ?>" required>
                </div>
                <button class="login-boton" type="submit">Enviar enlace</button>
            </form>
            <p><a href="login.php">Volver a iniciar sesión</a></p>
        </div>
    </section>
</main>
</body>
</html>
