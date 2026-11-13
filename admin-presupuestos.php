<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
if (!defined('GPUFIX_TABLA_ACCIONES')) {
    if (admin_es_cuenta_principal($conexion)) {
        header('Location: ' . admin_url_panel('presupuestos') . '&' . http_build_query(array_diff_key($_GET, ['tabla'=>1,'vista'=>1])), true, 303);
        exit;
    }
    define('GPUFIX_TABLA_ACCIONES', true);
}
require_once __DIR__ . '/consultas-panel.php';
require_once __DIR__ . '/admin-edicion-datos.php';
require_once __DIR__ . '/conexion.php';

$correoDisponible = false;
if (is_file(__DIR__ . '/correo-config.php')) {
    try {
        ob_start();
        try { require_once __DIR__ . '/correo-config.php'; } finally { ob_end_clean(); }
        $correoDisponible = is_callable('gpufix_enviar_correo');
    } catch (Throwable $errorCorreo) {
        error_log('Error al cargar el correo de presupuestos: ' . $errorCorreo->getMessage());
    }
}
admin_validar_csrf();

$mensaje = (string) ($_SESSION['mensaje_presupuesto_admin'] ?? '');
unset($_SESSION['mensaje_presupuesto_admin']);
$error = (string) ($_SESSION['error_presupuesto_admin'] ?? '');
unset($_SESSION['error_presupuesto_admin']);
$formularioEditado = '';
$respuestaIngresada = '';
$precioIngresado = '';
$transaccionActiva = false;
$respuestaGuardada = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = is_string($_POST['accion'] ?? null) ? $_POST['accion'] : '';
    $idPresupuesto = filter_input(INPUT_POST, 'id_presupuesto', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $idOrden = filter_input(INPUT_POST, 'id_orden_reparacion', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $formularioEditado = $idPresupuesto ? 'p-' . $idPresupuesto : 'o-' . $idOrden;

    try {
        if ($accion === 'eliminar_consulta') {
            if (($_POST['confirmar_eliminar'] ?? '') !== '1') throw new RuntimeException('Confirmá eliminar todos los registros de esta consulta.');
            $conexion->begin_transaction();
            $transaccionActiva = true;
            consultas_eliminar_presupuesto($conexion, $idPresupuesto, $idOrden);
            $conexion->commit();
            $transaccionActiva = false;
            $_SESSION['mensaje_presupuesto_admin'] = 'Consulta y detalles del presupuesto eliminados. Ya no figura como consulta sin responder.';
            header('Location: ' . admin_url_panel('presupuestos'), true, 303);
            exit;
        }
        if ($accion === 'responder_consulta') {
            $respuestaIngresada = is_string($_POST['respuesta'] ?? null) ? trim($_POST['respuesta']) : '';
            $precioIngresado = is_string($_POST['precio'] ?? null) ? trim($_POST['precio']) : '';
            $precio = $precioIngresado === '' ? null : str_replace(',', '.', $precioIngresado);
            if ((!$idPresupuesto && !$idOrden) || $respuestaIngresada === '' || mb_strlen($respuestaIngresada, 'UTF-8') > 5000) {
                throw new RuntimeException('Escribí una respuesta de hasta 5000 caracteres para una solicitud válida.');
            }
            if ($precio !== null && !preg_match('/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/', $precio)) {
                throw new RuntimeException('El precio debe ser un importe entre 0 y 9.999.999.999,99, con hasta dos decimales. También podés dejarlo vacío.');
            }

            $conexion->begin_transaction();
            $transaccionActiva = true;
            if ($idPresupuesto) {
                $buscar = $conexion->prepare("SELECT id_presupuesto FROM presupuesto WHERE id_presupuesto = ? AND COALESCE(estado, '') NOT IN ('Consulta de garantía', 'Reparación en garantía') FOR UPDATE");
                $buscar->bind_param('i', $idPresupuesto);
                $buscar->execute();
                $existe = $buscar->get_result()->fetch_assoc();
                $buscar->close();
                if (!$existe) throw new RuntimeException('La solicitud ya no está disponible.');
            } else {
                // Las solicitudes anteriores de reparacion usan el mismo formulario.
                $buscar = $conexion->prepare('SELECT g.id_cliente, o.motivo FROM orden_reparacion o INNER JOIN gpu_cliente g ON g.id_gpu_cliente = o.id_gpu_cliente WHERE o.id_orden_reparacion = ? FOR UPDATE');
                $buscar->bind_param('i', $idOrden);
                $buscar->execute();
                $orden = $buscar->get_result()->fetch_assoc();
                $buscar->close();
                if (!$orden) throw new RuntimeException('La solicitud ya no está disponible.');
                $buscar = $conexion->prepare('SELECT id_presupuesto, estado FROM presupuesto WHERE id_orden_reparacion = ? ORDER BY id_presupuesto LIMIT 1 FOR UPDATE');
                $buscar->bind_param('i', $idOrden);
                $buscar->execute();
                $existente = $buscar->get_result()->fetch_assoc();
                $buscar->close();
                if ($existente && in_array($existente['estado'], ['Consulta de garantía', 'Reparación en garantía'], true)) throw new RuntimeException('Esta consulta se gestiona desde Reparaciones.');
                $idPresupuesto = (int) ($existente['id_presupuesto'] ?? 0);
                if (!$idPresupuesto) {
                    $clienteId = (int) $orden['id_cliente'];
                    $crear = $conexion->prepare("INSERT INTO presupuesto (id_orden_reparacion, id_cliente, consulta, total, estado) VALUES (?, ?, ?, NULL, 'Pendiente de respuesta')");
                    $crear->bind_param('iis', $idOrden, $clienteId, $orden['motivo']);
                    $crear->execute();
                    $idPresupuesto = (int) $conexion->insert_id;
                    $crear->close();
                }
            }
            $guardar = $conexion->prepare("UPDATE presupuesto SET respuesta = ?, total = ?, fecha_respuesta = NOW(), estado = 'Respondida' WHERE id_presupuesto = ?");
            $guardar->bind_param('ssi', $respuestaIngresada, $precio, $idPresupuesto);
            $guardar->execute();
            $guardar->close();
            $conexion->commit();
            $transaccionActiva = false;
            $respuestaGuardada = true;
        }
        if (in_array($accion, ['responder_consulta', 'enviar'], true)) {
            if (!$correoDisponible) throw new RuntimeException('El envío por correo no está configurado. La respuesta está disponible en Mi cuenta.');
            if (!$idPresupuesto) throw new RuntimeException('Seleccioná una solicitud respondida.');
            $buscar = $conexion->prepare("SELECT p.id_presupuesto, p.total, p.respuesta, COALESCE(p.consulta, o.motivo, '') AS consulta_cliente, u.nombre, u.apellido, u.correo FROM presupuesto p LEFT JOIN orden_reparacion o ON o.id_orden_reparacion = p.id_orden_reparacion LEFT JOIN gpu_cliente g ON g.id_gpu_cliente = o.id_gpu_cliente INNER JOIN cliente c ON c.id_cliente = COALESCE(p.id_cliente, g.id_cliente) INNER JOIN usuarios u ON u.id_correo = c.id_correo WHERE p.id_presupuesto = ? LIMIT 1");
            $buscar->bind_param('i', $idPresupuesto);
            $buscar->execute();
            $solicitud = $buscar->get_result()->fetch_assoc();
            $buscar->close();
            if (!$solicitud || trim((string) $solicitud['respuesta']) === '') throw new RuntimeException('Primero guardá una respuesta para esta solicitud.');
            if (!filter_var($solicitud['correo'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('El cliente no tiene un correo válido.');
            $precioCorreo = $solicitud['total'] === null ? 'Sin total informado.' : 'Total: $ ' . number_format((float) $solicitud['total'], 2, ',', '.');
            $cuerpo = 'Hola ' . trim($solicitud['nombre'] . ' ' . $solicitud['apellido']) . ",\n\n"
                . "Respondimos tu solicitud #" . $idPresupuesto . ".\n\nTu consulta:\n" . $solicitud['consulta_cliente']
                . "\n\nRespuesta de GPUFIX:\n" . $solicitud['respuesta'] . "\n\n" . $precioCorreo
                . "\n\nTambién podés consultar esta respuesta desde Mi cuenta.\n\nEquipo GPUFIX";
            if (!gpufix_enviar_correo($solicitud['correo'], 'Respuesta a tu solicitud GPUFIX #' . $idPresupuesto, $cuerpo)) {
                throw new RuntimeException('No se pudo enviar el correo. La respuesta sigue guardada y disponible en Mi cuenta.');
            }
            $_SESSION['mensaje_presupuesto_admin'] = 'Respuesta disponible en Mi cuenta. El servidor de correo aceptó el envío al cliente.';
            header('Location: ' . admin_url_panel('presupuestos') . '#solicitud-p-' . $idPresupuesto, true, 303);
            exit;
        } else {
            throw new RuntimeException('No se reconoció la acción solicitada.');
        }
    } catch (Throwable $fallo) {
        if ($transaccionActiva) {
            $conexion->rollback();
            $transaccionActiva = false;
        }
        error_log('Error en presupuestos de administración: ' . $fallo->getMessage());
        if ($respuestaGuardada || $accion === 'enviar') {
            $_SESSION['error_presupuesto_admin'] = 'El correo no se pudo enviar. Revisá la respuesta guardada y usá «Reenviar respuesta por correo».';
            header('Location: ' . admin_url_panel('presupuestos') . '#solicitud-p-' . $idPresupuesto, true, 303);
            exit;
        }
        $error = $fallo instanceof mysqli_sql_exception ? 'No pudimos guardar la respuesta. Intentá nuevamente.' : $fallo->getMessage();
    }
}

$estadoFiltro = $_GET['estado'] ?? 'todas';
$buscar = is_string($_GET['buscar'] ?? null) ? trim($_GET['buscar']) : '';
$solicitudes = array_filter(consultas_presupuestos($conexion), static function ($fila) use ($estadoFiltro, $buscar) {
    if ($estadoFiltro === 'pendientes' && !$fila['sin_respuesta']) return false;
    if ($estadoFiltro === 'respondidas' && $fila['sin_respuesta']) return false;
    $texto = implode(' ', [$fila['nombre'], $fila['apellido'], $fila['correo'], $fila['consulta'], $fila['id_presupuesto'] ?? $fila['id_orden_reparacion']]);
    return $buscar === '' || mb_stripos($texto, $buscar) !== false;
});
admin_layout_inicio('Presupuestos');
?>
<h1 class="h3 mt-3">Presupuestos</h1>
<p class="text-secondary">Respondé la consulta del cliente. Podés informar el total o dejarlo vacío.</p>
<?php if ($mensaje !== ''): ?><div class="alert alert-success" role="status"><?= admin_e($mensaje) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
<form method="get" class="panel d-flex flex-wrap gap-3 align-items-end"><input type="hidden" name="tabla" value="presupuesto"><input type="hidden" name="vista" value="acciones">
    <label>Estado<select class="form-select" name="estado"><?php foreach (['todas'=>'Todas', 'pendientes'=>'Sin responder', 'respondidas'=>'Respondidas'] as $valor=>$texto): ?><option value="<?= $valor ?>" <?= $estadoFiltro === $valor ? 'selected' : '' ?>><?= $texto ?></option><?php endforeach; ?></select></label>
    <label>Buscar por cliente, correo o consulta<input class="form-control" name="buscar" value="<?= admin_e($buscar) ?>"></label>
    <button class="btn btn-primary">Filtrar</button><a href="<?= admin_e(admin_url_panel('presupuestos')) ?>">Limpiar</a>
</form>
<section class="panel" aria-labelledby="tituloSolicitudes">
    <h2 class="h5 mb-3" id="tituloSolicitudes">Solicitudes de clientes</h2>
    <?php if (!$solicitudes): ?>
        <p class="mb-0">No hay consultas que coincidan.</p>
    <?php else: foreach ($solicitudes as $solicitud):
        $idPresupuesto = (int) ($solicitud['id_presupuesto'] ?? 0);
        $idOrden = (int) ($solicitud['id_orden_reparacion'] ?? 0);
        $idFormulario = $idPresupuesto ? 'p-' . $idPresupuesto : 'o-' . $idOrden;
        $respuesta = $formularioEditado === $idFormulario && $accion === 'responder_consulta' ? $respuestaIngresada : (string) ($solicitud['respuesta'] ?? '');
        $precio = $formularioEditado === $idFormulario && $accion === 'responder_consulta' ? $precioIngresado : (string) ($solicitud['total'] ?? '');
    ?>
        <details class="presupuesto-admin border rounded mb-3" id="solicitud-<?= admin_e($idFormulario) ?>" <?= $formularioEditado === $idFormulario && $error !== '' ? 'open' : '' ?>>
            <summary>
                <div class="presupuesto-admin-resumen">
                    <h3 class="h6 mb-1">Solicitud #<?= $idPresupuesto ?: $idOrden ?> · <?= admin_e(trim($solicitud['nombre'] . ' ' . $solicitud['apellido'])) ?></h3>
                    <div class="small text-secondary"><?= admin_e($solicitud['correo']) ?> · <?= admin_e($solicitud['fecha_emision']) ?></div>
                </div>
                <span class="badge text-bg-secondary"><?= admin_e($solicitud['estado'] ?: 'Pendiente de respuesta') ?></span>
            </summary>
            <div class="presupuesto-admin-contenido">
            <div class="p-3 bg-light border rounded mb-3" style="overflow-wrap:anywhere">
                <strong>Motivo o consulta</strong>
                <p class="mb-0 mt-2"><?= nl2br(admin_e($solicitud['consulta'])) ?></p>
            </div>
            <form method="post" action="<?= admin_e(admin_url_panel('presupuestos')) ?>#solicitud-<?= admin_e($idFormulario) ?>">
                <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
                <input type="hidden" name="accion" value="responder_consulta">
                <input type="hidden" name="id_presupuesto" value="<?= $idPresupuesto ?>">
                <input type="hidden" name="id_orden_reparacion" value="<?= $idOrden ?>">
                <div class="mb-3">
                    <label for="respuesta-<?= admin_e($idFormulario) ?>" class="form-label">Respuesta al cliente</label>
                    <textarea class="form-control" id="respuesta-<?= admin_e($idFormulario) ?>" name="respuesta" rows="3" maxlength="5000" placeholder="Escribí la respuesta que verá el cliente en Mi cuenta." required><?= admin_e($respuesta) ?></textarea>
                </div>
                <div class="row align-items-end g-3">
                    <div class="col-md-5">
                        <label for="precio-<?= admin_e($idFormulario) ?>" class="form-label">Total ($)</label>
                        <input type="text" class="form-control" id="precio-<?= admin_e($idFormulario) ?>" name="precio" inputmode="decimal" maxlength="13" pattern="[0-9]+([.,][0-9]{1,2})?" value="<?= admin_e($precio) ?>">
                        <div class="form-text">Dejalo vacío para responder solo la consulta.</div>
                    </div>
                    <div class="col-md-auto"><button type="submit" class="btn btn-primary">Guardar y enviar respuesta</button></div>
                </div>
                <?php if ($solicitud['fecha_respuesta'] !== null): ?><p class="small text-secondary mt-3 mb-0">Última respuesta: <?= admin_e($solicitud['fecha_respuesta']) ?></p><?php endif; ?>
            </form>
            <?php if ($idPresupuesto && $solicitud['respuesta'] !== null): ?>
                <form method="post" class="mt-3">
                    <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
                    <input type="hidden" name="accion" value="enviar">
                    <input type="hidden" name="id_presupuesto" value="<?= $idPresupuesto ?>">
                    <button type="submit" class="btn btn-outline-success btn-sm" <?= $correoDisponible ? '' : 'disabled' ?>>Reenviar respuesta por correo</button>
                    <?php if (!$correoDisponible): ?><small class="text-secondary ms-2">Correo no configurado. La respuesta está disponible en Mi cuenta.</small><?php endif; ?>
                </form>
            <?php endif; ?>
            <?php if (admin_puede_eliminar($conexion)): ?><form method="post" class="mt-4 border-top pt-3">
                <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
                <input type="hidden" name="accion" value="eliminar_consulta">
                <input type="hidden" name="id_presupuesto" value="<?= $idPresupuesto ?>">
                <input type="hidden" name="id_orden_reparacion" value="<?= $idOrden ?>">
                <label class="d-block mb-2"><input type="checkbox" name="confirmar_eliminar" value="1" required> Eliminar todos los registros de esta consulta y los detalles de su presupuesto.</label>
                <p class="small text-secondary">Si existe una orden antigua usada solo para esta consulta, también se elimina. Se conservan clientes, placas e historiales de reparación.</p>
                <button class="btn btn-outline-danger" type="submit">Eliminar consulta completa</button>
            </form><?php endif; ?>
            </div>
        </details>
    <?php endforeach; endif; ?>
</section>
<script>
(function () {
    function abrirPresupuestoEnlazado() {
        const presupuesto = document.getElementById(window.location.hash.slice(1));
        if (presupuesto && presupuesto.matches('details.presupuesto-admin')) presupuesto.open = true;
    }
    window.addEventListener('hashchange', abrirPresupuestoEnlazado);
    abrirPresupuestoEnlazado();
})();
</script>
<?php
$conexion->close();
admin_layout_fin();
