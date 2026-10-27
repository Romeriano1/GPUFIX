
<?php
session_start();
$error = '';

if (!empty($_SESSION['gpufix_admin'])) {
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = strtolower(trim($_POST['correo'] ?? ''));
    $clave = $_POST['contrasena'] ?? '';

    if ($correo !== 'gpufixstore@gmail.com') {
        $error = 'Acceso exclusivo para el administrador.';
    } else {
        try {
            require_once __DIR__ . '/conexion.php';

            $consulta = $conexion->prepare(
                'SELECT id_correo, `contraseña`, nombre, apellido
                 FROM usuarios WHERE correo = ? LIMIT 1'
            );
            $consulta->bind_param('s', $correo);
            $consulta->execute();
            $consulta->bind_result($id, $hash, $nombre, $apellido);

            if ($consulta->fetch() && password_verify($clave, $hash)) {
                $consulta->close();
                session_regenerate_id(true);

                $_SESSION['gpufix_admin'] = true;
                $_SESSION['admin_correo'] = $correo;
                $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));

                header('Location: admin.php');
                exit;
            }

            $consulta->close();
            $error = 'Correo o contraseña incorrectos.';
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $error = 'No se pudo verificar la cuenta. Revisá la conexión.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administración - GPUFIX</title>
</head>
<body>
    <h1>GPUFIX - Administración</h1>

    <?php if ($error !== ''): ?>
        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post">
        <label for="correo">Correo administrador</label>
        <input type="email" id="correo" name="correo"
               value="gpufixstore@gmail.com" required>

        <label for="contrasena">Contraseña</label>
        <input type="password" id="contrasena"
               name="contrasena" required>

        <button type="submit">Ingresar</button>
    </form>

    <a href="index.php">Volver a la tienda</a>
</body>
</html>
