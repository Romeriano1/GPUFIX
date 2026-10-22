<?php
session_start();
$requeridoCarrito = (($_GET["requerido"] ?? "") === "carrito") || (($_POST["requerido"] ?? "") === "carrito");

if (!empty($_SESSION["usuario_id"])) {
    header("Location: mi-cuenta.php");
    exit;
}

$registroError = "";
$registroMailEnviado = null;
$etapaRegistro = "validar_formulario";
$transaccionActiva = false;
$nombre = "";
$apellido = "";
$dni = "";
$telefono = "";
$correo = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"] ?? "");
    $apellido = trim($_POST["apellido"] ?? "");
    $dni = trim($_POST["dni"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $correo = strtolower(trim($_POST["correo"] ?? ""));
    $contrasena = $_POST["contrasena"] ?? "";
    $confirmarContrasena = $_POST["confirmar_contrasena"] ?? "";

    if ($nombre === "" || $apellido === "" || $correo === "" || $contrasena === "" || $confirmarContrasena === "") {
        $registroError = "datos";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $registroError = "correo_invalido";
    } elseif (strlen($contrasena) < 8) {
        $registroError = "contrasena_corta";
    } elseif (strlen($contrasena) > 72) {
        $registroError = "contrasena_larga";
    } elseif ($contrasena !== $confirmarContrasena) {
        $registroError = "contrasenas_distintas";
    } else {
        try {
            $etapaRegistro = "conectar_base";
            require_once __DIR__ . "/conexion.php";
            $conexion->begin_transaction();
            $transaccionActiva = true;

            $etapaRegistro = "limpiar_registros_vencidos";
            $conexion->query("DELETE FROM registro_pendiente WHERE token_expira IS NOT NULL AND token_expira <= NOW()");

            $etapaRegistro = "consultar_correo";
            $consultaCorreo = $conexion->prepare("SELECT id_correo FROM usuarios WHERE correo = ? LIMIT 1");
            $consultaCorreo->bind_param("s", $correo);
            $consultaCorreo->execute();
            $consultaCorreo->store_result();
            $correoExiste = $consultaCorreo->num_rows > 0;
            $consultaCorreo->close();

            if ($correoExiste) {
                $conexion->rollback();
                $transaccionActiva = false;
                $registroError = "correo_existente";
            } else {
                // Guardar solo el hash; password_verify() lo valida en el login.
                $contrasenaGuardar = password_hash($contrasena, PASSWORD_DEFAULT);
                if ($contrasenaGuardar === false) {
                    throw new RuntimeException("No se pudo proteger la contraseña.");
                }
                $dniGuardar = $dni === "" ? null : $dni;
                $telefonoGuardar = $telefono === "" ? null : $telefono;
                $tokenVerificacion = bin2hex(random_bytes(32));
                $tokenVerificacionHash = hash("sha256", $tokenVerificacion);
                $tokenExpira = date("Y-m-d H:i:s", time() + 86400);

                $etapaRegistro = "guardar_registro_pendiente";
                $guardarPendiente = $conexion->prepare(
                    "INSERT INTO registro_pendiente (correo, `contraseña`, nombre, apellido, dni, telefono, token_verificacion, token_expira) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `contraseña` = VALUES(`contraseña`), nombre = VALUES(nombre), apellido = VALUES(apellido), dni = VALUES(dni), telefono = VALUES(telefono), token_verificacion = VALUES(token_verificacion), token_expira = VALUES(token_expira)"
                );
                $guardarPendiente->bind_param("ssssssss", $correo, $contrasenaGuardar, $nombre, $apellido, $dniGuardar, $telefonoGuardar, $tokenVerificacionHash, $tokenExpira);
                $guardarPendiente->execute();
                $guardarPendiente->close();

                $etapaRegistro = "confirmar_transaccion";
                $conexion->commit();
                $transaccionActiva = false;
                $conexion->close();

                $etapaRegistro = "enviar_correo_confirmacion";
                require_once __DIR__ . "/correo.php";
                $registroMailEnviado = correo_enviar_confirmacion($correo, $nombre, $tokenVerificacion);
                $_SESSION["verificacion_pendiente_correo"] = $correo;
                $_SESSION["verificacion_pendiente_mail"] = $registroMailEnviado;
                header("Location: verificacion-pendiente.php");
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
                    // La conexión puede haber fallado antes de iniciar una transacción.
                }
            }

            $mensajeTecnico = preg_replace('/[\r\n\t]+/', ' ', $error->getMessage());
            $mensajeTecnico = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[correo oculto]', $mensajeTecnico);
            $codigoTecnico = $error instanceof mysqli_sql_exception ? (int) $error->getCode() : 0;
            $entradaTecnica = sprintf(
                "[%s] etapa=%s %s (código %d): %s\n",
                date('c'),
                $etapaRegistro,
                get_class($error),
                $codigoTecnico,
                $mensajeTecnico
            );
            error_log("Error al registrar una cuenta: " . $mensajeTecnico);
            @file_put_contents(__DIR__ . "/registro-error.log", $entradaTecnica, FILE_APPEND | LOCK_EX);
            $registroError = ($error instanceof mysqli_sql_exception && (int) $error->getCode() === 1062)
                ? "correo_existente"
                : "conexion";
        }
    }
}

