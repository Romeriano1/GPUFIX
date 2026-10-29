<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
require_once __DIR__ . '/admin-edicion-datos.php';
admin_edicion_seccion('Reparaciones', ['gpu_cliente', 'orden_reparacion', 'diagnostico', 'reparacion', 'detalle_reparacion'], 'admin-reparaciones.php');
admin_validar_csrf();

$estadosReparacion = ['Pendiente de presupuesto', 'Pendiente', 'En diagnóstico', 'Presupuestada', 'En reparación', 'Finalizada', 'Entregada', 'Cancelada'];
$estadosIniciales = ['Pendiente', 'En diagnóstico', 'En reparación'];
$puedeAgregarEstado = admin_es_cuenta_principal($conexion);
function leerEstadosPersonalizados($archivo): array {
    rewind($archivo);
    $contenido = stream_get_contents($archivo);
    $estados = $contenido === '' ? [] : json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($estados) || !array_is_list($estados)) throw new RuntimeException('La configuración de estados no es válida.');
    foreach ($estados as $estado) if (!is_string($estado) || mb_strlen($estado, 'UTF-8') > 50) throw new RuntimeException('La configuración de estados no es válida.');
    return $estados;
}
function guardarEstadoPersonalizado(string $estado, array $existentes): void {
    if ($estado === '' || mb_strlen($estado, 'UTF-8') > 50 || !preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ0-9][A-Za-zÀ-ÖØ-öø-ÿ0-9 .()\/_-]*$/uD', $estado)) throw new RuntimeException('Ingresá un estado de 1 a 50 caracteres, con letras, números, espacios o signos . / ( ) _ -.');
    $archivo = @fopen(__DIR__ . '/estados-reparacion.json', 'c+');
    if (!$archivo) throw new RuntimeException('No pudimos guardar el estado. Revisá el permiso de escritura de estados-reparacion.json.');
    try {
        if (!flock($archivo, LOCK_EX)) throw new RuntimeException('No se pudo bloquear la configuración de estados.');
        $personalizados = leerEstadosPersonalizados($archivo);
        foreach (array_merge($existentes, $personalizados) as $opcion) if (mb_strtolower($opcion, 'UTF-8') === mb_strtolower($estado, 'UTF-8')) throw new RuntimeException('Ese estado ya existe.');
        $personalizados[] = $estado;
        $contenido = json_encode($personalizados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        rewind($archivo);
        if (fwrite($archivo, $contenido) !== strlen($contenido) || !ftruncate($archivo, strlen($contenido)) || !fflush($archivo)) throw new RuntimeException('No se pudo completar el guardado del estado.');
    } finally { flock($archivo, LOCK_UN); fclose($archivo); }
}
$errorConfiguracionEstados = '';
try {
    $archivoEstados = fopen(__DIR__ . '/estados-reparacion.json', 'r');
    if (!$archivoEstados || !flock($archivoEstados, LOCK_SH)) throw new RuntimeException('No pudimos leer la configuración de estados.');
    try { $estadosPersonalizados = leerEstadosPersonalizados($archivoEstados); }
    finally { flock($archivoEstados, LOCK_UN); fclose($archivoEstados); }
    $estadosReparacion = array_values(array_unique(array_merge($estadosReparacion, $estadosPersonalizados)));
    $estadosIniciales = array_values(array_unique(array_merge($estadosIniciales, $estadosPersonalizados)));
} catch (Throwable $falloEstados) { $errorConfiguracionEstados = 'No pudimos cargar los estados personalizados. Revisá estados-reparacion.json.'; }

$estadosCerrados = ['Finalizada', 'Entregada', 'Cancelada'];
$mensaje = (string) ($_SESSION['mensaje_reparaciones_admin'] ?? '');
unset($_SESSION['mensaje_reparaciones_admin']);
$error = $errorConfiguracionEstados;
$transaccionActiva = false;
$idConsultaOrigen = filter_input(INPUT_GET, 'consulta', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$datosNueva = ['id_cliente' => '', 'id_gpu_cliente' => '0', 'marca' => '', 'modelo' => '', 'numero_serie' => '', 'problema' => '', 'estado' => 'En reparación'];

function guardarEstadoReparacion(mysqli $conexion, int $idOrden, int $idPlaca, string $estado, int $idEmpleado): void
{
    $cerrada = in_array($estado, ['Finalizada', 'Entregada', 'Cancelada'], true) ? 1 : 0;
    if (!$cerrada) {
        $buscarActiva = $conexion->prepare("SELECT id_orden_reparacion FROM orden_reparacion WHERE id_gpu_cliente = ? AND id_orden_reparacion <> ? AND COALESCE(estado, 'Pendiente') NOT IN ('Finalizada', 'Entregada', 'Cancelada') LIMIT 1");
        $buscarActiva->bind_param('ii', $idPlaca, $idOrden);
        $buscarActiva->execute();
        $otraActiva = (bool) $buscarActiva->get_result()->fetch_row();
        $buscarActiva->close();
        if ($otraActiva) throw new RuntimeException('La placa ya tiene otra reparación abierta.');
    }
    $guardar = $conexion->prepare('UPDATE orden_reparacion SET estado = ?, fecha_egreso = CASE WHEN ? THEN COALESCE(fecha_egreso, NOW()) ELSE NULL END WHERE id_orden_reparacion = ?');
    $guardar->bind_param('sii', $estado, $cerrada, $idOrden);
    $guardar->execute();
    $guardar->close();
    $buscarTrabajo = $conexion->prepare('SELECT id_reparacion FROM reparacion WHERE id_orden_reparacion = ? FOR UPDATE');
    $buscarTrabajo->bind_param('i', $idOrden);
    $buscarTrabajo->execute();
    $tieneTrabajo = (bool) $buscarTrabajo->get_result()->fetch_row();
    $buscarTrabajo->close();
    if (!$tieneTrabajo && $estado === 'En reparación') {
        $crearTrabajo = $conexion->prepare("INSERT INTO reparacion (id_orden_reparacion, id_tecnico, estado) VALUES (?, ?, 'En reparación')");
        $crearTrabajo->bind_param('ii', $idOrden, $idEmpleado);
        $crearTrabajo->execute();
        $crearTrabajo->close();
    } elseif ($tieneTrabajo) {
        $guardarTrabajo = $conexion->prepare('UPDATE reparacion SET estado = ?, fecha_fin = CASE WHEN ? THEN COALESCE(fecha_fin, NOW()) ELSE NULL END WHERE id_orden_reparacion = ?');
        $guardarTrabajo->bind_param('sii', $estado, $cerrada, $idOrden);
        $guardarTrabajo->execute();
        $guardarTrabajo->close();
    }
    $guardarPlaca = $conexion->prepare('UPDATE gpu_cliente SET estado = ? WHERE id_gpu_cliente = ?');
    $guardarPlaca->bind_param('si', $estado, $idPlaca);
    $guardarPlaca->execute();
    $guardarPlaca->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = is_string($_POST['accion'] ?? null) ? $_POST['accion'] : '';
    $idEmpleado = (int) $_SESSION['empleado_id'];
    if ($accion === 'crear') {
        foreach ($datosNueva as $campo => $valor) $datosNueva[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
        $idConsultaOrigen = filter_input(INPUT_POST, 'id_consulta', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    }
    try {
        if ($accion === 'agregar_estado') {
            if (!$puedeAgregarEstado) { http_response_code(403); throw new RuntimeException('Solo la cuenta principal de GPUFIX puede agregar estados.'); }
            $estadoNuevo = is_string($_POST['estado_nuevo'] ?? null) ? trim(preg_replace('/ +/', ' ', $_POST['estado_nuevo'])) : '';
            guardarEstadoPersonalizado($estadoNuevo, $estadosReparacion);
            $_SESSION['mensaje_reparaciones_admin'] = 'Estado agregado. Ya está disponible para todos los empleados.';
            header('Location: admin-reparaciones.php#nueva-reparacion', true, 303);
            exit;
        }
        if (!in_array($accion, ['crear', 'responder', 'guardar_orden', 'cancelar'], true)) throw new RuntimeException('No se reconoció la acción.');
        $conexion->begin_transaction();
        $transaccionActiva = true;
        if ($accion === 'crear') {
            $idCliente = filter_var($datosNueva['id_cliente'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $idPlaca = filter_var($datosNueva['id_gpu_cliente'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if (!$idCliente || $idPlaca === false || !in_array($datosNueva['estado'], $estadosIniciales, true)) throw new RuntimeException('Seleccioná un cliente, una placa y un estado inicial válidos.');
            if (mb_strlen($datosNueva['problema'], 'UTF-8') < 10 || mb_strlen($datosNueva['problema'], 'UTF-8') > 3000) throw new RuntimeException('Describí el problema usando entre 10 y 3000 caracteres.');
            $buscarCliente = $conexion->prepare('SELECT id_cliente FROM cliente WHERE id_cliente = ? FOR UPDATE');
            $buscarCliente->bind_param('i', $idCliente);
            $buscarCliente->execute();
            $clienteExiste = (bool) $buscarCliente->get_result()->fetch_row();
            $buscarCliente->close();
            if (!$clienteExiste) throw new RuntimeException('El cliente seleccionado no existe.');
            if ($idConsultaOrigen) {
                $buscarConsulta = $conexion->prepare("SELECT id_cliente, id_orden_reparacion FROM presupuesto WHERE id_presupuesto = ? AND estado IN ('Consulta de garantía', 'Reparación en garantía') FOR UPDATE");
                $buscarConsulta->bind_param('i', $idConsultaOrigen);
                $buscarConsulta->execute();
                $origen = $buscarConsulta->get_result()->fetch_assoc();
                $buscarConsulta->close();
                if (!$origen || (int) $origen['id_cliente'] !== $idCliente || $origen['id_orden_reparacion'] !== null) throw new RuntimeException('La consulta ya tiene una reparación o no corresponde al cliente seleccionado.');
            }
            if ($idPlaca) {
                $buscarPlaca = $conexion->prepare('SELECT id_gpu_cliente FROM gpu_cliente WHERE id_gpu_cliente = ? AND id_cliente = ? FOR UPDATE');
                $buscarPlaca->bind_param('ii', $idPlaca, $idCliente);
                $buscarPlaca->execute();
                $placaExiste = (bool) $buscarPlaca->get_result()->fetch_row();
                $buscarPlaca->close();
                if (!$placaExiste) throw new RuntimeException('La placa no corresponde al cliente seleccionado.');
            } else {
                foreach (['marca' => 100, 'modelo' => 150, 'numero_serie' => 100] as $campo => $maximo) {
                    if ($datosNueva[$campo] === '' || mb_strlen($datosNueva[$campo], 'UTF-8') > $maximo) throw new RuntimeException('Completá la marca, modelo y número de serie de la placa.');
                }
                $buscarSerie = $conexion->prepare('SELECT id_gpu_cliente FROM gpu_cliente WHERE numero_serie = ? FOR UPDATE');
                $buscarSerie->bind_param('s', $datosNueva['numero_serie']);
                $buscarSerie->execute();
                $serieExiste = (bool) $buscarSerie->get_result()->fetch_row();
                $buscarSerie->close();
                if ($serieExiste) throw new RuntimeException('Ese número de serie ya está registrado. Seleccioná la placa existente del cliente.');
                $crearPlaca = $conexion->prepare("INSERT INTO gpu_cliente (marca, modelo, numero_serie, estado, id_cliente) VALUES (?, ?, ?, 'Recibida', ?)");
                $crearPlaca->bind_param('sssi', $datosNueva['marca'], $datosNueva['modelo'], $datosNueva['numero_serie'], $idCliente);
                $crearPlaca->execute();
                $idPlaca = (int) $conexion->insert_id;
                $crearPlaca->close();
            }
            $crearOrden = $conexion->prepare('INSERT INTO orden_reparacion (id_gpu_cliente, id_empleado_recepcion, motivo, estado) VALUES (?, ?, ?, ?)');
            $crearOrden->bind_param('iiss', $idPlaca, $idEmpleado, $datosNueva['problema'], $datosNueva['estado']);
            $crearOrden->execute();
            $idOrden = (int) $conexion->insert_id;
            $crearOrden->close();
            guardarEstadoReparacion($conexion, $idOrden, $idPlaca, $datosNueva['estado'], $idEmpleado);
            if ($idConsultaOrigen) {
                $vincular = $conexion->prepare("UPDATE presupuesto SET id_orden_reparacion = ?, estado = 'Reparación en garantía' WHERE id_presupuesto = ?");
                $vincular->bind_param('ii', $idOrden, $idConsultaOrigen);
            } else {
                $vincular = $conexion->prepare("INSERT INTO presupuesto (id_orden_reparacion, id_cliente, consulta, total, estado) VALUES (?, ?, ?, NULL, 'Reparación en garantía')");
                $vincular->bind_param('iis', $idOrden, $idCliente, $datosNueva['problema']);
            }
            $vincular->execute();
            $vincular->close();
            $mensaje = 'Reparación #' . $idOrden . ' creada y asignada al cliente.';
            $destino = 'admin-reparaciones.php#orden-' . $idOrden;
        } elseif ($accion === 'responder') {
            $idConsulta = filter_input(INPUT_POST, 'id_consulta', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
            $respuesta = is_string($_POST['respuesta'] ?? null) ? trim($_POST['respuesta']) : '';
            if (!$idConsulta || $respuesta === '' || mb_strlen($respuesta, 'UTF-8') > 5000) throw new RuntimeException('Escribí una respuesta de hasta 5000 caracteres.');
            $buscarConsulta = $conexion->prepare("SELECT id_presupuesto FROM presupuesto WHERE id_presupuesto = ? AND id_orden_reparacion IS NULL AND estado IN ('Consulta de garantía', 'Reparación en garantía') FOR UPDATE");
            $buscarConsulta->bind_param('i', $idConsulta);
            $buscarConsulta->execute();
            $consultaExiste = (bool) $buscarConsulta->get_result()->fetch_row();
            $buscarConsulta->close();
            if (!$consultaExiste) throw new RuntimeException('Esta consulta ya tiene una orden o no está disponible.');
            $guardarRespuesta = $conexion->prepare("UPDATE presupuesto SET respuesta = ?, fecha_respuesta = NOW(), estado = 'Reparación en garantía' WHERE id_presupuesto = ?");
            $guardarRespuesta->bind_param('si', $respuesta, $idConsulta);
            $guardarRespuesta->execute();
            $guardarRespuesta->close();
            $mensaje = 'Respuesta guardada. El cliente puede verla en Mi cuenta.';
            $destino = 'admin-reparaciones.php#consulta-' . $idConsulta;
        } else {
            $idOrden = filter_input(INPUT_POST, 'id_orden_reparacion', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
            $estado = $accion === 'cancelar' ? 'Cancelada' : (is_string($_POST['estado'] ?? null) ? $_POST['estado'] : '');
            $respuesta = is_string($_POST['respuesta'] ?? null) ? trim($_POST['respuesta']) : '';
            if (!$idOrden || !in_array($estado, $estadosReparacion, true) || mb_strlen($respuesta, 'UTF-8') > 5000) throw new RuntimeException('Revisá el estado y la respuesta de la reparación.');
            $buscarOrden = $conexion->prepare('SELECT o.id_gpu_cliente, o.motivo, g.id_cliente FROM orden_reparacion o INNER JOIN gpu_cliente g ON g.id_gpu_cliente = o.id_gpu_cliente WHERE o.id_orden_reparacion = ? FOR UPDATE');
            $buscarOrden->bind_param('i', $idOrden);
            $buscarOrden->execute();
            $orden = $buscarOrden->get_result()->fetch_assoc();
            $buscarOrden->close();
            if (!$orden) throw new RuntimeException('La reparación seleccionada no existe.');
            guardarEstadoReparacion($conexion, $idOrden, (int) $orden['id_gpu_cliente'], $estado, $idEmpleado);
            if ($accion !== 'cancelar' && $respuesta !== '') {
                $buscarRespuesta = $conexion->prepare("SELECT id_presupuesto FROM presupuesto WHERE id_orden_reparacion = ? AND estado IN ('Consulta de garantía', 'Reparación en garantía') ORDER BY id_presupuesto DESC LIMIT 1 FOR UPDATE");
                $buscarRespuesta->bind_param('i', $idOrden);
                $buscarRespuesta->execute();
                $idRespuesta = (int) ($buscarRespuesta->get_result()->fetch_assoc()['id_presupuesto'] ?? 0);
                $buscarRespuesta->close();
                if ($idRespuesta) {
                    $guardarRespuesta = $conexion->prepare("UPDATE presupuesto SET respuesta = ?, fecha_respuesta = NOW(), estado = 'Reparación en garantía' WHERE id_presupuesto = ?");
                    $guardarRespuesta->bind_param('si', $respuesta, $idRespuesta);
                } else {
                    $guardarRespuesta = $conexion->prepare("INSERT INTO presupuesto (id_orden_reparacion, id_cliente, consulta, respuesta, fecha_respuesta, total, estado) VALUES (?, ?, ?, ?, NOW(), NULL, 'Reparación en garantía')");
                    $guardarRespuesta->bind_param('iiss', $idOrden, $orden['id_cliente'], $orden['motivo'], $respuesta);
                }
                $guardarRespuesta->execute();
                $guardarRespuesta->close();
            }
            $mensaje = $accion === 'cancelar' ? 'Reparación cancelada. El cliente verá Finalizado y la aclaración Reparación cancelada.' : 'Estado y respuesta actualizados.';
            $destino = 'admin-reparaciones.php#orden-' . $idOrden;
        }
        $conexion->commit();
        $transaccionActiva = false;
        $_SESSION['mensaje_reparaciones_admin'] = $mensaje;
        header('Location: ' . $destino, true, 303);
        exit;
    } catch (Throwable $fallo) {
        if ($transaccionActiva) $conexion->rollback();
        error_log('Error al gestionar reparaciones: ' . $fallo->getMessage());
        $error = $fallo instanceof mysqli_sql_exception
            ? ((int) $fallo->getCode() === 1062 ? 'El número de serie ya está registrado.' : 'No pudimos guardar la reparación. Intentá nuevamente.')
            : $fallo->getMessage();
    }
}

if ($idConsultaOrigen && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $prefill = $conexion->prepare("SELECT id_cliente, consulta FROM presupuesto WHERE id_presupuesto = ? AND id_orden_reparacion IS NULL AND estado IN ('Consulta de garantía', 'Reparación en garantía') LIMIT 1");
    $prefill->bind_param('i', $idConsultaOrigen);
    $prefill->execute();
    $origenFormulario = $prefill->get_result()->fetch_assoc();
    $prefill->close();
    if ($origenFormulario) {
        $datosNueva['id_cliente'] = (string) $origenFormulario['id_cliente'];
        $datosNueva['problema'] = (string) $origenFormulario['consulta'];
    } else {
        $error = 'La consulta seleccionada ya tiene una reparación o no está disponible.';
        $idConsultaOrigen = 0;
    }
}
$clientesReparacion = $conexion->query('SELECT c.id_cliente, u.nombre, u.apellido, u.correo FROM cliente c INNER JOIN usuarios u ON u.id_correo = c.id_correo ORDER BY u.nombre, u.apellido')->fetch_all(MYSQLI_ASSOC);
$placasCliente = $conexion->query('SELECT id_gpu_cliente, id_cliente, marca, modelo, numero_serie FROM gpu_cliente ORDER BY marca, modelo')->fetch_all(MYSQLI_ASSOC);
$consultasGarantia = $conexion->query("SELECT p.*, u.nombre, u.apellido, u.correo FROM presupuesto p INNER JOIN cliente c ON c.id_cliente = p.id_cliente INNER JOIN usuarios u ON u.id_correo = c.id_correo WHERE p.id_orden_reparacion IS NULL AND p.estado IN ('Consulta de garantía', 'Reparación en garantía') ORDER BY p.fecha_emision DESC, p.id_presupuesto DESC");
$ordenesReparacion = $conexion->query("SELECT o.*, g.marca, g.modelo, g.numero_serie, u.nombre, u.apellido, u.correo, p.respuesta, p.fecha_respuesta FROM orden_reparacion o INNER JOIN gpu_cliente g ON g.id_gpu_cliente = o.id_gpu_cliente INNER JOIN cliente c ON c.id_cliente = g.id_cliente INNER JOIN usuarios u ON u.id_correo = c.id_correo LEFT JOIN presupuesto p ON p.id_presupuesto = (SELECT MAX(pg.id_presupuesto) FROM presupuesto pg WHERE pg.id_orden_reparacion = o.id_orden_reparacion AND pg.estado IN ('Consulta de garantía', 'Reparación en garantía')) ORDER BY o.fecha_ingreso DESC, o.id_orden_reparacion DESC");
admin_layout_inicio('Reparaciones');
?>
<h1 class="h3 mt-3">Reparaciones y consultas de garantía</h1>
<p class="text-secondary">Respondé las consultas. Cuando recibas la placa, creá la reparación y asignala al cliente.</p>
<?php if ($mensaje !== ''): ?><div class="alert alert-success" role="status"><?= admin_e($mensaje) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= admin_e($error) ?></div><?php endif; ?>

<section class="panel" aria-labelledby="tituloConsultasGarantia">
    <h2 class="h5 mb-3" id="tituloConsultasGarantia">Consultas de garantía</h2>
    <?php if ($consultasGarantia->num_rows === 0): ?><p class="text-secondary mb-0">No hay consultas de garantía pendientes de una orden de reparación.</p><?php endif; ?>
    <?php while ($consulta = $consultasGarantia->fetch_assoc()): ?>
    <article class="border rounded p-3 mb-3" id="consulta-<?= (int) $consulta['id_presupuesto'] ?>">
        <h3 class="h6">Consulta #<?= (int) $consulta['id_presupuesto'] ?> · <?= admin_e($consulta['nombre'] . ' ' . $consulta['apellido']) ?></h3>
        <p class="small text-secondary"><?= admin_e($consulta['correo']) ?> · <?= admin_e($consulta['fecha_emision']) ?> · Garantía: sí</p>
        <p><?= nl2br(admin_e($consulta['consulta'])) ?></p>
        <form method="post" action="admin-reparaciones.php#consulta-<?= (int) $consulta['id_presupuesto'] ?>" class="d-grid gap-2">
            <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
            <input type="hidden" name="accion" value="responder">
            <input type="hidden" name="id_consulta" value="<?= (int) $consulta['id_presupuesto'] ?>">
            <label class="form-label mb-0" for="respuestaConsulta-<?= (int) $consulta['id_presupuesto'] ?>">Respuesta al cliente</label>
            <textarea class="form-control" id="respuestaConsulta-<?= (int) $consulta['id_presupuesto'] ?>" name="respuesta" rows="3" maxlength="5000" required><?= admin_e($consulta['respuesta']) ?></textarea>
            <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit">Guardar respuesta</button><a class="btn btn-outline-primary" href="admin-reparaciones.php?consulta=<?= (int) $consulta['id_presupuesto'] ?>#nueva-reparacion">Crear reparación para esta consulta</a></div>
        </form>
    </article>
    <?php endwhile; ?>
</section>

<section class="panel" id="nueva-reparacion" aria-labelledby="tituloNuevaReparacion">
    <h2 class="h5 mb-3" id="tituloNuevaReparacion">Crear reparación<?= $idConsultaOrigen ? ' desde consulta #' . $idConsultaOrigen : '' ?></h2>
    <form method="post" action="admin-reparaciones.php#nueva-reparacion" class="row g-3" id="formularioNuevaReparacion">
        <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
        <input type="hidden" name="accion" value="crear">
        <input type="hidden" name="id_consulta" value="<?= $idConsultaOrigen ?>">
        <div class="col-md-6"><label class="form-label" for="clienteReparacion">Cliente</label><select class="form-select" id="clienteReparacion" name="id_cliente" required>
            <option value="">Seleccionar cliente</option>
            <?php foreach ($clientesReparacion as $cliente): ?><option value="<?= (int) $cliente['id_cliente'] ?>" <?= (int) $datosNueva['id_cliente'] === (int) $cliente['id_cliente'] ? 'selected' : '' ?>><?= admin_e($cliente['nombre'] . ' ' . $cliente['apellido'] . ' · ' . $cliente['correo']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-6"><label class="form-label" for="placaReparacion">Placa del cliente</label><select class="form-select" id="placaReparacion" name="id_gpu_cliente">
            <option value="0">Registrar una placa nueva</option>
            <?php foreach ($placasCliente as $placa): ?><option data-cliente="<?= (int) $placa['id_cliente'] ?>" value="<?= (int) $placa['id_gpu_cliente'] ?>" <?= (int) $datosNueva['id_gpu_cliente'] === (int) $placa['id_gpu_cliente'] ? 'selected' : '' ?>><?= admin_e($placa['marca'] . ' ' . $placa['modelo'] . ' · ' . $placa['numero_serie']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-12" id="datosPlacaNueva"><div class="row g-3">
            <div class="col-md-4"><label class="form-label" for="marcaReparacion">Marca</label><input class="form-control" id="marcaReparacion" name="marca" maxlength="100" value="<?= admin_e($datosNueva['marca']) ?>" required></div>
            <div class="col-md-4"><label class="form-label" for="modeloReparacion">Modelo</label><input class="form-control" id="modeloReparacion" name="modelo" maxlength="150" value="<?= admin_e($datosNueva['modelo']) ?>" required></div>
            <div class="col-md-4"><label class="form-label" for="serieReparacion">Número de serie</label><input class="form-control" id="serieReparacion" name="numero_serie" maxlength="100" value="<?= admin_e($datosNueva['numero_serie']) ?>" required></div>
        </div></div>
        <div class="col-12"><label class="form-label" for="motivoNuevaReparacion">Problema de la placa</label><textarea class="form-control" id="motivoNuevaReparacion" name="problema" rows="3" minlength="10" maxlength="3000" required><?= admin_e($datosNueva['problema']) ?></textarea></div>
        <div class="col-md-6"><label class="form-label" for="estadoNuevaReparacion">Estado inicial</label><div class="d-flex gap-2 align-items-center"><select class="form-select" id="estadoNuevaReparacion" name="estado" required><?php foreach ($estadosIniciales as $estado): ?><option value="<?= admin_e($estado) ?>" <?= $datosNueva['estado'] === $estado ? 'selected' : '' ?>><?= admin_e($estado) ?></option><?php endforeach; ?></select><?php if ($puedeAgregarEstado): ?><button class="btn btn-outline-primary flex-shrink-0" style="width:40px;height:40px" type="button" data-bs-toggle="modal" data-bs-target="#agregarEstadoModal" aria-label="Agregar nuevo estado" title="Agregar nuevo estado">+</button><?php endif; ?></div></div>
        <div class="col-12"><button class="btn btn-primary" type="submit">Crear reparación</button></div>
    </form>
</section>

<section class="panel" aria-labelledby="tituloOrdenesReparacion">
    <h2 class="h5 mb-3" id="tituloOrdenesReparacion">Órdenes de reparación</h2>
    <?php if ($ordenesReparacion->num_rows === 0): ?><p class="text-secondary mb-0">Todavía no hay reparaciones registradas.</p><?php endif; ?>
    <?php while ($orden = $ordenesReparacion->fetch_assoc()): $cerrada = in_array($orden['estado'], $estadosCerrados, true); ?>
    <article class="border rounded p-3 mb-3" id="orden-<?= (int) $orden['id_orden_reparacion'] ?>">
        <div class="d-flex flex-wrap justify-content-between gap-2"><h3 class="h6">Reparación #<?= (int) $orden['id_orden_reparacion'] ?> · <?= admin_e($orden['nombre'] . ' ' . $orden['apellido']) ?></h3><span class="badge <?= $cerrada ? 'text-bg-secondary' : 'text-bg-primary' ?>"><?= admin_e($orden['estado'] ?: 'Pendiente') ?></span></div>
        <p class="small text-secondary"><?= admin_e($orden['correo']) ?> · Ingreso: <?= admin_e($orden['fecha_ingreso']) ?></p>
        <p><strong><?= admin_e($orden['marca'] . ' ' . $orden['modelo']) ?></strong> · Serie: <?= admin_e($orden['numero_serie']) ?></p>
        <p><?= nl2br(admin_e($orden['motivo'])) ?></p>
        <form method="post" action="admin-reparaciones.php#orden-<?= (int) $orden['id_orden_reparacion'] ?>" class="row g-3">
            <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
            <input type="hidden" name="id_orden_reparacion" value="<?= (int) $orden['id_orden_reparacion'] ?>">
            <div class="col-md-5"><label class="form-label" for="estadoOrden-<?= (int) $orden['id_orden_reparacion'] ?>">Estado</label><div class="d-flex gap-2 align-items-center"><select class="form-select" id="estadoOrden-<?= (int) $orden['id_orden_reparacion'] ?>" name="estado" required><?php foreach ($estadosReparacion as $estado): ?><option value="<?= admin_e($estado) ?>" <?= $orden['estado'] === $estado ? 'selected' : '' ?>><?= admin_e($estado) ?></option><?php endforeach; ?></select><?php if ($puedeAgregarEstado): ?><button class="btn btn-outline-primary flex-shrink-0" style="width:40px;height:40px" type="button" data-bs-toggle="modal" data-bs-target="#agregarEstadoModal" aria-label="Agregar nuevo estado" title="Agregar nuevo estado">+</button><?php endif; ?></div></div>
            <div class="col-12"><label class="form-label" for="respuestaOrden-<?= (int) $orden['id_orden_reparacion'] ?>">Respuesta al cliente</label><textarea class="form-control" id="respuestaOrden-<?= (int) $orden['id_orden_reparacion'] ?>" name="respuesta" rows="3" maxlength="5000"><?= admin_e($orden['respuesta']) ?></textarea></div>
            <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit" name="accion" value="guardar_orden">Guardar estado y respuesta</button><?php if (!$cerrada): ?><button class="btn btn-outline-danger" type="submit" name="accion" value="cancelar">Cancelar reparación</button><?php endif; ?></div>
        </form>
    </article>
    <?php endwhile; ?>
</section>

<?php if ($puedeAgregarEstado): ?>
<div class="modal fade" id="agregarEstadoModal" tabindex="-1" aria-labelledby="tituloAgregarEstado" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post" action="admin-reparaciones.php">
            <div class="modal-header"><h2 class="modal-title fs-5" id="tituloAgregarEstado">Agregar nuevo estado</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="accion" value="agregar_estado">
                <label class="form-label" for="estadoNuevo">Nombre del estado</label><input class="form-control" id="estadoNuevo" name="estado_nuevo" maxlength="50" required>
                <p class="form-text">Hasta 50 caracteres. Estará disponible para todos los empleados y se mostrará al cliente cuando lo asignes a su reparación.</p>
            </div>
            <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Agregar estado</button></div>
        </form>
    </div></div>
</div>
<script>document.getElementById('agregarEstadoModal').addEventListener('shown.bs.modal', function () { document.getElementById('estadoNuevo').focus(); });</script>
<?php endif; ?>

<script>
(function () {
    const cliente = document.getElementById('clienteReparacion');
    const placa = document.getElementById('placaReparacion');
    const datos = document.getElementById('datosPlacaNueva');
    function cambiarPlaca() {
        const nueva = placa.value === '0';
        datos.hidden = !nueva;
        datos.querySelectorAll('input').forEach(function (campo) { campo.required = nueva; campo.disabled = !nueva; });
    }
    function cambiarCliente() {
        Array.from(placa.options).forEach(function (opcion) {
            if (!opcion.dataset.cliente) return;
            const pertenece = opcion.dataset.cliente === cliente.value;
            opcion.hidden = !pertenece;
            opcion.disabled = !pertenece;
            if (!pertenece && opcion.selected) placa.value = '0';
        });
        cambiarPlaca();
    }
    cliente.addEventListener('change', cambiarCliente);
    placa.addEventListener('change', cambiarPlaca);
    cambiarCliente();
})();
</script>
<?php
$consultasGarantia->free();
$ordenesReparacion->free();
$conexion->close();
admin_layout_fin();