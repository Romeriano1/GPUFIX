<?php
session_start();
$sesionReparacion = !empty($_SESSION['usuario_id']);
$errorReparacion = '';
$mensajeReparacion = (string) ($_SESSION['mensaje_reparacion'] ?? '');
unset($_SESSION['mensaje_reparacion']);
$garantiaReparacion = '';
$placaConsultaReparacion = '';
$problemaReparacion = '';
if (empty($_SESSION['csrf_reparacion'])) $_SESSION['csrf_reparacion'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$sesionReparacion) {
        header('Location: login.php?requerido=reparacion', true, 303);
        exit;
    }
    $garantiaReparacion = is_string($_POST['garantia'] ?? null) ? $_POST['garantia'] : '';
    $placaConsultaReparacion = is_string($_POST['placa'] ?? null) ? trim($_POST['placa']) : '';
    $problemaReparacion = is_string($_POST['problema'] ?? null) ? trim($_POST['problema']) : '';
    $tokenReparacion = $_POST['csrf_reparacion'] ?? null;
    if (!is_string($tokenReparacion) || !hash_equals($_SESSION['csrf_reparacion'], $tokenReparacion)) {
        $errorReparacion = 'La solicitud venció o ya fue enviada. Revisá tus reparaciones en Mi cuenta antes de intentarlo otra vez.';
    } elseif ($placaConsultaReparacion === '' || mb_strlen($placaConsultaReparacion, 'UTF-8') > 250 || preg_match('/[\r\n]/', $placaConsultaReparacion)) {
        $errorReparacion = 'Indicá qué placa tenés, con hasta 250 caracteres.';
    } elseif ($garantiaReparacion === 'no') {
        $errorReparacion = 'GPUFIX & STORE se encarga de reparar placas en garantía. Podés pedir un presupuesto para tu nueva placa en Mi cuenta.';
    } elseif ($garantiaReparacion !== 'si') {
        $errorReparacion = 'Indicá si tu placa tiene garantía.';
    } elseif (mb_strlen($problemaReparacion, 'UTF-8') < 10 || mb_strlen($problemaReparacion, 'UTF-8') > 3000) {
        $errorReparacion = 'Contanos qué le sucede a tu placa usando entre 10 y 3000 caracteres.';
    } else {
        try {
            require_once __DIR__ . '/conexion.php';
            $idUsuarioReparacion = (int) $_SESSION['usuario_id'];
            $buscarCliente = $conexion->prepare('SELECT id_cliente FROM cliente WHERE id_correo = ? LIMIT 1');
            $buscarCliente->bind_param('i', $idUsuarioReparacion);
            $buscarCliente->execute();
            $clienteReparacion = $buscarCliente->get_result()->fetch_assoc();
            $buscarCliente->close();
            if (!$clienteReparacion) throw new RuntimeException('No encontramos el perfil de cliente asociado a tu cuenta.');
            $detalleConsultaReparacion = 'Placa: ' . $placaConsultaReparacion . "\n\nProblema: " . $problemaReparacion;
            $crearConsulta = $conexion->prepare("INSERT INTO presupuesto (id_cliente, consulta, total, estado) VALUES (?, ?, NULL, 'Consulta de garantía')");
            $crearConsulta->bind_param('is', $clienteReparacion['id_cliente'], $detalleConsultaReparacion);
            $crearConsulta->execute();
            $idConsultaReparacion = (int) $conexion->insert_id;
            $crearConsulta->close();
            $conexion->close();
            unset($_SESSION['csrf_reparacion']);
            $_SESSION['mensaje_reparacion'] = 'Consulta #' . $idConsultaReparacion . ' enviada. Podés ver la respuesta y el seguimiento desde Mi cuenta.';
            header('Location: reparar.php#consulta-reparacion', true, 303);
            exit;
        } catch (Throwable $falloReparacion) {
            error_log('Error al solicitar reparación: ' . $falloReparacion->getMessage());
            $errorReparacion = 'No pudimos registrar tu consulta. Intentá nuevamente.';
        }
    }
}
$escaparReparacion = static fn($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reparar GPU - GPU FIX & STORE</title>

    <link rel="stylesheet" href="style.css?v=20261027-consultas-reparacion">
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
                Podés iniciar una consulta por la reparación de tu placa. Para realizar la reparación,
                tenés que llevarla a una sucursal. Las reparaciones tardan <strong>48 horas o más</strong>;
                no se cuentan sábados, domingos ni feriados.
            </p>

        </section>

        <section class="seguimiento" id="consulta-reparacion">
            <?php if (!$sesionReparacion): ?>
            <div class="acceso-reparaciones">
                <h3>Iniciá sesión para consultar por una reparación</h3>

                <p>
                    Accedé a tu cuenta para consultar el estado de tus GPUs,
                    los diagnósticos y el seguimiento de cada reparación.
                </p>

                <a href="login.php?requerido=reparacion" class="boton-iniciar-sesion">
                    Iniciar sesión
                </a>

            </div>
            <?php else: ?>
            <div class="acceso-reparaciones consulta-reparacion">
                <h3>Consulta por reparación</h3>
                <?php if ($mensajeReparacion !== ''): ?><div class="mi-cuenta-mensaje exito" role="status"><?= $escaparReparacion($mensajeReparacion) ?></div><?php endif; ?>
                <?php if ($errorReparacion !== ''): ?><div class="mi-cuenta-mensaje error" role="alert"><?= $escaparReparacion($errorReparacion) ?></div><?php endif; ?>
                <form method="post" action="reparar.php#consulta-reparacion" class="presupuesto-formulario" id="formularioConsultaReparacion">
                    <input type="hidden" name="csrf_reparacion" value="<?= $escaparReparacion($_SESSION['csrf_reparacion']) ?>">
                    <div>
                        <label for="placaConsultaReparacion">¿Qué placa es?</label>
                        <input type="text" id="placaConsultaReparacion" name="placa" maxlength="250" value="<?= $escaparReparacion($placaConsultaReparacion) ?>" required>
                    </div>
                    <div>
                        <label for="garantiaReparacion">¿Tiene garantía?</label>
                        <select id="garantiaReparacion" name="garantia" required>
                            <option value="" <?= $garantiaReparacion === '' ? 'selected' : '' ?> disabled>Seleccionar</option>
                            <option value="si" <?= $garantiaReparacion === 'si' ? 'selected' : '' ?>>Sí</option>
                            <option value="no" <?= $garantiaReparacion === 'no' ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                    <div id="avisoSinGarantia" <?= $garantiaReparacion !== 'no' ? 'hidden' : '' ?>>
                        <p>GPUFIX &amp; STORE se encarga de reparar placas en garantía. Podés pedir un presupuesto para tu nueva placa en <a href="mi-cuenta.php?solicitar=presupuesto#presupuestos">Mi cuenta</a>.</p>
                    </div>
                    <div id="datosConsultaReparacion" <?= $garantiaReparacion !== 'si' ? 'hidden' : '' ?>>
                        <label for="problemaReparacion">¿Qué le sucede a tu placa?</label>
                        <textarea id="problemaReparacion" name="problema" rows="4" minlength="10" maxlength="3000" <?= $garantiaReparacion === 'si' ? 'required' : 'disabled' ?>><?= $escaparReparacion($problemaReparacion) ?></textarea>
                        <button class="pago-boton" type="submit">Enviar consulta</button>
                    </div>
                </form>
                <a class="pago-volver" href="mi-cuenta.php#reparaciones">Ver consultas y reparaciones en Mi cuenta</a>
            </div>
            <?php endif; ?>

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

    <script>
    (function () {
        const garantia = document.getElementById('garantiaReparacion');
        if (!garantia) return;
        const problema = document.getElementById('problemaReparacion');
        function cambiarGarantia() {
            const tieneGarantia = garantia.value === 'si';
            document.getElementById('avisoSinGarantia').hidden = garantia.value !== 'no';
            document.getElementById('datosConsultaReparacion').hidden = !tieneGarantia;
            problema.required = tieneGarantia;
            problema.disabled = !tieneGarantia;
        }
        garantia.addEventListener('change', cambiarGarantia);
        cambiarGarantia();
    })();
    </script>
    <script src="script.js?v=20261021-9"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
