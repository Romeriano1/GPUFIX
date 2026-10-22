<?php
session_start();
$requeridoCarrito = (($_GET["requerido"] ?? "") === "carrito") || (($_POST["requerido"] ?? "") === "carrito");

if (!empty($_SESSION["usuario_id"])) {
    header("Location: mi-cuenta.php");
    exit;
}

$registroError = "";
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
    } elseif ($contrasena !== $confirmarContrasena) {
        $registroError = "contrasenas_distintas";
    } else {
        try {
            require_once __DIR__ . "/conexion.php";
            $conexion->begin_transaction();

            $consultaCorreo = $conexion->prepare("SELECT id_correo FROM USUARIOS WHERE correo = ? LIMIT 1");
            $consultaCorreo->bind_param("s", $correo);
            $consultaCorreo->execute();
            $consultaCorreo->store_result();
            $correoExiste = $consultaCorreo->num_rows > 0;
            $consultaCorreo->close();

            if ($correoExiste) {
                $conexion->rollback();
                $registroError = "correo_existente";
            } else {
                $contrasenaGuardar = $contrasena;
                $dniGuardar = $dni === "" ? null : $dni;
                $telefonoGuardar = $telefono === "" ? null : $telefono;

                $crearUsuario = $conexion->prepare(
                    "INSERT INTO USUARIOS (correo, `contraseña`, nombre, apellido, dni, telefono) VALUES (?, ?, ?, ?, ?, ?)"
                );
                $crearUsuario->bind_param("ssssss", $correo, $contrasenaGuardar, $nombre, $apellido, $dniGuardar, $telefonoGuardar);
                $crearUsuario->execute();
                $idCorreo = $conexion->insert_id;
                $crearUsuario->close();

                $crearCliente = $conexion->prepare("INSERT INTO CLIENTE (id_correo) VALUES (?)");
                $crearCliente->bind_param("i", $idCorreo);
                $crearCliente->execute();
                $crearCliente->close();

                $conexion->commit();
                $conexion->close();

                header("Location: login.php?registrado=1" . ($requeridoCarrito ? "&requerido=carrito" : ""));
                exit;
            }
        } catch (Throwable $error) {
            if (isset($conexion)) {
                try {
                    $conexion->rollback();
                    $conexion->close();
                } catch (Throwable $errorRollback) {
                    // La conexión puede haber fallado antes de iniciar una transacción.
                }
            }

            error_log("Error al registrar una cuenta: " . $error->getMessage());
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
                <li class="nav-item"><a class="nav-link" href="carrito.php">Ver carrito</a></li>
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
            <a href="login.php" class="cuenta-button" aria-label="Iniciar sesión">👤</a>
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

<script src="script.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