function escaparRegistro($valor)
{
    return htmlspecialchars($valor, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta - GPU FIX & STORE</title>
    <link rel="stylesheet" href="style.css?v=20261021-7">
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
        <div class="login-formulario registro-formulario">
            <h2>Crear cuenta</h2>
            <p class="login-descripcion">Registrate en GPU FIX & STORE</p>

            <?php if ($registroError !== ""): ?>
                <div class="login-error" role="alert">
                    <?php
                    $mensajesError = [
                        "datos" => "Completá los campos obligatorios.",
                        "correo_invalido" => "Ingresá un correo electrónico válido.",
                        "contrasena_corta" => "La contraseña debe tener al menos 8 caracteres.",
                        "contrasena_larga" => "La contraseña no puede superar los 72 bytes.",
                        "contrasenas_distintas" => "Las contraseñas no coinciden.",
                        "correo_existente" => "Ya existe una cuenta registrada con ese correo.",
                        "conexion" => "No pudimos completar el registro. Revisá la conexión con la base de datos e intentá nuevamente."
                    ];
                    echo escaparRegistro($mensajesError[$registroError] ?? "No se pudo crear la cuenta.");
                    ?>
                </div>
            <?php endif; ?>

            <form class="login-form" action="registro.php" method="POST">
                <?php if ($requeridoCarrito): ?>
                    <input type="hidden" name="requerido" value="carrito">
                <?php endif; ?>
                <div class="registro-grid">
                    <div class="login-campo">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" maxlength="100" autocomplete="given-name" value="<?= escaparRegistro($nombre) ?>" required>
                    </div>

                    <div class="login-campo">
                        <label for="apellido">Apellido *</label>
                        <input type="text" id="apellido" name="apellido" maxlength="100" autocomplete="family-name" value="<?= escaparRegistro($apellido) ?>" required>
                    </div>

                    <div class="login-campo">
                        <label for="dni">DNI</label>
                        <input type="text" id="dni" name="dni" maxlength="20" autocomplete="off" value="<?= escaparRegistro($dni) ?>">
                    </div>

                    <div class="login-campo">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" maxlength="30" autocomplete="tel" value="<?= escaparRegistro($telefono) ?>">
                    </div>

                    <div class="login-campo registro-campo-completo">
                        <label for="correo">Correo electrónico *</label>
                        <input type="email" id="correo" name="correo" maxlength="150" autocomplete="email" value="<?= escaparRegistro($correo) ?>" required>
                    </div>

                    <div class="login-campo">
                        <label for="contrasena">Contraseña *</label>
                        <input type="password" id="contrasena" name="contrasena" minlength="8" autocomplete="new-password" required>
                    </div>

                    <div class="login-campo">
                        <label for="confirmar_contrasena">Repetir contraseña *</label>
                        <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" minlength="8" autocomplete="new-password" required>
                    </div>

                </div>

                <button class="login-boton" type="submit">Crear cuenta</button>
            </form>

            <div class="login-enlaces">
                <p>¿Ya tenés una cuenta?</p>
                <a href="login.php">Iniciar sesión</a>
            </div>
        </div>
    </section>
</main>

<footer class="login-footer">
    <p>GPU FIX & STORE - Reparación y venta de GPUs</p>
</footer>

<script src="script.js?v=20261021-9"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
