<?php
session_start();
$sesionReparacion = !empty($_SESSION['usuario_id']);
if (empty($_SESSION['csrf_consulta_gpu'])) {
    $_SESSION['csrf_consulta_gpu'] = bin2hex(random_bytes(32));
}
$consultaCorreo = '';
$consultaProblema = '';
$consultaError = '';
$consultaMensaje = (string) ($_SESSION['consulta_gpu_mensaje'] ?? '');
unset($_SESSION['consulta_gpu_mensaje']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $consultaCorreo = is_string($_POST['correo'] ?? null) ? trim($_POST['correo']) : '';
    $consultaProblema = is_string($_POST['problema'] ?? null) ? trim($_POST['problema']) : '';
    $tokenConsulta = is_string($_POST['csrf_consulta_gpu'] ?? null) ? $_POST['csrf_consulta_gpu'] : '';
    if (!hash_equals($_SESSION['csrf_consulta_gpu'], $tokenConsulta)) {
        $consultaError = 'La solicitud venció. Volvé a enviar la consulta.';
    } elseif (strlen($consultaCorreo) > 150 || !filter_var($consultaCorreo, FILTER_VALIDATE_EMAIL)) {
        $consultaError = 'El correo no es válido. Revisalo e intentá nuevamente.';
    } elseif (mb_strlen($consultaProblema, 'UTF-8') < 10 || mb_strlen($consultaProblema, 'UTF-8') > 3000) {
        $consultaError = 'Contanos qué le sucede a tu GPU usando entre 10 y 3000 caracteres.';
    } elseif (time() - (int) ($_SESSION['consulta_gpu_ultimo_envio'] ?? 0) < 60) {
        $consultaError = 'Esperá un minuto antes de enviar otra consulta.';
    } else {
        try {
            require_once __DIR__ . '/correo-config.php';
            $cuerpoConsulta = "Consulta de reparación de GPU\n\nCorreo de contacto: " . $consultaCorreo
                . "\n\n¿Qué le sucede a la GPU?\n" . $consultaProblema;
            if (!is_callable('gpufix_enviar_correo') || !gpufix_enviar_correo('gpufixstore@gmail.com', 'Nueva consulta de reparación GPUFIX', $cuerpoConsulta)) {
                throw new RuntimeException('No se pudo enviar la consulta.');
            }
            $_SESSION['consulta_gpu_ultimo_envio'] = time();
            $_SESSION['csrf_consulta_gpu'] = bin2hex(random_bytes(32));
            $_SESSION['consulta_gpu_mensaje'] = 'Recibimos tu consulta. Te responderemos al correo que ingresaste.';
            header('Location: reparar.php#hace-tu-consulta', true, 303);
            exit;
        } catch (Throwable $errorConsulta) {
            error_log('Error al enviar consulta de GPU: ' . $errorConsulta->getMessage());
            $consultaError = 'No pudimos enviar tu consulta. Intentá nuevamente más tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reparar GPU - GPU FIX & STORE</title>

    <link rel="stylesheet" href="style.css?v=20261029-consultas">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

    <header>

        <nav class="navbar">

            <div class="navbar-izquierda">

                <a class="navbar-brand" href="index.php">
                    GPU FIX & STORE
                </a>

                <ul class="navbar-nav">

                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            Inicio
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="productos.php">
                            Productos
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link active" href="reparar.php">
                            Reparar GPU
                        </a>
                    </li>

                </ul>

            </div>

            <div class="navbar-derecha">

                <button id="themeButton" class="theme-button" type="button" aria-label="Cambiar tema">
                    ☀️
                </button>

                <?php include __DIR__ . "/menu-perfil.php"; ?>

            </div>

        </nav>

    </header>

    <main>

        <section class="reparaciones">

            <h2>Reparación de GPU</h2>

            <p class="reparaciones-intro">
                Para solicitar una reparación, acercate personalmente a cualquiera de nuestras sucursales.
                Allí recibiremos tu GPU y registraremos la reparación para que puedas consultar su estado
                desde tu cuenta.
            </p>

            <p class="reparaciones-intro">
                La reparación se registra en administración cuando llevás tu placa a una sucursal. Las reparaciones tardan <strong>48 horas o más</strong>;
                no se cuentan sábados, domingos ni feriados.
            </p>

        </section>

        <section class="seguimiento" id="hace-tu-consulta" aria-labelledby="titulo-consulta-gpu">
            <div class="acceso-reparaciones consulta-reparacion">
                <h3 id="titulo-consulta-gpu">Hacé tu consulta</h3>
                <p>No necesitás iniciar sesión. Dejanos tu correo y contanos qué le pasa a tu GPU.</p>
                <?php if ($consultaMensaje !== ''): ?>
                    <div class="alert alert-success" role="status"><?= htmlspecialchars($consultaMensaje, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if ($consultaError !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($consultaError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <form method="post" action="reparar.php#hace-tu-consulta" class="consulta-gpu-formulario">
                    <input type="hidden" name="csrf_consulta_gpu" value="<?= htmlspecialchars($_SESSION['csrf_consulta_gpu'], ENT_QUOTES, 'UTF-8') ?>">
                    <label for="consulta-correo">Correo (obligatorio)</label>
                    <input type="email" id="consulta-correo" name="correo" autocomplete="email" maxlength="150" required value="<?= htmlspecialchars($consultaCorreo, ENT_QUOTES, 'UTF-8') ?>">
                    <label for="consulta-problema">¿Qué le pasa a tu GPU?</label>
                    <textarea id="consulta-problema" name="problema" rows="5" minlength="10" maxlength="3000" required aria-describedby="consulta-ayuda"><?= htmlspecialchars($consultaProblema, ENT_QUOTES, 'UTF-8') ?></textarea>
                    <small id="consulta-ayuda">Escribí entre 10 y 3000 caracteres. Te responderemos por correo.</small>
                    <button type="submit" class="boton-iniciar-sesion">Enviar consulta</button>
                </form>
            </div>
        </section>

        <section class="seguimiento" id="seguimiento-reparacion">
            <div class="acceso-reparaciones">
                <h3>Seguimiento de tu reparación</h3>
                <p>Una vez registrada por nuestro equipo, podés ver el estado y las respuestas desde Mi cuenta.</p>
                <?php if ($sesionReparacion): ?>
                    <a href="mi-cuenta.php#reparaciones" class="boton-iniciar-sesion">Ver mis reparaciones</a>
                <?php else: ?>
                    <a href="login.php?requerido=reparacion" class="boton-iniciar-sesion">Iniciar sesión</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="reparaciones">

            <h2 class="titulo-sucursales">
                Nuestras sucursales
            </h2>

            <div class="sucursales">

                <article class="sucursal">

                    <h3>Sucursal Rivadavia</h3>

                    <p>
                        Av. Rivadavia 5376, Local 4
                    </p>

                    <div class="mapa">

                        <iframe
                            src="https://www.google.com/maps?q=Av.+Rivadavia+5376,+Buenos+Aires,+Argentina&output=embed"
                            loading="lazy"
                            allowfullscreen
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>

                    </div>

                    <a class="boton-maps"
                        href="https://www.google.com/maps/search/?api=1&query=Av.+Rivadavia+5376,+Buenos+Aires,+Argentina"
                        target="_blank"
                        rel="noopener noreferrer">
                        Ver en Google Maps
                    </a>

                </article>

                <article class="sucursal">

                    <h3>Sucursal Florida</h3>

                    <p>
                        Florida 537, CABA
                    </p>

                    <div class="mapa">

                        <iframe
                            src="https://www.google.com/maps?q=Florida+537,+Buenos+Aires,+Argentina&output=embed"
                            loading="lazy"
                            allowfullscreen
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>

                    </div>

                    <a class="boton-maps"
                        href="https://www.google.com/maps/search/?api=1&query=Florida+537,+Buenos+Aires,+Argentina"
                        target="_blank"
                        rel="noopener noreferrer">
                        Ver en Google Maps
                    </a>

                </article>

            </div>

        </section>

    </main>

    <footer>

        <p>GPU FIX & STORE - Reparación y venta de GPUs</p>

    </footer>

    <script src="script.js?v=20261021-9"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
