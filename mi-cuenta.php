<?php
session_start();

if (empty($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$usuarioId = (int) $_SESSION["usuario_id"];
$errorCuenta = "";
$nombre = $apellido = $correo = "";
$facturas = [];
$presupuestos = [];
$mensajeCuenta = $_SESSION["mensaje_cuenta"] ?? "";
unset($_SESSION["mensaje_cuenta"]);

if (empty($_SESSION["csrf_mi_cuenta"])) {
    $_SESSION["csrf_mi_cuenta"] = bin2hex(random_bytes(32));
}
$csrfMiCuenta = $_SESSION["csrf_mi_cuenta"];
$editarCuenta = $_GET["editar"] ?? "";
if (!in_array($editarCuenta, ["correo", "contrasena"], true)) {
    $editarCuenta = "";
}
$correoNuevo = "";
$accion = "";
$solicitarPresupuesto = (($_GET["solicitar"] ?? "") === "presupuesto") || (($_POST["accion"] ?? "") === "solicitar_presupuesto");
$motivoPresupuesto = "";
if (empty($_SESSION["csrf_presupuesto"])) {
    $_SESSION["csrf_presupuesto"] = bin2hex(random_bytes(32));
}

try {
    require_once __DIR__ . "/conexion.php";

    $consultaCuenta = $conexion->prepare(
        "SELECT u.nombre, u.apellido, u.correo, u.`contraseña`, c.id_cliente
         FROM usuarios u
         INNER JOIN cliente c ON c.id_correo = u.id_correo
         WHERE u.id_correo = ? LIMIT 1"
    );
    $consultaCuenta->bind_param("i", $usuarioId);
    $consultaCuenta->execute();
    $consultaCuenta->bind_result($nombre, $apellido, $correo, $hashActual, $clienteId);
    $cuentaExiste = $consultaCuenta->fetch();
    $consultaCuenta->close();

    if (!$cuentaExiste) {
        $conexion->close();
        header("Location: cerrar-sesion.php");
        exit;
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "solicitar_presupuesto") {
        $accion = "solicitar_presupuesto";
        $motivoPresupuesto = is_string($_POST["motivo"] ?? null) ? trim($_POST["motivo"]) : "";
        $tokenPresupuesto = $_POST["csrf_presupuesto"] ?? null;
        if (!is_string($tokenPresupuesto) || !hash_equals($_SESSION["csrf_presupuesto"], $tokenPresupuesto)) {
            $errorCuenta = "La solicitud venció o ya fue enviada. Revisá tus presupuestos antes de intentar otra vez.";
        } elseif (mb_strlen($motivoPresupuesto, "UTF-8") < 10 || mb_strlen($motivoPresupuesto, "UTF-8") > 3000) {
            $errorCuenta = "Escribí tu motivo o consulta usando entre 10 y 3000 caracteres.";
        } else {
            $crearConsulta = $conexion->prepare("INSERT INTO presupuesto (id_cliente, consulta, total, estado) VALUES (?, ?, NULL, 'Pendiente de respuesta')");
            $crearConsulta->bind_param("is", $clienteId, $motivoPresupuesto);
            $crearConsulta->execute();
            $idConsulta = (int) $conexion->insert_id;
            $crearConsulta->close();
            unset($_SESSION["csrf_presupuesto"]);
            $_SESSION["mensaje_cuenta"] = "Consulta #" . $idConsulta . " enviada. Podés ver nuestra respuesta en tus presupuestos.";
            header("Location: mi-cuenta.php#presupuestos", true, 303);
            exit;
        }
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && $accion !== "solicitar_presupuesto") {
        $csrfEnviado = $_POST["csrf_token"] ?? "";
        $accion = $_POST["accion"] ?? "";
        $editarCuenta = $accion === "cambiar_correo" ? "correo" : ($accion === "cambiar_contrasena" ? "contrasena" : "");
        $correoNuevo = strtolower(trim($_POST["correo_nuevo"] ?? ""));
        $contrasenaActual = $_POST["contrasena_actual"] ?? "";
        $contrasenaNueva = $_POST["contrasena_nueva"] ?? "";
        $confirmarContrasena = $_POST["confirmar_contrasena"] ?? "";

        if (!hash_equals($csrfMiCuenta, $csrfEnviado)) {
            $errorCuenta = "La sesión del formulario venció. Recargá la página e intentá otra vez.";
        } elseif (!in_array($accion, ["cambiar_contrasena", "cambiar_correo"], true)) {
            $errorCuenta = "No se reconoció la acción solicitada.";
        } elseif ($contrasenaActual === "") {
            $errorCuenta = "Ingresá tu contraseña actual para guardar el cambio.";
        } elseif (!password_verify($contrasenaActual, $hashActual)) {
            $errorCuenta = "La contraseña actual no es correcta.";
        } elseif ($accion === "cambiar_correo") {
            if (strlen($correoNuevo) > 150 || !filter_var($correoNuevo, FILTER_VALIDATE_EMAIL)) {
                $errorCuenta = "El correo electrónico no es válido. Ingresá una dirección con @ y un dominio válido.";
            } elseif ($correoNuevo === strtolower($correo)) {
                $errorCuenta = "Ingresá un correo diferente del actual.";
            } else {
                $consultaCorreo = $conexion->prepare("SELECT id_correo FROM usuarios WHERE correo = ? AND id_correo <> ? LIMIT 1");
                $consultaCorreo->bind_param("si", $correoNuevo, $usuarioId);
                $consultaCorreo->execute();
                $consultaCorreo->store_result();
                $correoOcupado = $consultaCorreo->num_rows > 0;
                $consultaCorreo->close();
                if ($correoOcupado) {
                    $errorCuenta = "Ya existe una cuenta registrada con ese correo.";
                } else {
                    $actualizarCorreo = $conexion->prepare("UPDATE usuarios SET correo = ? WHERE id_correo = ?");
                    $actualizarCorreo->bind_param("si", $correoNuevo, $usuarioId);
                    $actualizarCorreo->execute();
                    $actualizarCorreo->close();
                    $conexion->close();
                    $_SESSION["mensaje_cuenta"] = "El correo se cambió correctamente. Usá el nuevo correo para iniciar sesión.";
                    header("Location: mi-cuenta.php#seguridad");
                    exit;
                }
            }
        } elseif ($contrasenaNueva === "" || $confirmarContrasena === "") {
            $errorCuenta = "Completá los campos de la nueva contraseña y su repetición.";
        } elseif (strlen($contrasenaNueva) < 8) {
            $errorCuenta = "La contraseña nueva debe tener al menos 8 caracteres.";
        } elseif (strlen($contrasenaNueva) > 72) {
            $errorCuenta = "La contraseña nueva no puede superar los 72 bytes.";
        } elseif ($contrasenaNueva !== $confirmarContrasena) {
            $errorCuenta = "La nueva contraseña y su repetición no coinciden.";
        } elseif (password_verify($contrasenaNueva, $hashActual)) {
            $errorCuenta = "Elegí una contraseña distinta de la actual.";
        } else {
            $nuevoHash = password_hash($contrasenaNueva, PASSWORD_DEFAULT);
            if ($nuevoHash === false) {
                throw new RuntimeException("No se pudo proteger la contraseña nueva.");
            }

            $actualizarContrasena = $conexion->prepare(
                "UPDATE usuarios SET `contraseña` = ? WHERE id_correo = ?"
            );
            $actualizarContrasena->bind_param("si", $nuevoHash, $usuarioId);
            $actualizarContrasena->execute();
            $actualizarContrasena->close();
            $conexion->close();

            $_SESSION["mensaje_cuenta"] = "La contraseña se cambió correctamente.";
            header("Location: mi-cuenta.php#seguridad");
            exit;
        }
    }

    $facturas = [];
    $consultaFacturas = $conexion->prepare(
        "SELECT id_factura, numero_factura, fecha_emision, total
         FROM factura WHERE id_cliente = ? ORDER BY fecha_emision DESC, id_factura DESC"
    );
    $consultaFacturas->bind_param("i", $clienteId);
    $consultaFacturas->execute();
    $resultadoFacturas = $consultaFacturas->get_result();
    while ($factura = $resultadoFacturas->fetch_assoc()) {
        $facturas[] = $factura;
    }
    $consultaFacturas->close();

    $presupuestos = [];
    $consultaPresupuestos = $conexion->prepare(
        "SELECT p.id_presupuesto, p.fecha_emision, p.total, p.estado,
                NULL AS id_orden_reparacion, p.consulta AS motivo, NULL AS marca, NULL AS modelo,
                p.respuesta, p.fecha_respuesta
         FROM presupuesto p
         WHERE p.id_cliente = ? AND p.id_orden_reparacion IS NULL
         UNION ALL
         SELECT p.id_presupuesto, COALESCE(p.fecha_emision, o.fecha_ingreso) AS fecha_emision,
                p.total, COALESCE(p.estado, o.estado) AS estado,
                o.id_orden_reparacion, o.motivo, g.marca, g.modelo, p.respuesta, p.fecha_respuesta
         FROM orden_reparacion o
         INNER JOIN gpu_cliente g ON g.id_gpu_cliente = o.id_gpu_cliente
         LEFT JOIN presupuesto p ON p.id_orden_reparacion = o.id_orden_reparacion
         WHERE g.id_cliente = ?
         ORDER BY fecha_emision DESC, id_presupuesto DESC"
    );
    $consultaPresupuestos->bind_param("ii", $clienteId, $clienteId);
    $consultaPresupuestos->execute();
    $resultadoPresupuestos = $consultaPresupuestos->get_result();
    while ($presupuesto = $resultadoPresupuestos->fetch_assoc()) {
        $presupuestos[] = $presupuesto;
    }
    $consultaPresupuestos->close();
    $conexion->close();
} catch (Throwable $error) {
    if (isset($conexion) && $conexion instanceof mysqli) {
        try {
            $conexion->close();
        } catch (Throwable $errorCierre) {
        }
    }
    error_log("Error al cargar Mi cuenta: " . $error->getMessage());
    $errorCuenta = $accion === "solicitar_presupuesto"
        ? "No pudimos registrar tu consulta. Intentá nuevamente."
        : (($accion === "cambiar_correo" && $error instanceof mysqli_sql_exception && (int) $error->getCode() === 1062)
        ? "Ya existe una cuenta registrada con ese correo."
        : "No pudimos cargar tu cuenta. Intentá nuevamente más tarde.");
    $facturas = [];
    $presupuestos = [];
}

function escaparCuenta($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function formatoFechaCuenta($fecha)
{
    $marcaTiempo = strtotime((string) $fecha);
    return $marcaTiempo ? date("d/m/Y H:i", $marcaTiempo) : "—";
}

function formatoImporteCuenta($importe)
{
    return "$ " . number_format((float) $importe, 2, ",", ".");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi cuenta - GPU FIX & STORE</title>
    <link rel="stylesheet" href="style.css?v=20261027-carrito">
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

<main class="mi-cuenta-main">
    <section class="mi-cuenta-encabezado">
        <div>
            <p class="mi-cuenta-etiqueta">MI CUENTA</p>
            <h1><?= escaparCuenta(trim($nombre . " " . $apellido)) ?></h1>
            <p>Acá podés consultar tus comprobantes y gestionar tu acceso.</p>
        </div>
        <div class="mi-cuenta-datos">
            <span>Correo</span>
            <strong><?= escaparCuenta($correo) ?></strong>
        </div>
    </section>

    <?php if ($errorCuenta !== "" && $accion !== "solicitar_presupuesto"): ?>
        <div class="mi-cuenta-mensaje error" role="alert"><?= escaparCuenta($errorCuenta) ?></div>
    <?php elseif ($mensajeCuenta !== ""): ?>
        <div class="mi-cuenta-mensaje exito" role="status"><?= escaparCuenta($mensajeCuenta) ?></div>
    <?php endif; ?>

    <div class="mi-cuenta-grid">
        <section class="mi-cuenta-seccion mi-cuenta-documentos">
            <div class="mi-cuenta-seccion-titulo">
                <div>
                    <p class="mi-cuenta-etiqueta">COMPROBANTES</p>
                    <h2>Historial de facturas</h2>
                </div>
                <span class="mi-cuenta-contador"><?= count($facturas) ?></span>
            </div>
            <?php if ($facturas === []): ?>
                <p class="mi-cuenta-vacio">Todavía no tenés facturas asociadas a tu cuenta.</p>
            <?php else: ?>
                <div class="mi-cuenta-tabla-scroll">
                    <table class="mi-cuenta-tabla">
                        <thead><tr><th>Factura</th><th>Fecha</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($facturas as $factura): ?>
                            <tr>
                                <td><?= escaparCuenta($factura["numero_factura"]) ?></td>
                                <td><?= escaparCuenta(formatoFechaCuenta($factura["fecha_emision"])) ?></td>
                                <td><?= escaparCuenta(formatoImporteCuenta($factura["total"])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="mi-cuenta-seccion mi-cuenta-documentos" id="presupuestos">
            <div class="mi-cuenta-seccion-titulo">
                <div>
                    <p class="mi-cuenta-etiqueta">REPARACIONES</p>
                    <h2>Historial de presupuestos</h2>
                </div>
                <span class="mi-cuenta-contador"><?= count($presupuestos) ?></span>
            </div>
            <div class="mi-cuenta-opciones">
                <a href="mi-cuenta.php?solicitar=presupuesto#presupuestos">Solicitar presupuesto</a>
            </div>
            <?php if ($solicitarPresupuesto): ?>
                <form action="mi-cuenta.php?solicitar=presupuesto#presupuestos" method="post" class="presupuesto-formulario">
                    <h3>Contanos en qué podemos ayudarte</h3>
                    <?php if ($accion === "solicitar_presupuesto" && $errorCuenta !== ""): ?><div class="mi-cuenta-mensaje error" role="alert"><?= escaparCuenta($errorCuenta) ?></div><?php endif; ?>
                    <input type="hidden" name="accion" value="solicitar_presupuesto">
                    <input type="hidden" name="csrf_presupuesto" value="<?= escaparCuenta($_SESSION["csrf_presupuesto"]) ?>">
                    <div><label for="presupuestoMotivo">Motivo o consulta</label><textarea id="presupuestoMotivo" name="motivo" rows="4" minlength="10" maxlength="3000" placeholder="Escribí tu comentario o consulta." required><?= escaparCuenta($motivoPresupuesto) ?></textarea></div>
                    <div class="mi-cuenta-acciones"><button type="submit" class="pago-boton">Enviar solicitud</button><a href="mi-cuenta.php#presupuestos" class="mi-cuenta-cancelar">Cancelar</a></div>
                </form>
            <?php endif; ?>
            <?php if ($presupuestos === []): ?>
                <p class="mi-cuenta-vacio">Todavía no tenés presupuestos asociados a tu cuenta.</p>
            <?php else: ?>
                <?php foreach ($presupuestos as $presupuesto): ?>
                    <article class="presupuesto-consulta">
                        <div class="presupuesto-consulta-cabecera">
                            <h3><?= $presupuesto["id_presupuesto"] === null ? "Solicitud #" . (int) $presupuesto["id_orden_reparacion"] : "Consulta #" . (int) $presupuesto["id_presupuesto"] ?></h3>
                            <span><?= escaparCuenta($presupuesto["estado"] ?: "Pendiente") ?></span>
                        </div>
                        <small><?= escaparCuenta(formatoFechaCuenta($presupuesto["fecha_emision"])) ?></small>
                        <?php if ($presupuesto["marca"] !== null): ?><p><strong><?= escaparCuenta(trim($presupuesto["marca"] . " " . $presupuesto["modelo"])) ?></strong></p><?php endif; ?>
                        <div><strong>Tu consulta</strong><p><?= nl2br(escaparCuenta($presupuesto["motivo"])) ?></p></div>
                        <div class="presupuesto-consulta-respuesta">
                            <strong>Respuesta de GPUFIX</strong>
                            <?php if ($presupuesto["respuesta"] !== null && $presupuesto["respuesta"] !== ""): ?>
                                <p><?= nl2br(escaparCuenta($presupuesto["respuesta"])) ?></p>
                                <small><?= escaparCuenta(formatoFechaCuenta($presupuesto["fecha_respuesta"])) ?></small>
                            <?php else: ?>
                                <p>Todavía no hay una respuesta. Podés consultarla acá cuando esté disponible.</p>
                            <?php endif; ?>
                        </div>
                        <?php if ($presupuesto["total"] !== null): ?>
                            <p class="presupuesto-consulta-precio"><strong>Total:</strong> <?= escaparCuenta(formatoImporteCuenta($presupuesto["total"])) ?></p>
                        <?php elseif ($presupuesto["respuesta"] !== null): ?>
                            <p class="presupuesto-consulta-precio">Sin total informado.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="mi-cuenta-seccion mi-cuenta-seguridad" id="seguridad">
            <div class="mi-cuenta-seccion-titulo">
                <div>
                    <p class="mi-cuenta-etiqueta">SEGURIDAD</p>
                    <h2>Datos de acceso</h2>
                </div>
                <span class="mi-cuenta-icono-seguridad" aria-hidden="true">🔒</span>
            </div>
            <div class="mi-cuenta-opciones">
                <a href="mi-cuenta.php?editar=correo#seguridad"<?= $editarCuenta === "correo" ? ' aria-current="page"' : '' ?>>Cambiar correo</a>
                <a href="mi-cuenta.php?editar=contrasena#seguridad"<?= $editarCuenta === "contrasena" ? ' aria-current="page"' : '' ?>>Cambiar contraseña</a>
            </div>
            <?php if ($editarCuenta === "correo"): ?>
            <h3 class="mi-cuenta-form-titulo">Cambiar correo</h3>
            <p class="mi-cuenta-ayuda">Ingresá tu nuevo correo y tu contraseña actual para guardar el cambio.</p>
            <form class="mi-cuenta-form" action="mi-cuenta.php?editar=correo#seguridad" method="POST">
                <input type="hidden" name="csrf_token" value="<?= escaparCuenta($csrfMiCuenta) ?>">
                <input type="hidden" name="accion" value="cambiar_correo">
                <div class="login-campo">
                    <label for="correo_nuevo">Nuevo correo electrónico</label>
                    <input type="email" id="correo_nuevo" name="correo_nuevo" maxlength="150" autocomplete="email" data-validacion="correo" value="<?= escaparCuenta($correoNuevo) ?>" required>
                </div>
                <div class="login-campo">
                    <label for="contrasena_actual_correo">Contraseña actual</label>
                    <input type="password" id="contrasena_actual_correo" name="contrasena_actual" autocomplete="current-password" required>
                </div>
                <div class="mi-cuenta-acciones">
                    <button class="login-boton" type="submit">Guardar correo</button>
                    <a class="mi-cuenta-cancelar" href="mi-cuenta.php#seguridad">Cancelar</a>
                </div>
            </form>
            <?php elseif ($editarCuenta === "contrasena"): ?>
            <h3 class="mi-cuenta-form-titulo">Cambiar contraseña</h3>
            <p class="mi-cuenta-ayuda">Ingresá tu contraseña actual y elegí una nueva.</p>
            <form class="mi-cuenta-form" action="mi-cuenta.php?editar=contrasena#seguridad" method="POST">
                <input type="hidden" name="csrf_token" value="<?= escaparCuenta($csrfMiCuenta) ?>">
                <input type="hidden" name="accion" value="cambiar_contrasena">
                <div class="login-campo mi-cuenta-campo-completo">
                    <label for="contrasena_actual">Contraseña actual</label>
                    <input type="password" id="contrasena_actual" name="contrasena_actual" autocomplete="current-password" required>
                </div>
                <div class="login-campo">
                    <label for="contrasena_nueva">Nueva contraseña</label>
                    <input type="password" id="contrasena_nueva" name="contrasena_nueva" minlength="8" autocomplete="new-password" required>
                    <small>Mínimo 8 caracteres; se aceptan números y símbolos.</small>
                </div>
                <div class="login-campo">
                    <label for="confirmar_contrasena">Repetir nueva contraseña</label>
                    <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="mi-cuenta-acciones">
                    <button class="login-boton" type="submit">Guardar contraseña</button>
                    <a class="mi-cuenta-cancelar" href="mi-cuenta.php#seguridad">Cancelar</a>
                </div>
            </form>
            <?php endif; ?>
        </section>
    </div>
</main>

<footer class="login-footer"><p>GPU FIX & STORE - Reparación y venta de GPUs</p></footer>
<script src="script.js?v=20261023-cuenta"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
