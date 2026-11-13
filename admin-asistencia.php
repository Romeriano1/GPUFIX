<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
require_once __DIR__ . '/asistencia-datos.php';
$puedeRegistrar = admin_es_cuenta_principal($conexion) || admin_es_rrhh($conexion);
$directorio = __DIR__ . '/datos/asistencia';
$zona = new DateTimeZone('America/Argentina/Buenos_Aires');
$hoy = new DateTimeImmutable('today', $zona);
$fecha = is_string($_GET['fecha'] ?? null) ? $_GET['fecha'] : $hoy->format('Y-m-d');
$dia = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha, $zona);
if (!$dia || $dia->format('Y-m-d') !== $fecha) {
    http_response_code(400);
    exit('Fecha no válida.');
}
$estados = ['presente' => 'Presente', 'ausente' => 'Ausente', 'tarde' => 'Llegada tarde'];
$empleados = $conexion->query('SELECT e.id_empleado,e.nombre,e.apellido,p.nombre puesto FROM empleado e INNER JOIN puesto p ON p.id_puesto=e.id_puesto ORDER BY e.apellido,e.nombre')->fetch_all(MYSQLI_ASSOC);
$porId = array_column($empleados, null, 'id_empleado');
$idEmpleado = filter_input(INPUT_GET, 'empleado', FILTER_VALIDATE_INT) ?: 0;
$error = '';
$mensaje = $_SESSION['mensaje_asistencia'] ?? '';
unset($_SESSION['mensaje_asistencia']);
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$puedeRegistrar) {
            http_response_code(403);
            exit('Solo RRHH y GPUFIX pueden registrar, editar o eliminar asistencias.');
        }
        admin_validar_csrf();
        $idEmpleado = filter_input(INPUT_POST, 'empleado', FILTER_VALIDATE_INT) ?: 0;
        if (!isset($porId[$idEmpleado])) throw new RuntimeException('Seleccioná un empleado existente.');
        $accion = $_POST['accion'] ?? '';
        if (!in_array($accion, ['guardar_asistencia', 'eliminar_asistencia'], true)) throw new RuntimeException('Acción no válida.');
        $registro = null;
        if ($accion === 'guardar_asistencia') {
            $estado = is_string($_POST['estado'] ?? null) ? $_POST['estado'] : '';
            $observaciones = is_string($_POST['observaciones'] ?? null) ? trim($_POST['observaciones']) : '';
            if (!isset($estados[$estado])) throw new RuntimeException('Seleccioná un estado de asistencia.');
            if (mb_strlen($observaciones, 'UTF-8') > 500) throw new RuntimeException('Las observaciones admiten hasta 500 caracteres.');
            $registro = ['empleado' => $idEmpleado, 'nombre' => $porId[$idEmpleado]['nombre'] . ' ' . $porId[$idEmpleado]['apellido'],
                'puesto' => $porId[$idEmpleado]['puesto'], 'fecha' => $fecha, 'estado' => $estado,
                'observaciones' => $observaciones, 'registrado_por' => (int) $_SESSION['empleado_id'],
                'actualizado' => (new DateTimeImmutable('now', $zona))->format(DATE_ATOM)];
        } elseif (($_POST['confirmar'] ?? '') !== '1') {
            throw new RuntimeException('Confirmá la eliminación de esta asistencia.');
        }
        $version = is_string($_POST['version'] ?? null) ? $_POST['version'] : '';
        asistencia_guardar($directorio, $idEmpleado . '-' . $fecha, $registro, $version);
        $_SESSION['mensaje_asistencia'] = $registro === null ? 'Asistencia eliminada.' : 'Asistencia guardada.';
        header('Location: admin-asistencia.php?fecha=' . $fecha, true, 303);
        exit;
    }
} catch (Throwable $fallo) {
    error_log('Asistencia: ' . $fallo->getMessage());
    $error = $fallo instanceof RuntimeException ? $fallo->getMessage() : 'No se pudo guardar la asistencia.';
}
try { $registros = asistencia_leer($directorio); }
catch (Throwable $fallo) {
    error_log('Asistencia: ' . $fallo->getMessage());
    admin_layout_inicio('Asistencia');
    echo '<h1>Asistencia</h1><div class="alert alert-danger">No se pudo leer el archivo de asistencias. Revisá el archivo antes de continuar.</div>';
    admin_layout_fin();
    exit;
}
$inicioMes = $dia->modify('first day of this month');
$meses = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$seleccionada = $registros[$idEmpleado . '-' . $fecha] ?? null;
$porFecha = [];
foreach ($registros as $registro) $porFecha[$registro['fecha']][] = $registro;
admin_layout_inicio('Asistencia');
?>
<link rel="stylesheet" href="admin-asistencia.css?v=20261113">
<h1 class="h3">Asistencia</h1>
<p><?= $puedeRegistrar ? 'Registrá la asistencia de todos los empleados, incluidos los jefes.' : 'Consulta de asistencias. RRHH administra estos registros.' ?></p>
<?php if ($mensaje): ?><div class="alert alert-success" role="status"><?= admin_e($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
<section class="panel">
    <nav class="asistencia-mes" aria-label="Mes del calendario">
        <a class="btn btn-outline-primary" href="?fecha=<?= $inicioMes->modify('-1 month')->format('Y-m-d') ?>">Anterior</a>
        <h2 class="h5 m-0"><?= $meses[(int) $dia->format('n')] . ' ' . $dia->format('Y') ?></h2>
        <a class="btn btn-outline-primary" href="?fecha=<?= $inicioMes->modify('+1 month')->format('Y-m-d') ?>">Siguiente</a>
    </nav>
    <div class="asistencia-calendario">
        <?php foreach (['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $nombre): ?><strong class="asistencia-semana"><?= $nombre ?></strong><?php endforeach; ?>
        <?php for ($i = 1; $i < (int) $inicioMes->format('N'); $i++): ?><span></span><?php endfor; ?>
        <?php for ($numero = 1; $numero <= (int) $dia->format('t'); $numero++): $fechaCelda = $dia->format('Y-m-') . sprintf('%02d', $numero); $cantidad = count($porFecha[$fechaCelda] ?? []); ?>
            <a class="asistencia-dia <?= $fechaCelda === $fecha ? 'seleccionado' : '' ?>" href="?fecha=<?= $fechaCelda ?>#asistencias-dia" <?= $fechaCelda === $fecha ? 'aria-current="date"' : '' ?> aria-label="<?= admin_e($numero . ' de ' . $meses[(int)$dia->format('n')] . ', ' . $cantidad . ' registros') ?>">
                <span><?= $numero ?></span><?php if ($cantidad): ?><small><?= $cantidad ?><span class="asistencia-texto-registros"> registros</span></small><?php endif; ?>
            </a>
        <?php endfor; ?>
    </div>
</section>
<section class="panel" id="asistencias-dia">
    <h2 class="h5">Asistencias del <?= $dia->format('d/m/Y') ?></h2>
    <form method="get" class="d-flex flex-wrap align-items-end gap-2 mb-3"><div><label class="form-label" for="fechaAsistencia">Elegir fecha</label><input class="form-control" type="date" name="fecha" id="fechaAsistencia" value="<?= $fecha ?>" required></div><button class="btn btn-outline-primary">Ver</button></form>
    <div class="table-responsive"><table class="table"><thead><tr><th>Empleado</th><th>Puesto</th><th>Asistencia</th><th>Observaciones</th><?php if ($puedeRegistrar): ?><th>Acciones</th><?php endif; ?></tr></thead><tbody>
        <?php foreach ($empleados as $empleado): $id = (int) $empleado['id_empleado']; $registro = $registros[$id . '-' . $fecha] ?? null; ?>
        <tr><th scope="row"><?= admin_e($empleado['nombre'] . ' ' . $empleado['apellido']) ?></th><td><?= admin_e($empleado['puesto']) ?></td><td><?= admin_e($registro ? $estados[$registro['estado']] : 'Sin registrar') ?></td><td><?= admin_e($registro['observaciones'] ?? '') ?></td>
        <?php if ($puedeRegistrar): ?><td><a class="btn btn-sm btn-outline-primary" href="?fecha=<?= $fecha ?>&amp;empleado=<?= $id ?>#registrar-asistencia"><?= $registro ? 'Editar' : 'Registrar' ?></a></td><?php endif; ?></tr>
        <?php endforeach; ?>
        <?php if (!$empleados): ?><tr><td colspan="5">No hay empleados registrados.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php if ($puedeRegistrar && isset($porId[$idEmpleado])): ?>
<section class="panel" id="registrar-asistencia">
    <h2 class="h5"><?= admin_e($porId[$idEmpleado]['nombre'] . ' ' . $porId[$idEmpleado]['apellido']) ?> · <?= $dia->format('d/m/Y') ?></h2>
    <form method="post" action="?fecha=<?= $fecha ?>&amp;empleado=<?= $idEmpleado ?>#registrar-asistencia">
        <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="empleado" value="<?= $idEmpleado ?>"><input type="hidden" name="version" value="<?= asistencia_version($seleccionada) ?>"><input type="hidden" name="accion" value="guardar_asistencia">
        <label class="form-label" for="estadoAsistencia">Estado</label><select class="form-select mb-3" name="estado" id="estadoAsistencia" required><option value="">Seleccioná un estado</option><?php foreach ($estados as $valor => $nombre): ?><option value="<?= $valor ?>" <?= ($_POST['estado'] ?? $seleccionada['estado'] ?? '') === $valor ? 'selected' : '' ?>><?= $nombre ?></option><?php endforeach; ?></select>
        <label class="form-label" for="observacionesAsistencia">Observaciones</label><textarea class="form-control mb-3" name="observaciones" id="observacionesAsistencia" maxlength="500" rows="3"><?= admin_e($_POST['observaciones'] ?? $seleccionada['observaciones'] ?? '') ?></textarea>
        <button class="btn btn-primary">Guardar asistencia</button>
    </form>
    <?php if ($seleccionada): ?><form method="post" class="border-top mt-4 pt-3"><input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="empleado" value="<?= $idEmpleado ?>"><input type="hidden" name="version" value="<?= asistencia_version($seleccionada) ?>"><input type="hidden" name="accion" value="eliminar_asistencia"><label class="d-block mb-2"><input type="checkbox" name="confirmar" value="1" required> Confirmo eliminar esta asistencia.</label><button class="btn btn-outline-danger">Eliminar asistencia</button></form><?php endif; ?>
</section>
<?php endif; ?>
<?php admin_layout_fin(); ?>
