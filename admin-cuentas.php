<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
$esAdministradorPrincipal = admin_es_cuenta_principal($conexion);

function buscarCuentaAdmin(mysqli $conexion, int $id, bool $bloquear = false): ?array
{
    $consulta = $conexion->prepare('SELECT u.id_correo, u.nombre, u.apellido, u.correo, u.dni, u.telefono,
        (u.correo = "gpufixstore@gmail.com") AS es_principal,
        c.id_cliente, c.direccion, e.id_empleado, e.id_puesto, p.nombre AS puesto
        FROM usuarios u LEFT JOIN cliente c ON c.id_correo = u.id_correo
        LEFT JOIN empleado e ON e.id_correo = u.id_correo LEFT JOIN puesto p ON p.id_puesto = e.id_puesto
        WHERE u.id_correo = ?' . ($bloquear ? ' FOR UPDATE' : ''));
    $consulta->bind_param('i', $id);
    $consulta->execute();
    $cuenta = $consulta->get_result()->fetch_assoc();
    $consulta->close();
    return $cuenta;
}

function cuentaTieneReferencias(mysqli $conexion, string $tabla, string $columna, int $id): bool
{
    // Los nombres de tabla y columna provienen únicamente de las listas internas de abajo.
    $consulta = $conexion->prepare('SELECT 1 FROM ' . $tabla . ' WHERE ' . $columna . ' = ? LIMIT 1');
    $consulta->bind_param('i', $id);
    $consulta->execute();
    $existe = (bool) $consulta->get_result()->fetch_row();
    $consulta->close();
    return $existe;
}

$mensaje = (string) ($_SESSION['mensaje_cuentas_admin'] ?? '');
unset($_SESSION['mensaje_cuentas_admin']);
$error = '';
$cuentaEditada = null;
$transaccionActiva = false;
$busquedaCuentas = is_string($_GET['buscar'] ?? null) ? trim($_GET['buscar']) : '';
$busquedaCuentas = mb_substr($busquedaCuentas, 0, 150, 'UTF-8');
$tipoFiltro = is_string($_GET['tipo'] ?? null) && in_array($_GET['tipo'], ['cliente', 'empleado'], true) ? $_GET['tipo'] : '';
$ordenCuentas = ($_GET['orden'] ?? '') === 'desc' ? 'desc' : 'asc';
$parametrosFiltro = ['buscar' => $busquedaCuentas, 'tipo' => $tipoFiltro, 'orden' => $ordenCuentas];
$urlListado = 'admin-cuentas.php?' . http_build_query($parametrosFiltro);
$puestosDisponibles = $conexion->query('SELECT id_puesto, nombre FROM puesto ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
$idCuenta = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validar_csrf();
    $idCuenta = filter_input(INPUT_POST, 'id_correo', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $accion = is_string($_POST['accion'] ?? null) ? $_POST['accion'] : '';
    try {
        if (!$idCuenta || !in_array($accion, ['editar', 'eliminar'], true)) throw new RuntimeException('Seleccioná una cuenta válida.');
        $conexion->begin_transaction();
        $transaccionActiva = true;
        $cuentaEditada = buscarCuentaAdmin($conexion, $idCuenta, true);
        if (!$cuentaEditada) throw new RuntimeException('La cuenta seleccionada ya no existe.');
        $esPrincipal = strtolower($cuentaEditada['correo']) === 'gpufixstore@gmail.com';
        $idPuestoActual = (int) ($cuentaEditada['id_puesto'] ?? 0);
        if ($esPrincipal && !$esAdministradorPrincipal) throw new RuntimeException('Solo GPUFIX puede modificar la cuenta principal.');
        if ($accion === 'eliminar') {
            if ($esPrincipal) throw new RuntimeException('La cuenta principal de GPUFIX no se puede eliminar.');
            if ($cuentaEditada['id_empleado'] && !$esAdministradorPrincipal) throw new RuntimeException('Solo GPUFIX puede eliminar cuentas de empleados.');
            if ($cuentaEditada['id_cliente']) {
                foreach (['gpu_ventas', 'factura', 'envios', 'presupuesto', 'gpu_cliente'] as $tabla) {
                    if (cuentaTieneReferencias($conexion, $tabla, 'id_cliente', (int) $cuentaEditada['id_cliente'])) {
                        throw new RuntimeException('Esta cuenta tiene compras, facturas, presupuestos o equipos registrados y no se puede eliminar para conservar su historial.');
                    }
                }
            }
            if ($cuentaEditada['id_empleado']) {
                foreach (['orden_compra' => 'id_empleado_recepcion', 'orden_reparacion' => 'id_empleado_recepcion', 'diagnostico' => 'id_tecnico', 'reparacion' => 'id_tecnico'] as $tabla => $columna) {
                    if (cuentaTieneReferencias($conexion, $tabla, $columna, (int) $cuentaEditada['id_empleado'])) {
                        throw new RuntimeException('Este empleado tiene operaciones o reparaciones asignadas y no se puede eliminar para conservar su historial.');
                    }
                }
            }
            if ($cuentaEditada['id_cliente']) {
                $borrarCarrito = $conexion->prepare('DELETE FROM carrito WHERE id_cliente = ?');
                $borrarCarrito->bind_param('i', $cuentaEditada['id_cliente']);
                $borrarCarrito->execute();
                $borrarCarrito->close();
            }
            foreach (['empleado', 'cliente', 'usuarios'] as $tabla) {
                $borrar = $conexion->prepare('DELETE FROM ' . $tabla . ' WHERE id_correo = ?');
                $borrar->bind_param('i', $idCuenta);
                $borrar->execute();
                $borrar->close();
            }
            $conexion->commit();
            $transaccionActiva = false;
            $_SESSION['mensaje_cuentas_admin'] = 'Cuenta eliminada junto con sus datos de cliente, empleado y carrito.';
            header('Location: ' . $urlListado . '#lista-cuentas', true, 303);
            exit;
        }

        $tipoCuenta = is_string($_POST['tipo_cuenta'] ?? null) ? $_POST['tipo_cuenta'] : '';
        $idPuesto = filter_input(INPUT_POST, 'id_puesto', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
        $cuentaEditada['tipo_cuenta'] = $tipoCuenta;
        $cuentaEditada['id_puesto'] = $idPuesto;
        if (!in_array($tipoCuenta, ['cliente', 'empleado'], true)) throw new RuntimeException('Seleccioná si la cuenta es de cliente o empleado.');
        if (!$esAdministradorPrincipal && $cuentaEditada['id_empleado'] && $tipoCuenta !== 'empleado') {
            $cuentaEditada['tipo_cuenta'] = 'empleado';
            throw new RuntimeException('Solo GPUFIX puede retirar el acceso de un empleado o pasarlo a cliente.');
        }
        if ($esPrincipal && ($tipoCuenta !== 'empleado' || $idPuesto !== $idPuestoActual)) {
            $cuentaEditada['tipo_cuenta'] = 'empleado';
            $cuentaEditada['id_puesto'] = $idPuestoActual;
            throw new RuntimeException('No se puede cambiar el rango de la cuenta principal de GPUFIX.');
        }
        if ($tipoCuenta === 'empleado' && !in_array($idPuesto, array_map('intval', array_column($puestosDisponibles, 'id_puesto')), true)) throw new RuntimeException('Seleccioná un puesto válido para el empleado.');
        if ($tipoCuenta === 'cliente' && $cuentaEditada['id_empleado']) {
            foreach (['orden_compra' => 'id_empleado_recepcion', 'orden_reparacion' => 'id_empleado_recepcion', 'diagnostico' => 'id_tecnico', 'reparacion' => 'id_tecnico'] as $tabla => $columna) {
                if (cuentaTieneReferencias($conexion, $tabla, $columna, (int) $cuentaEditada['id_empleado'])) {
                    throw new RuntimeException('No se puede pasar a cliente un empleado con operaciones o reparaciones asignadas.');
                }
            }
        }
        foreach (['nombre', 'apellido', 'correo', 'dni', 'telefono'] as $campo) {
            $cuentaEditada[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
        }
        $cuentaEditada['correo'] = strtolower($cuentaEditada['correo']);
        $cuentaEditada['direccion'] = is_string($_POST['direccion'] ?? null) ? trim($_POST['direccion']) : '';
        if ($cuentaEditada['nombre'] === '' || $cuentaEditada['apellido'] === ''
            || mb_strlen($cuentaEditada['nombre'], 'UTF-8') > 100 || mb_strlen($cuentaEditada['apellido'], 'UTF-8') > 100) {
            throw new RuntimeException('Completá el nombre y apellido, con hasta 100 caracteres cada uno.');
        }
        if (strlen($cuentaEditada['correo']) > 150 || !filter_var($cuentaEditada['correo'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('El correo no es válido.');
        if ($esPrincipal && $cuentaEditada['correo'] !== 'gpufixstore@gmail.com') throw new RuntimeException('El correo de la cuenta principal debe seguir siendo gpufixstore@gmail.com.');
        if (!preg_match('/^[0-9]{7,8}$/D', $cuentaEditada['dni'])) throw new RuntimeException('El DNI debe tener 7 u 8 números.');
        if (mb_strlen($cuentaEditada['telefono'], 'UTF-8') > 30) throw new RuntimeException('El teléfono admite hasta 30 caracteres.');
        if (mb_strlen((string) ($cuentaEditada['direccion'] ?? ''), 'UTF-8') > 255) throw new RuntimeException('La dirección admite hasta 255 caracteres.');
        $clave = is_string($_POST['contrasena'] ?? null) ? $_POST['contrasena'] : '';
        $confirmacion = is_string($_POST['confirmar_contrasena'] ?? null) ? $_POST['confirmar_contrasena'] : '';
        if ($clave !== '' || $confirmacion !== '') {
            if (strlen($clave) < 8 || strlen($clave) > 72 || str_contains($clave, "\0")) throw new RuntimeException('La contraseña debe tener entre 8 y 72 caracteres.');
            if ($clave !== $confirmacion) throw new RuntimeException('Las contraseñas no coinciden.');
        }
        $duplicado = $conexion->prepare('SELECT id_correo FROM usuarios WHERE correo = ? AND id_correo <> ? LIMIT 1');
        $duplicado->bind_param('si', $cuentaEditada['correo'], $idCuenta);
        $duplicado->execute();
        $correoEnUso = (bool) $duplicado->get_result()->fetch_row();
        $duplicado->close();
        if ($correoEnUso) throw new RuntimeException('Ese correo ya está registrado en otra cuenta.');
        $guardar = $conexion->prepare('UPDATE usuarios SET nombre = ?, apellido = ?, correo = ?, dni = ?, telefono = ? WHERE id_correo = ?');
        $guardar->bind_param('sssssi', $cuentaEditada['nombre'], $cuentaEditada['apellido'], $cuentaEditada['correo'], $cuentaEditada['dni'], $cuentaEditada['telefono'], $idCuenta);
        $guardar->execute();
        $guardar->close();
        if ($clave !== '') {
            $hash = password_hash($clave, PASSWORD_DEFAULT);
            $guardar = $conexion->prepare('UPDATE usuarios SET `contraseña` = ? WHERE id_correo = ?');
            $guardar->bind_param('si', $hash, $idCuenta);
            $guardar->execute();
            $guardar->close();
        }
        if ($tipoCuenta === 'empleado') {
            $guardar = $conexion->prepare($cuentaEditada['id_empleado']
                ? 'UPDATE empleado SET nombre = ?, apellido = ?, email = ?, dni = ?, telefono = ?, id_puesto = ? WHERE id_correo = ?'
                : 'INSERT INTO empleado (nombre, apellido, email, dni, telefono, id_puesto, id_correo) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $guardar->bind_param('sssssii', $cuentaEditada['nombre'], $cuentaEditada['apellido'], $cuentaEditada['correo'], $cuentaEditada['dni'], $cuentaEditada['telefono'], $idPuesto, $idCuenta);
            $guardar->execute();
            $guardar->close();
        } elseif ($cuentaEditada['id_empleado']) {
            $guardar = $conexion->prepare('DELETE FROM empleado WHERE id_correo = ?');
            $guardar->bind_param('i', $idCuenta);
            $guardar->execute();
            $guardar->close();
        }
        if ($cuentaEditada['id_cliente']) {
            $guardar = $conexion->prepare('UPDATE cliente SET direccion = ? WHERE id_correo = ?');
            $guardar->bind_param('si', $cuentaEditada['direccion'], $idCuenta);
            $guardar->execute();
            $guardar->close();
        } elseif ($tipoCuenta === 'cliente') {
            $guardar = $conexion->prepare('INSERT INTO cliente (id_correo, direccion) VALUES (?, ?)');
            $guardar->bind_param('is', $idCuenta, $cuentaEditada['direccion']);
            $guardar->execute();
            $guardar->close();
        }
        $conexion->commit();
        $transaccionActiva = false;
        if ((int) ($cuentaEditada['id_empleado'] ?? 0) === (int) $_SESSION['empleado_id']) {
            $_SESSION['empleado_nombre'] = $cuentaEditada['nombre'];
            $_SESSION['empleado_apellido'] = $cuentaEditada['apellido'];
        }
        $_SESSION['mensaje_cuentas_admin'] = 'Datos de la cuenta actualizados.';
        header('Location: ' . $urlListado . '&editar=' . $idCuenta . '#editar-cuenta', true, 303);
        exit;
    } catch (Throwable $fallo) {
        if ($transaccionActiva) $conexion->rollback();
        error_log('Error al administrar cuenta: ' . $fallo->getMessage());
        $error = $fallo instanceof mysqli_sql_exception
            ? ($fallo->getCode() === 1062 ? 'El correo o DNI de empleado ya está en uso.' : 'No pudimos guardar el cambio. Intentá nuevamente.')
            : $fallo->getMessage();
        if ($accion !== 'editar') $cuentaEditada = null;
    }
} elseif (isset($_GET['editar'])) {
    $cuentaEditada = buscarCuentaAdmin($conexion, $idCuenta);
    if (!$cuentaEditada) { http_response_code(404); $error = 'La cuenta seleccionada no existe.'; }
}
if ($cuentaEditada && $cuentaEditada['es_principal'] && !$esAdministradorPrincipal) {
    http_response_code(403);
    $error = 'Solo GPUFIX puede modificar la cuenta principal.';
    $cuentaEditada = null;
}

$patronBusqueda = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busquedaCuentas) . '%';
$condicionTipo = $tipoFiltro === 'empleado' ? ' AND e.id_empleado IS NOT NULL' : ($tipoFiltro === 'cliente' ? ' AND e.id_empleado IS NULL' : '');
$direccionOrden = $ordenCuentas === 'desc' ? 'DESC' : 'ASC';
$consultaCuentas = $conexion->prepare('SELECT u.id_correo, u.nombre, u.apellido, u.correo, u.dni, e.id_empleado, p.nombre AS puesto, c.id_cliente
    FROM usuarios u LEFT JOIN empleado e ON e.id_correo = u.id_correo LEFT JOIN puesto p ON p.id_puesto = e.id_puesto
    LEFT JOIN cliente c ON c.id_correo = u.id_correo
    WHERE (CONCAT(u.nombre, " ", u.apellido) LIKE ? ESCAPE "!" OR u.correo LIKE ? ESCAPE "!" OR u.dni LIKE ? ESCAPE "!")'
    . $condicionTipo . ' ORDER BY u.nombre ' . $direccionOrden . ', u.apellido ' . $direccionOrden . ', u.id_correo ASC');
$consultaCuentas->bind_param('sss', $patronBusqueda, $patronBusqueda, $patronBusqueda);
$consultaCuentas->execute();
$cuentas = $consultaCuentas->get_result();
admin_layout_inicio('Cuentas');
?>
<h1 class="h3 mt-3">Cuentas de clientes y empleados</h1>
<p class="text-secondary">Podés editar cuentas y eliminar clientes sin historial asociado. Solo GPUFIX puede eliminar empleados. La cuenta principal está protegida.</p>
<?php if ($mensaje !== ''): ?><div class="alert alert-success" role="status"><?= admin_e($mensaje) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
<?php if ($cuentaEditada):
    $tipoEditado = $cuentaEditada['tipo_cuenta'] ?? ($cuentaEditada['id_empleado'] ? 'empleado' : 'cliente');
    $principalEditada = (bool) $cuentaEditada['es_principal'];
    $tipoProtegido = $principalEditada || (!$esAdministradorPrincipal && $cuentaEditada['id_empleado']);
?>
<section class="panel" id="editar-cuenta" aria-labelledby="tituloEditarCuenta">
    <h2 class="h5 mb-3" id="tituloEditarCuenta">Editar cuenta #<?= (int) $cuentaEditada['id_correo'] ?></h2>
    <?php if ($cuentaEditada['id_empleado']): ?><p class="text-secondary">Empleado · <?= admin_e($cuentaEditada['puesto']) ?></p><?php endif; ?>
    <form method="post" action="<?= admin_e($urlListado . '&editar=' . (int) $cuentaEditada['id_correo']) ?>#editar-cuenta" class="row g-3">
        <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
        <input type="hidden" name="accion" value="editar">
        <input type="hidden" name="id_correo" value="<?= (int) $cuentaEditada['id_correo'] ?>">
        <div class="col-md-6">
            <label class="form-label" for="cuentaTipo">Tipo de cuenta</label>
            <select class="form-select" id="cuentaTipo" name="tipo_cuenta" required <?= $tipoProtegido ? 'disabled' : '' ?>>
                <option value="cliente" <?= $tipoEditado === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                <option value="empleado" <?= $tipoEditado === 'empleado' ? 'selected' : '' ?>>Empleado</option>
            </select>
            <?php if ($tipoProtegido): ?><input type="hidden" name="tipo_cuenta" value="empleado"><div class="form-text"><?= $principalEditada ? 'Administrador principal: su rango está protegido.' : 'Solo GPUFIX puede pasar un empleado a cliente.' ?></div><?php endif; ?>
        </div>
        <div class="col-md-6" id="grupoCuentaPuesto" <?= $tipoEditado !== 'empleado' ? 'hidden' : '' ?>>
            <label class="form-label" for="cuentaPuesto">Puesto del empleado</label>
            <select class="form-select" id="cuentaPuesto" name="id_puesto" <?= $principalEditada || $tipoEditado !== 'empleado' ? 'disabled' : 'required' ?>>
                <option value="">Seleccionar puesto</option>
                <?php foreach ($puestosDisponibles as $puesto): ?><option value="<?= (int) $puesto['id_puesto'] ?>" <?= (int) $cuentaEditada['id_puesto'] === (int) $puesto['id_puesto'] ? 'selected' : '' ?>><?= admin_e($puesto['nombre']) ?></option><?php endforeach; ?>
            </select>
            <?php if ($principalEditada): ?><input type="hidden" name="id_puesto" value="<?= (int) $cuentaEditada['id_puesto'] ?>"><?php endif; ?>
        </div>
        <div class="col-md-6"><label class="form-label" for="cuentaNombre">Nombre</label><input id="cuentaNombre" class="form-control" name="nombre" maxlength="100" value="<?= admin_e($cuentaEditada['nombre']) ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="cuentaApellido">Apellido</label><input id="cuentaApellido" class="form-control" name="apellido" maxlength="100" value="<?= admin_e($cuentaEditada['apellido']) ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="cuentaCorreo">Correo</label><input id="cuentaCorreo" class="form-control" name="correo" type="email" maxlength="150" value="<?= admin_e($cuentaEditada['correo']) ?>" required <?= $principalEditada ? 'readonly' : '' ?>></div>
        <div class="col-md-3"><label class="form-label" for="cuentaDni">DNI</label><input id="cuentaDni" class="form-control" name="dni" inputmode="numeric" pattern="[0-9]{7,8}" minlength="7" maxlength="8" value="<?= admin_e($cuentaEditada['dni']) ?>" required></div>
        <div class="col-md-3"><label class="form-label" for="cuentaTelefono">Teléfono</label><input id="cuentaTelefono" class="form-control" name="telefono" type="tel" maxlength="30" value="<?= admin_e($cuentaEditada['telefono']) ?>"></div>
        <div class="col-12" id="grupoCuentaDireccion" data-tiene-cliente="<?= $cuentaEditada['id_cliente'] ? '1' : '0' ?>" <?= !$cuentaEditada['id_cliente'] && $tipoEditado !== 'cliente' ? 'hidden' : '' ?>><label class="form-label" for="cuentaDireccion">Dirección de facturación del cliente</label><input id="cuentaDireccion" class="form-control" name="direccion" maxlength="255" value="<?= admin_e($cuentaEditada['direccion']) ?>"></div>
        <fieldset class="col-12">
            <legend class="h6">Cambiar contraseña</legend>
            <p class="small text-secondary">Dejá ambos campos vacíos para mantener la contraseña actual.</p>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="cuentaClave">Nueva contraseña</label><input id="cuentaClave" class="form-control" name="contrasena" type="password" autocomplete="new-password" minlength="8" maxlength="72"></div>
                <div class="col-md-6"><label class="form-label" for="cuentaClaveConfirmar">Repetir nueva contraseña</label><input id="cuentaClaveConfirmar" class="form-control" name="confirmar_contrasena" type="password" autocomplete="new-password" minlength="8" maxlength="72"></div>
            </div>
        </fieldset>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Guardar datos</button><a class="btn btn-outline-secondary" href="<?= admin_e($urlListado) ?>#lista-cuentas">Cancelar</a></div>
    </form>
</section>
<?php endif; ?>
<section class="panel" id="lista-cuentas" aria-labelledby="tituloCuentas">
    <h2 class="h5 mb-3" id="tituloCuentas">Cuentas registradas</h2>
    <form method="get" action="admin-cuentas.php#lista-cuentas" class="row g-3 align-items-end mb-3" role="search">
        <div class="col-md-7"><label class="form-label" for="buscarCuentas">Buscar por nombre, apellido, correo o DNI</label><input class="form-control" id="buscarCuentas" name="buscar" type="search" maxlength="150" value="<?= admin_e($busquedaCuentas) ?>"></div>
        <input type="hidden" name="tipo" value="<?= admin_e($tipoFiltro) ?>">
        <input type="hidden" name="orden" value="<?= admin_e($ordenCuentas) ?>">
        <div class="col-md-auto d-flex gap-2"><button class="btn btn-primary" type="submit">Buscar</button><a class="btn btn-outline-secondary" href="admin-cuentas.php#lista-cuentas">Limpiar filtros</a></div>
    </form>
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
        <div class="d-flex flex-wrap gap-2" role="group" aria-label="Filtrar tipo de cuenta">
            <?php foreach (['' => 'Todos', 'cliente' => 'Clientes', 'empleado' => 'Empleados'] as $tipo => $etiqueta): ?>
                <a class="btn btn-sm <?= $tipoFiltro === $tipo ? 'btn-primary' : 'btn-outline-primary' ?>" href="admin-cuentas.php?<?= admin_e(http_build_query(array_replace($parametrosFiltro, ['tipo' => $tipo]))) ?>#lista-cuentas" <?= $tipoFiltro === $tipo ? 'aria-current="true"' : '' ?>><?= $etiqueta ?></a>
            <?php endforeach; ?>
        </div>
        <div class="d-flex flex-wrap gap-2" role="group" aria-label="Ordenar por nombre">
            <?php foreach (['asc' => 'Nombre A–Z', 'desc' => 'Nombre Z–A'] as $orden => $etiqueta): ?>
                <a class="btn btn-sm <?= $ordenCuentas === $orden ? 'btn-secondary' : 'btn-outline-secondary' ?>" href="admin-cuentas.php?<?= admin_e(http_build_query(array_replace($parametrosFiltro, ['orden' => $orden]))) ?>#lista-cuentas" <?= $ordenCuentas === $orden ? 'aria-current="true"' : '' ?>><?= $etiqueta ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <p class="small text-secondary"><?= (int) $cuentas->num_rows ?> <?= $cuentas->num_rows === 1 ? 'usuario encontrado' : 'usuarios encontrados' ?></p>
    <div class="table-responsive"><table class="table table-striped align-middle">
        <thead><tr><th>ID</th><th>Nombre y apellido</th><th>Tipo de cuenta</th><th>Correo</th><th>DNI</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php if ($cuentas->num_rows === 0): ?><tr><td colspan="6" class="text-secondary py-4">No se encontraron usuarios con esos filtros.</td></tr><?php endif; ?>
        <?php while ($cuenta = $cuentas->fetch_assoc()): $esPrincipal = strtolower($cuenta['correo']) === 'gpufixstore@gmail.com'; ?>
            <tr>
                <td><?= (int) $cuenta['id_correo'] ?></td>
                <td><?= admin_e($cuenta['nombre'] . ' ' . $cuenta['apellido']) ?></td>
                <td><span class="badge <?= $cuenta['id_empleado'] ? 'text-bg-primary' : 'text-bg-secondary' ?>"><?= $cuenta['id_empleado'] ? 'Empleado' : 'Cliente' ?></span><?php if ($esPrincipal): ?><div class="small mt-1">Administrador principal</div><?php elseif ($cuenta['id_empleado']): ?><div class="small text-secondary mt-1"><?= admin_e($cuenta['puesto']) ?></div><?php endif; ?></td>
                <td><?= admin_e($cuenta['correo']) ?></td>
                <td><?= admin_e($cuenta['dni']) ?></td>
                <td><div class="d-flex flex-wrap gap-2">
                    <?php if (!$esPrincipal || $esAdministradorPrincipal): ?><a class="btn btn-sm btn-outline-primary" href="<?= admin_e($urlListado . '&editar=' . (int) $cuenta['id_correo']) ?>#editar-cuenta">Editar datos</a><?php endif; ?>
                    <?php if (!$esPrincipal && (!$cuenta['id_empleado'] || $esAdministradorPrincipal)): ?>
                    <form method="post" action="<?= admin_e($urlListado) ?>#lista-cuentas">
                        <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id_correo" value="<?= (int) $cuenta['id_correo'] ?>">
                        <button class="btn btn-sm btn-danger" type="submit" aria-label="<?= admin_e('Eliminar cuenta de ' . $cuenta['correo']) ?>">Eliminar cuenta</button>
                    </form>
                    <?php else: ?><span class="small text-secondary align-self-center"><?= $esPrincipal ? 'Cuenta protegida' : 'Solo GPUFIX puede eliminar' ?></span><?php endif; ?>
                </div></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table></div>
</section>
<script>
(function () {
    const tipo = document.getElementById('cuentaTipo');
    const grupoPuesto = document.getElementById('grupoCuentaPuesto');
    const puesto = document.getElementById('cuentaPuesto');
    const direccion = document.getElementById('grupoCuentaDireccion');
    if (!tipo || tipo.disabled) return;
    function actualizarTipo() {
        const esEmpleado = tipo.value === 'empleado';
        grupoPuesto.hidden = !esEmpleado;
        puesto.disabled = !esEmpleado;
        puesto.required = esEmpleado;
        direccion.hidden = esEmpleado && direccion.dataset.tieneCliente !== '1';
    }
    tipo.addEventListener('change', actualizarTipo);
    actualizarTipo();
})();
</script>
<?php
$cuentas->free();
$consultaCuentas->close();
$conexion->close();
admin_layout_fin();
