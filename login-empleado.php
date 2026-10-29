
<?php
session_start();

$error = "";

if (!empty($_SESSION["empleado_id"])) {
    header("Location: admin.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $correo = strtolower(trim($_POST["correo"] ?? ""));
    $contrasena = $_POST["contrasena"] ?? "";

    if ($correo === "" || $contrasena === "") {
        $error = "Completá todos los campos.";
    } else {
        try {
            require_once __DIR__ . "/conexion.php";

            $consulta = $conexion->prepare(
                "SELECT e.id_empleado, e.nombre, e.apellido, u.id_correo, u.contraseña
                 FROM empleado e
                 INNER JOIN usuarios u ON u.id_correo = e.id_correo
                 WHERE u.correo = ?
                 LIMIT 1"
            );

            $consulta->bind_param("s", $correo);
            $consulta->execute();
            $consulta->bind_result(
                $empleadoId,
                $nombre,
                $apellido,
                $idCorreo,
                $contrasenaGuardada
            );

            $encontrado = $consulta->fetch();
            $consulta->close();

            if ($encontrado && password_verify($contrasena, $contrasenaGuardada)) {
                session_regenerate_id(true);

                $_SESSION["empleado_id"] = (int) $empleadoId;
                $_SESSION["empleado_nombre"] = $nombre;
                $_SESSION["empleado_apellido"] = $apellido;

                $conexion->close();

                header("Location: admin.php");
                exit;
            }

            $conexion->close();
            $error = "Correo o contraseña incorrectos, o cuenta no habilitada como empleado.";
        } catch (Throwable $e) {
            error_log("Error en login de empleado: " . $e->getMessage());
            $error = "No se pudo verificar el acceso de empleado. Intentá nuevamente más tarde.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso de empleados - GPU FIX & STORE</title>
    <link rel="stylesheet" href="style.css?v=20261029-buscador">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>
<body class="pagina-login">

<header>
    <nav class="navbar">
        <div class="navbar-izquierda">
            <a class="navbar-brand" href="index.php">GPU FIX & STORE</a>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Inicio</a>
                </li>
            </ul>
        </div>
    </nav>
</header>

<main class="login-main">
    <section class="login-contenedor">
        <div class="login-formulario">
            <h2>Acceso de empleados</h2>
            <p class="login-descripcion">
                Ingresá con tu cuenta de GPU FIX & STORE
            </p>

            <?php if ($error !== ""): ?>
                <div class="login-error">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
                </div>
            <?php endif; ?>

            <form class="login-form" action="login-empleado.php" method="POST">
                <div class="login-campo">
                    <label for="correo">Correo electrónico</label>
                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        placeholder="Ingresá tu correo"
                        autocomplete="username"
                        required
                    >
                </div>

                <div class="login-campo">
                    <label for="contrasena">Contraseña</label>
                    <input
                        type="password"
                        id="contrasena"
                        name="contrasena"
                        placeholder="Ingresá tu contraseña"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button class="login-boton" type="submit">Ingresar</button>
            </form>

            <div class="login-enlaces">
                <a class="login-registro-boton" href="login.php">
                    Volver al login de clientes
                </a>
            </div>
        </div>
    </section>
</main>

<footer class="login-footer">
    <p>GPU FIX & STORE - Reparación y venta de GPUs</p>
</footer>

<script src="script.js?v=20261021-9"></script>
</body>
</html>
