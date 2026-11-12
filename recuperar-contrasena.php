<?php
session_start();
header('Cache-Control: no-store');
$acceso = ($_GET['acceso'] ?? '') === 'empleado' ? 'empleado' : 'cliente';
$login = $acceso === 'empleado' ? 'login-empleado.php' : 'login.php';
$ruta = 'recuperar-contrasena.php?acceso=' . $acceso;
if (empty($_SESSION['csrf_recuperar'])) $_SESSION['csrf_recuperar'] = bin2hex(random_bytes(32));
$error = '';
$mensaje = $_SESSION['recuperar_mensaje'] ?? '';
unset($_SESSION['recuperar_mensaje']);
$completado = !empty($_SESSION['recuperar_completado']);
unset($_SESSION['recuperar_completado']);
$escapar = static fn($valor) => htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
if (isset($_SESSION['recuperacion']) && $_SESSION['recuperacion']['vence'] <= time()) {
    unset($_SESSION['recuperacion']);
    $error = 'El código venció. Pedí uno nuevo.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $completado = false;
    try {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf_recuperar'], $_POST['csrf'])) throw new RuntimeException('La solicitud venció. Recargá la página.');
        $accion = $_POST['accion'] ?? '';
        if ($accion === 'solicitar') {
            if (time() < ($_SESSION['recuperar_proximo_envio'] ?? 0)) throw new RuntimeException('Esperá un minuto antes de pedir otro código.');
            $correo = is_string($_POST['correo'] ?? null) ? strtolower(trim($_POST['correo'])) : '';
            $dni = is_string($_POST['dni'] ?? null) ? trim($_POST['dni']) : '';
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 150 || !preg_match('/^[0-9]{7,8}$/D', $dni)) throw new RuntimeException('Ingresá un correo válido y un DNI de 7 u 8 números.');
            $_SESSION['recuperar_proximo_envio'] = time() + 60;
            unset($_SESSION['recuperacion']);
            require_once __DIR__ . '/conexion.php';
            $sql = 'SELECT u.id_correo,u.correo,u.`contraseña` FROM usuarios u WHERE u.correo=? AND u.dni=?'
                . ($acceso === 'empleado' ? ' AND EXISTS (SELECT 1 FROM empleado e WHERE e.id_correo=u.id_correo)' : '') . ' LIMIT 1';
            $consulta = $conexion->prepare($sql);
            $consulta->bind_param('ss', $correo, $dni);
            $consulta->execute();
            $usuario = $consulta->get_result()->fetch_assoc();
            $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            // También mostramos el paso del código si no coincide la cuenta, sin revelar registros.
            $recuperacion = ['id' => $usuario ? (int)$usuario['id_correo'] : 0, 'correo' => $correo, 'anterior' => $usuario['contraseña'] ?? '', 'codigo' => hash('sha256', $codigo), 'vence' => time() + 600, 'intentos' => 0, 'acceso' => $acceso];
            if ($usuario) {
                ob_start();
                try { require_once __DIR__ . '/correo-config.php'; } finally { ob_end_clean(); }
                if (!gpufix_enviar_correo($usuario['correo'], 'Código para cambiar tu contraseña - GPUFIX', "Tu código es: " . $codigo . "\n\nVence en 10 minutos y se puede usar una sola vez. Ingresalo en el mismo navegador donde lo solicitaste.\nSi no pediste cambiar tu contraseña, ignorá este mensaje.")) throw new RuntimeException('No pudimos enviar el código. Intentá nuevamente en un minuto.');
            }
            $_SESSION['recuperacion'] = $recuperacion;
            $_SESSION['recuperar_mensaje'] = 'Si el correo y el DNI corresponden a una cuenta habilitada, recibirás un código de 6 dígitos. Revisá también el correo no deseado.';
            header('Location: ' . $ruta, true, 303);
            exit;
        } elseif ($accion === 'cambiar') {
            $pendiente = $_SESSION['recuperacion'] ?? null;
            if (!$pendiente || $pendiente['acceso'] !== $acceso || $pendiente['vence'] <= time()) throw new RuntimeException('Pedí un nuevo código para este acceso.');
            $clave = is_string($_POST['contrasena'] ?? null) ? $_POST['contrasena'] : '';
            $confirmacion = is_string($_POST['confirmacion'] ?? null) ? $_POST['confirmacion'] : '';
            if (strlen($clave) < 8 || strlen($clave) > 72 || $clave !== $confirmacion) throw new RuntimeException('Las contraseñas deben coincidir y tener entre 8 y 72 bytes.');
            $codigo = is_string($_POST['codigo'] ?? null) ? trim($_POST['codigo']) : '';
            $_SESSION['recuperacion']['intentos']++;
            if (!preg_match('/^[0-9]{6}$/D', $codigo) || !hash_equals($pendiente['codigo'], hash('sha256', $codigo)) || !$pendiente['id']) {
                if ($_SESSION['recuperacion']['intentos'] >= 5) {
                    unset($_SESSION['recuperacion']);
                    throw new RuntimeException('Se agotaron los 5 intentos. Pedí un nuevo código.');
                }
                throw new RuntimeException('El código es incorrecto.');
            }
            require_once __DIR__ . '/conexion.php';
            $hash = password_hash($clave, PASSWORD_DEFAULT);
            // Comparar el hash anterior también invalida códigos de otras sesiones tras un cambio.
            $sql = 'UPDATE usuarios u SET u.`contraseña`=? WHERE u.id_correo=? AND u.correo=? AND BINARY u.`contraseña`=?'
                . ($acceso === 'empleado' ? ' AND EXISTS (SELECT 1 FROM empleado e WHERE e.id_correo=u.id_correo)' : '');
            $actualizar = $conexion->prepare($sql);
            $actualizar->bind_param('siss', $hash, $pendiente['id'], $pendiente['correo'], $pendiente['anterior']);
            $actualizar->execute();
            unset($_SESSION['recuperacion']);
            if ($actualizar->affected_rows !== 1) throw new RuntimeException('La cuenta cambió o el código ya no es válido. Pedí uno nuevo.');
            session_regenerate_id(true);
            $_SESSION['csrf_recuperar'] = bin2hex(random_bytes(32));
            $_SESSION['recuperar_completado'] = true;
            header('Location: ' . $ruta, true, 303);
            exit;
        } else {
            throw new RuntimeException('Solicitud no válida.');
        }
    } catch (Throwable $fallo) {
        if ($fallo instanceof RuntimeException && !($fallo instanceof mysqli_sql_exception)) $error = $fallo->getMessage();
        else { error_log('Recuperación de contraseña: ' . $fallo->getMessage()); $error = 'No pudimos completar la solicitud. Intentá nuevamente.'; }
    }
}
$pendiente = $_SESSION['recuperacion'] ?? null;
$mostrarCodigo = $pendiente && $pendiente['acceso'] === $acceso;
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Recuperar contraseña - GPUFIX</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="style.css?v=20261106-navbar"></head>
<body class="pagina-login"><header><?php include __DIR__ . '/navbar.php'; ?></header>
<main class="login-main"><section class="login-contenedor"><div class="login-formulario">
<h1 class="h3">Olvidé mi contraseña</h1><p class="login-descripcion">Acceso de <?= $acceso === 'empleado' ? 'empleados' : 'clientes' ?></p>
<?php if ($error): ?><div class="login-error" role="alert"><?= $escapar($error) ?></div><?php endif; ?>
<?php if ($mensaje): ?><p role="status"><?= $escapar($mensaje) ?></p><?php endif; ?>
<?php if ($completado): ?><p role="status">Tu contraseña fue actualizada. El código ya no se puede volver a usar. Ya podés iniciar sesión.</p>
<?php else: ?>
<?php if ($mostrarCodigo): ?>
<form class="login-form" method="post" action="<?= $ruta ?>"><input type="hidden" name="csrf" value="<?= $escapar($_SESSION['csrf_recuperar']) ?>"><input type="hidden" name="accion" value="cambiar">
<div class="login-campo"><label for="codigo">Código de 6 dígitos</label><input id="codigo" name="codigo" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required></div>
<p>Vence en 10 minutos. Usalo en este mismo navegador. Tenés hasta 5 intentos.</p>
<div class="login-campo"><label for="nuevaClave">Nueva contraseña</label><input id="nuevaClave" type="password" name="contrasena" autocomplete="new-password" minlength="8" maxlength="72" required></div>
<div class="login-campo"><label for="confirmacion">Repetí la nueva contraseña</label><input id="confirmacion" type="password" name="confirmacion" autocomplete="new-password" minlength="8" maxlength="72" required></div>
<button class="login-boton" type="submit">Cambiar contraseña</button></form>
<hr><h2 class="h5">Pedir otro código</h2><p>Al enviar uno nuevo, el anterior deja de funcionar.</p>
<?php endif; ?>
<form class="login-form" method="post" action="<?= $ruta ?>"><input type="hidden" name="csrf" value="<?= $escapar($_SESSION['csrf_recuperar']) ?>"><input type="hidden" name="accion" value="solicitar">
<div class="login-campo"><label for="correoRecuperar">Correo de tu cuenta</label><input id="correoRecuperar" type="email" name="correo" autocomplete="email" maxlength="150" required></div>
<div class="login-campo"><label for="dniRecuperar">DNI</label><input id="dniRecuperar" name="dni" inputmode="numeric" pattern="[0-9]{7,8}" minlength="7" maxlength="8" required></div>
<button class="login-boton" type="submit">Enviar código</button></form>
<?php endif; ?>
<div class="login-enlaces"><a class="login-registro-boton" href="<?= $login ?>">Volver a iniciar sesión</a></div>
</div></section></main><script src="script.js?v=20261104-responsive"></script></body></html>
