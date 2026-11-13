<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
admin_validar_csrf();
$puestos = $conexion->query('SELECT id_puesto,nombre FROM puesto ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
$puestoId = filter_input(INPUT_GET, 'puesto', FILTER_VALIDATE_INT) ?: 0;
$vista = ($_GET['vista'] ?? '') === 'empleados' ? 'empleados' : 'puestos';
$crearPuesto = ($_GET['nuevo'] ?? '') === '1';
$error = '';
$mensaje = $_SESSION['mensaje_empleos'] ?? '';
unset($_SESSION['mensaje_empleos']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $puestoId = filter_input(INPUT_POST, 'puesto', FILTER_VALIDATE_INT) ?: 0;
    try {
        $accion = $_POST['accion'] ?? '';
        $crearPuesto = $accion === 'crear_rol';
        $vista = $accion === 'asignar' ? 'empleados' : 'puestos';
        if ($crearPuesto) $puestoId = 0;
        if (!$crearPuesto && !in_array($puestoId, array_map('intval', array_column($puestos,'id_puesto')), true)) throw new RuntimeException('Seleccioná un puesto válido.');
        if ($accion === 'asignar') {
            $empleadoId = filter_input(INPUT_POST, 'empleado', FILTER_VALIDATE_INT) ?: 0;
            $buscar = $conexion->prepare("SELECT e.id_empleado FROM empleado e LEFT JOIN usuarios u ON u.id_correo=e.id_correo WHERE e.id_empleado=? AND COALESCE(LOWER(TRIM(u.correo)),'') <> 'gpufixstore@gmail.com'");
            $buscar->bind_param('i',$empleadoId); $buscar->execute();
            if (!$buscar->get_result()->fetch_row()) throw new RuntimeException('Seleccioná un empleado válido.');
            $guardar = $conexion->prepare('UPDATE empleado SET id_puesto=? WHERE id_empleado=?');
            $guardar->bind_param('ii',$puestoId,$empleadoId); $guardar->execute();
            $mensaje = 'Puesto asignado al empleado. Sus accesos se aplican al navegar.';
        } elseif (in_array($accion, ['crear_rol', 'permisos'], true)) {
            $seleccion = $_POST['paneles'] ?? [];
            if (!is_array($seleccion)) throw new RuntimeException('Seleccioná paneles válidos.');
            foreach ($seleccion as $panel) if (!is_string($panel) || !isset(admin_secciones_permisos()[$panel])) throw new RuntimeException('Panel no disponible.');
            $nombreRol = is_string($_POST['nombre'] ?? null) ? trim($_POST['nombre']) : '';
            if ($nombreRol === '' || mb_strlen($nombreRol, 'UTF-8') > 100) throw new RuntimeException('El nombre del puesto debe tener entre 1 y 100 caracteres.');
            $conexion->begin_transaction();
            try {
                $actual = null;
                if ($accion !== 'crear_rol') {
                    $buscar = $conexion->prepare('SELECT nombre FROM puesto WHERE id_puesto=? FOR UPDATE');
                    $buscar->bind_param('i', $puestoId); $buscar->execute();
                    $actual = $buscar->get_result()->fetch_assoc();
                    if (!$actual) throw new RuntimeException('El puesto ya no existe.');
                    if ($actual['nombre'] === 'Administrador' && $nombreRol !== $actual['nombre']) throw new RuntimeException('El nombre Administrador está protegido.');
                }
                $buscar = $conexion->prepare('SELECT id_puesto FROM puesto WHERE LOWER(TRIM(nombre))=LOWER(?) AND id_puesto<>? LIMIT 1');
                $buscar->bind_param('si', $nombreRol, $puestoId); $buscar->execute();
                if ($buscar->get_result()->fetch_row()) throw new RuntimeException('Ya existe un puesto con ese nombre.');
                if ($accion === 'crear_rol') {
                    $guardar = $conexion->prepare('INSERT INTO puesto(nombre) VALUES(?)');
                    $guardar->bind_param('s', $nombreRol); $guardar->execute();
                    $puestoId = (int) $conexion->insert_id;
                } else {
                    $guardar = $conexion->prepare('UPDATE puesto SET nombre=? WHERE id_puesto=?');
                    $guardar->bind_param('si', $nombreRol, $puestoId); $guardar->execute();
                }
                admin_guardar_paneles_puesto($puestoId, $seleccion);
                $conexion->commit();
            } catch (Throwable $fallo) { $conexion->rollback(); throw $fallo; }
            $mensaje = $accion === 'crear_rol' ? 'Puesto creado. Ya podés asignarlo a un empleado.' : 'Cambios guardados. Los permisos se aplican a los empleados de este puesto al navegar.';
        } else { throw new RuntimeException('Acción inválida.'); }
        $_SESSION['mensaje_empleos'] = $mensaje;
        $destino = $vista === 'empleados' ? 'admin-empleos.php?vista=empleados' : 'admin-empleos.php?puesto=' . $puestoId;
        header('Location: ' . $destino,true,303); exit;
    } catch (Throwable $fallo) { error_log($fallo->getMessage()); $error = $fallo instanceof mysqli_sql_exception ? 'No se pudo guardar el cambio.' : $fallo->getMessage(); }
}
$empleados = $conexion->query("SELECT e.id_empleado,e.nombre,e.apellido,e.email,e.id_puesto FROM empleado e LEFT JOIN usuarios u ON u.id_correo=e.id_correo WHERE COALESCE(LOWER(TRIM(u.correo)),'') <> 'gpufixstore@gmail.com' ORDER BY e.nombre,e.apellido")->fetch_all(MYSQLI_ASSOC);
$permisos = admin_leer_permisos();
foreach ($puestos as $puesto) {
    if ($puesto['nombre'] === 'Administrador' && !array_key_exists((string) $puesto['id_puesto'], $permisos)) {
        $permisos[(string) $puesto['id_puesto']] = array_keys(admin_secciones_permisos());
    }
}
$rolActual = null;
foreach ($puestos as $puesto) if ((int)$puesto['id_puesto'] === $puestoId) $rolActual = $puesto;
$panelesDisponibles = admin_secciones_permisos();
unset($panelesDisponibles['admin-asistencia.php']);
$areas = array_fill_keys(array_keys(admin_roles_organigrama()), []);
$areaPorNombre = [];
$ordenPorNombre = [];
foreach (admin_roles_organigrama() as $area => $roles) {
    foreach ($roles as $nombre => $paneles) {
        $clave = mb_strtolower($nombre, 'UTF-8');
        $areaPorNombre[$clave] = $area;
        $ordenPorNombre[$clave] = count($ordenPorNombre);
    }
}
foreach ($puestos as $puesto) {
    $area = $areaPorNombre[mb_strtolower(trim($puesto['nombre']), 'UTF-8')] ?? 'Otros puestos';
    $areas[$area][] = $puesto;
}
foreach ($areas as &$puestosArea) {
    usort($puestosArea, static function (array $primero, array $segundo) use ($ordenPorNombre): int {
        $nombrePrimero = mb_strtolower(trim($primero['nombre']), 'UTF-8');
        $nombreSegundo = mb_strtolower(trim($segundo['nombre']), 'UTF-8');
        return ($ordenPorNombre[$nombrePrimero] ?? PHP_INT_MAX) <=> ($ordenPorNombre[$nombreSegundo] ?? PHP_INT_MAX);
    });
}
unset($puestosArea);
if (!$crearPuesto && $puestoId === 0 && $puestos) {
    foreach ($areas as $puestosArea) {
        if ($puestosArea) {
            $rolActual = $puestosArea[0];
            $puestoId = (int) $rolActual['id_puesto'];
            break;
        }
    }
}
if (!$crearPuesto && $puestoId > 0 && !$rolActual && !$error) $error = 'El puesto seleccionado ya no está disponible.';
$conteosEmpleados = $conexion->query('SELECT id_puesto,COUNT(*) cantidad FROM empleado GROUP BY id_puesto')->fetch_all(MYSQLI_ASSOC);
$empleadosPorPuesto = array_column($conteosEmpleados, 'cantidad', 'id_puesto');
$cantidadAsignados = (int) ($empleadosPorPuesto[$puestoId] ?? 0);
$errorPuesto = $error && in_array($_POST['accion'] ?? '', ['crear_rol', 'permisos'], true);
$nombreFormulario = $errorPuesto && is_string($_POST['nombre'] ?? null) ? $_POST['nombre'] : ($crearPuesto ? '' : ($rolActual['nombre'] ?? ''));
$panelesFormulario = $errorPuesto && is_array($_POST['paneles'] ?? null) ? $_POST['paneles'] : ($crearPuesto ? [] : ($permisos[(string) $puestoId] ?? []));
$panelesFormulario = array_values(array_diff($panelesFormulario, ['admin-asistencia.php']));
$areaActual = $rolActual ? ($areaPorNombre[mb_strtolower(trim($rolActual['nombre']), 'UTF-8')] ?? 'Otros puestos') : '';
admin_layout_inicio('Empleados y permisos');
?>
<link rel="stylesheet" href="admin-empleos.css?v=20261113">
<div class="empleos-cabecera">
    <div><h1>Empleados y permisos</h1><p>Administrá los puestos, sus accesos y la asignación de empleados.</p></div>
    <?php if ($vista === 'puestos'): ?>
        <a class="btn btn-primary" href="admin-empleos.php?nuevo=1">Nuevo puesto</a>
    <?php else: ?>
        <div class="empleos-botones">
            <a class="btn btn-outline-primary" href="admin-tablas.php?tabla=usuarios&amp;nuevo=1">Crear cuenta de acceso</a>
            <a class="btn btn-primary" href="admin-tablas.php?tabla=empleado&amp;nuevo=1">Agregar empleado</a>
        </div>
    <?php endif; ?>
</div>
<nav class="empleos-vistas" aria-label="Gestión de empleados">
    <a href="admin-empleos.php" <?= $vista === 'puestos' ? 'aria-current="page"' : '' ?>>Puestos y permisos</a>
    <a href="admin-empleos.php?vista=empleados" <?= $vista === 'empleados' ? 'aria-current="page"' : '' ?>>Empleados</a>
</nav>
<?php if ($mensaje): ?><div class="alert alert-success" role="status"><?= admin_e($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= admin_e($error) ?></div><?php endif; ?>

<?php if ($vista === 'puestos'): ?>
<form method="get" class="empleos-selector-movil panel">
    <label for="puestoMovil" class="form-label">Puesto</label>
    <div class="empleos-botones">
        <select id="puestoMovil" name="puesto" class="form-select" required>
            <?php foreach ($areas as $area => $puestosArea): if (!$puestosArea) continue; ?>
                <optgroup label="<?= admin_e($area) ?>">
                <?php foreach ($puestosArea as $puesto): ?><option value="<?= (int) $puesto['id_puesto'] ?>" <?= !$crearPuesto && (int) $puesto['id_puesto'] === $puestoId ? 'selected' : '' ?>><?= admin_e($puesto['nombre']) ?></option><?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-primary">Ver puesto</button>
    </div>
</form>
<div class="empleos-distribucion">
    <aside class="panel empleos-puestos" aria-label="Puestos de la empresa">
        <label class="form-label" for="buscarPuesto">Buscar puesto</label>
        <input class="form-control" id="buscarPuesto" type="search" autocomplete="off">
        <nav class="empleos-lista" aria-label="Elegir puesto">
        <?php foreach ($areas as $area => $puestosArea): if (!$puestosArea) continue; ?>
            <section class="empleos-area" data-area>
                <h2><?= admin_e($area) ?></h2>
                <?php foreach ($puestosArea as $puesto): ?>
                    <a class="empleos-puesto" data-puesto href="admin-empleos.php?puesto=<?= (int) $puesto['id_puesto'] ?>" <?= !$crearPuesto && (int) $puesto['id_puesto'] === $puestoId ? 'aria-current="page"' : '' ?>><?= admin_e($puesto['nombre']) ?></a>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
        </nav>
        <p class="text-secondary mt-3 mb-0" id="puestosSinResultados" hidden>No hay puestos que coincidan.</p>
    </aside>

    <section class="panel empleos-editor" id="configurar-rol" aria-labelledby="tituloPuesto">
    <?php if ($crearPuesto || $rolActual): ?>
        <div class="empleos-titulo-editor">
            <h2 id="tituloPuesto"><?= $crearPuesto ? 'Nuevo puesto' : admin_e($rolActual['nombre']) ?></h2>
            <?php if (!$crearPuesto): ?><p><?= admin_e($areaActual) ?> · <?= $cantidadAsignados ?> <?= $cantidadAsignados === 1 ? 'empleado asignado' : 'empleados asignados' ?></p><?php endif; ?>
        </div>
        <form method="post" action="admin-empleos.php<?= $crearPuesto ? '?nuevo=1' : '?puesto=' . $puestoId ?>">
            <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
            <input type="hidden" name="accion" value="<?= $crearPuesto ? 'crear_rol' : 'permisos' ?>">
            <?php if (!$crearPuesto): ?><input type="hidden" name="puesto" value="<?= $puestoId ?>"><?php endif; ?>
            <div class="mb-4">
                <label class="form-label" for="nombrePuesto">Nombre del puesto</label>
                <input class="form-control" id="nombrePuesto" name="nombre" maxlength="100" required value="<?= admin_e($nombreFormulario) ?>" <?= !$crearPuesto && $rolActual['nombre'] === 'Administrador' ? 'readonly' : '' ?>>
            </div>
            <fieldset>
                <legend class="h6">Acceso a paneles</legend>
                <p class="text-secondary">Seleccioná los paneles que podrán utilizar los empleados de este puesto.</p>
                <p class="small text-secondary">RRHH y GPUFIX administran las asistencias. GPUFIX conserva siempre el acceso completo a la administración.</p>
                <div class="empleos-permisos">
                <?php foreach ($panelesDisponibles as $pagina => $nombre): ?>
                    <label class="empleos-permiso">
                        <input type="checkbox" name="paneles[]" value="<?= admin_e($pagina) ?>" data-panel <?= in_array($pagina, $panelesFormulario, true) ? 'checked' : '' ?>>
                        <span><?= admin_e($nombre) ?></span>
                    </label>
                <?php endforeach; ?>
                </div>
                <p class="empleos-contador" id="cantidadPaneles" aria-live="polite"><?= count($panelesFormulario) ?> <?= count($panelesFormulario) === 1 ? 'panel seleccionado' : 'paneles seleccionados' ?></p>
            </fieldset>
            <p class="empleos-aclaracion">Cada acceso habilita las funciones disponibles dentro del panel. Empleados y permisos permite modificar puestos, permisos y asignaciones del personal. Tablas SQL es exclusivo de la cuenta principal.</p>
            <div class="empleos-pie-editor">
                <button class="btn btn-primary" type="submit"><?= $crearPuesto ? 'Crear puesto' : 'Guardar cambios' ?></button>
                <?php if ($crearPuesto): ?><a class="btn btn-outline-secondary" href="admin-empleos.php">Cancelar</a><?php endif; ?>
            </div>
        </form>
    <?php else: ?>
        <h2 id="tituloPuesto">Seleccioná un puesto</h2><p>Elegí un puesto para configurar sus accesos.</p>
    <?php endif; ?>
    </section>
</div>
<?php else: ?>
<section class="panel empleos-empleados">
    <div class="empleos-encabezado-lista">
        <div><h2>Empleados</h2><p>Asigná un puesto a cada empleado para definir sus accesos.</p></div>
        <?php if ($empleados): ?><div class="empleos-busqueda"><label class="form-label" for="buscarEmpleado">Buscar empleado</label><input class="form-control" id="buscarEmpleado" type="search" autocomplete="off"></div><?php endif; ?>
    </div>
    <?php if ($empleados): ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th scope="col">Empleado</th><th scope="col">Puesto asignado</th><th scope="col"><span class="visually-hidden">Acciones</span></th></tr></thead>
            <tbody>
            <?php foreach ($empleados as $empleado): $idEmpleado = (int) $empleado['id_empleado']; ?>
                <tr data-empleado>
                    <th scope="row"><span><?= admin_e($empleado['nombre'] . ' ' . $empleado['apellido']) ?></span><small><?= admin_e($empleado['email']) ?></small></th>
                    <td>
                        <form method="post" action="admin-empleos.php?vista=empleados" id="asignar-<?= $idEmpleado ?>">
                            <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
                            <input type="hidden" name="accion" value="asignar">
                            <input type="hidden" name="empleado" value="<?= $idEmpleado ?>">
                            <label class="visually-hidden" for="puesto-<?= $idEmpleado ?>">Puesto de <?= admin_e($empleado['nombre'] . ' ' . $empleado['apellido']) ?></label>
                            <select class="form-select" id="puesto-<?= $idEmpleado ?>" name="puesto" required>
                                <?php foreach ($areas as $area => $puestosArea): if (!$puestosArea) continue; ?>
                                    <optgroup label="<?= admin_e($area) ?>">
                                    <?php foreach ($puestosArea as $puesto): ?><option value="<?= (int) $puesto['id_puesto'] ?>" <?= (int) $puesto['id_puesto'] === (int) $empleado['id_puesto'] ? 'selected' : '' ?>><?= admin_e($puesto['nombre']) ?></option><?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td class="empleos-guardar-asignacion"><button class="btn btn-outline-primary" type="submit" form="asignar-<?= $idEmpleado ?>">Guardar</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p id="empleadosSinResultados" class="text-secondary mb-0" hidden>No hay empleados que coincidan.</p>
    <?php else: ?>
        <div class="empleos-vacio"><p class="mb-0">Todavía no hay empleados para asignar.</p></div>
    <?php endif; ?>
</section>
<?php endif; ?>
<script>
(() => {
    const normalizar = texto => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const buscarPuesto = document.getElementById('buscarPuesto');
    if (buscarPuesto) buscarPuesto.addEventListener('input', () => {
        const filtro = normalizar(buscarPuesto.value);
        let visibles = 0;
        document.querySelectorAll('[data-area]').forEach(area => {
            let puestosVisibles = 0;
            area.querySelectorAll('[data-puesto]').forEach(puesto => {
                puesto.hidden = !normalizar(puesto.textContent).includes(filtro);
                if (!puesto.hidden) puestosVisibles++;
            });
            area.hidden = puestosVisibles === 0;
            visibles += puestosVisibles;
        });
        document.getElementById('puestosSinResultados').hidden = visibles > 0;
    });
    const paneles = document.querySelectorAll('[data-panel]');
    paneles.forEach(panel => panel.addEventListener('change', () => {
        const cantidad = document.querySelectorAll('[data-panel]:checked').length;
        document.getElementById('cantidadPaneles').textContent = cantidad === 1 ? '1 panel seleccionado' : cantidad + ' paneles seleccionados';
    }));
    const buscarEmpleado = document.getElementById('buscarEmpleado');
    if (buscarEmpleado) buscarEmpleado.addEventListener('input', () => {
        const filtro = normalizar(buscarEmpleado.value);
        let visibles = 0;
        document.querySelectorAll('[data-empleado]').forEach(fila => {
            const nombre = fila.querySelector('th').textContent;
            const puesto = fila.querySelector('select').selectedOptions[0]?.textContent ?? '';
            fila.hidden = !normalizar(nombre + ' ' + puesto).includes(filtro);
            if (!fila.hidden) visibles++;
        });
        document.getElementById('empleadosSinResultados').hidden = visibles > 0;
    });
})();
</script>
<?php admin_layout_fin(); ?>
