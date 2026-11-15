<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
if (!defined('GPUFIX_TABLA_ACCIONES')) {
    if (admin_es_cuenta_principal($conexion)) {
        header('Location: ' . admin_url_panel('envios') . '&' . http_build_query(array_diff_key($_GET, ['tabla'=>1, 'vista'=>1])), true, 303);
        exit;
    }
    define('GPUFIX_TABLA_ACCIONES', true);
}
require_once __DIR__ . '/admin-edicion-datos.php';
require_once __DIR__ . '/envios-seguimiento.php';
admin_edicion_seccion('Envíos', ['envios'], 'admin-envios.php');
admin_validar_csrf();
header('Cache-Control: private, no-store');

$errorEnvio = '';
$envioEditar = null;
$estadosEnvio = envios_estados();
foreach ($conexion->query("SELECT DISTINCT estado FROM envios WHERE estado IS NOT NULL AND estado <> '' ORDER BY estado") as $fila) {
    if (!in_array($fila['estado'], $estadosEnvio, true)) $estadosEnvio[] = $fila['estado'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $idEnvio = filter_var($_POST['id_envios'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (($_POST['accion'] ?? '') !== 'guardar_envio' || !$idEnvio) throw new RuntimeException('Seleccioná un envío válido.');
        $estado = is_string($_POST['estado'] ?? null) ? trim($_POST['estado']) : '';
        $direccion = is_string($_POST['direccion_envio'] ?? null) ? trim($_POST['direccion_envio']) : '';
        $envioEditar = ['id_envios'=>$idEnvio, 'estado'=>$estado, 'direccion_envio'=>$direccion, 'codigo_seguimiento'=>''];
        if (!in_array($estado, $estadosEnvio, true)) throw new RuntimeException('Seleccioná un estado válido.');
        if ($direccion === '' || mb_strlen($direccion, 'UTF-8') > 255) throw new RuntimeException('La dirección es obligatoria y admite hasta 255 caracteres.');
        $existente = $conexion->prepare('SELECT codigo_seguimiento FROM envios WHERE id_envios = ?');
        $existente->bind_param('i', $idEnvio);
        $existente->execute();
        $existe = $existente->get_result()->fetch_row();
        $existente->close();
        if (!$existe) throw new RuntimeException('El envío ya no existe.');
        $envioEditar['codigo_seguimiento'] = $existe[0];
        $actualizar = $conexion->prepare('UPDATE envios SET estado = ?, direccion_envio = ? WHERE id_envios = ?');
        $actualizar->bind_param('ssi', $estado, $direccion, $idEnvio);
        $actualizar->execute();
        $actualizar->close();
        header('Location: ' . admin_url_panel('envios') . '&guardado=1#envio-' . $idEnvio, true, 303);
        exit;
    } catch (Throwable $fallo) {
        error_log('Error al actualizar envío: ' . $fallo->getMessage());
        $errorEnvio = $fallo instanceof mysqli_sql_exception ? 'No pudimos guardar el envío. Intentá nuevamente.' : $fallo->getMessage();
    }
} elseif (isset($_GET['editar'])) {
    $idEnvio = filter_var($_GET['editar'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
    $consultar = $conexion->prepare('SELECT * FROM envios WHERE id_envios = ?');
    $consultar->bind_param('i', $idEnvio);
    $consultar->execute();
    $envioEditar = $consultar->get_result()->fetch_assoc();
    $consultar->close();
    if (!$envioEditar) $errorEnvio = 'No encontramos ese envío.';
}
$buscar = is_string($_GET['buscar'] ?? null) ? mb_substr(trim($_GET['buscar']), 0, 150, 'UTF-8') : '';
$estadoFiltro = is_string($_GET['estado'] ?? null) && in_array($_GET['estado'], $estadosEnvio, true) ? $_GET['estado'] : '';
$patron = '%' . $buscar . '%';
$pagina = max(1, filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$condicion = "FROM envios e INNER JOIN cliente c ON c.id_cliente=e.id_cliente INNER JOIN usuarios u ON u.id_correo=c.id_correo WHERE (?='' OR e.estado=?) AND (?='' OR CONCAT(u.nombre,' ',u.apellido) LIKE ? OR u.correo LIKE ? OR e.codigo_seguimiento LIKE ? OR CAST(e.id_gpu_ventas AS CHAR)=?)";
$contar = $conexion->prepare('SELECT COUNT(*) ' . $condicion);
$contar->bind_param('sssssss', $estadoFiltro, $estadoFiltro, $buscar, $patron, $patron, $patron, $buscar);
$contar->execute();
$total = (int) $contar->get_result()->fetch_row()[0];
$contar->close();
$paginas = max(1, (int) ceil($total / 50));
$pagina = min($pagina, $paginas);
$desplazamiento = ($pagina - 1) * 50;
$consultar = $conexion->prepare('SELECT e.*, u.nombre, u.apellido, u.correo ' . $condicion . ' ORDER BY e.fecha DESC, e.id_envios DESC LIMIT 50 OFFSET ?');
$consultar->bind_param('sssssssi', $estadoFiltro, $estadoFiltro, $buscar, $patron, $patron, $patron, $buscar, $desplazamiento);
$consultar->execute();
$envios = $consultar->get_result()->fetch_all(MYSQLI_ASSOC);
$consultar->close();
admin_layout_inicio('Envíos');
?>
<h1 class="h3 mt-3">Envíos y retiros</h1>
<p class="text-secondary">Gestioná la entrega de las compras, sus direcciones y códigos de seguimiento.</p>
<?php if ($errorEnvio !== ''): ?><div class="alert alert-danger" role="alert"><?= admin_e($errorEnvio) ?></div><?php endif; ?>
<?php if (($_GET['guardado'] ?? '') === '1'): ?><div class="alert alert-success" role="status">Envío actualizado.</div><?php endif; ?>
<?php if ($envioEditar): ?>
<section class="panel" id="editar-envio">
    <h2 class="h5">Editar envío #<?= (int) $envioEditar['id_envios'] ?></h2>
    <form method="post" action="<?= admin_e(admin_url_panel('envios')) ?>" class="row g-3">
        <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
        <input type="hidden" name="accion" value="guardar_envio">
        <input type="hidden" name="id_envios" value="<?= (int) $envioEditar['id_envios'] ?>">
        <div class="col-md-6"><label class="form-label" for="estadoEnvio">Estado</label><select class="form-select" name="estado" id="estadoEnvio" required>
            <?php foreach ($estadosEnvio as $estado): ?><option <?= $envioEditar['estado'] === $estado ? 'selected' : '' ?>><?= admin_e($estado) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-6"><label class="form-label" for="codigoEnvio">Código de seguimiento</label><input class="form-control" id="codigoEnvio" readonly value="<?= admin_e($envioEditar['codigo_seguimiento'] ?: 'Sin código generado') ?>"><p class="form-text">Se genera al comprar y se conserva al actualizar el estado.</p></div>
        <div class="col-12"><label class="form-label" for="direccionEnvio">Dirección de entrega o sucursal de retiro</label><input class="form-control" name="direccion_envio" id="direccionEnvio" maxlength="255" required value="<?= admin_e($envioEditar['direccion_envio']) ?>"></div>
        <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-primary">Guardar cambios</button><a class="btn btn-outline-secondary" href="<?= admin_e(admin_url_panel('envios')) ?>">Cancelar</a></div>
    </form>
</section>
<?php endif; ?>
<section class="panel">
    <h2 class="h5">Entregas registradas</h2>
    <form method="get" action="<?= admin_e(admin_url_panel('envios')) ?>" class="row g-3 align-items-end mb-4">
        <?php if (admin_es_cuenta_principal($conexion)): ?><input type="hidden" name="tabla" value="envios"><input type="hidden" name="vista" value="acciones"><?php endif; ?>
        <div class="col-md-6"><label class="form-label" for="buscarEnvios">Cliente, correo, compra o seguimiento</label><input class="form-control" type="search" name="buscar" id="buscarEnvios" maxlength="150" value="<?= admin_e($buscar) ?>"></div>
        <div class="col-md-4"><label class="form-label" for="filtrarEstado">Estado</label><select class="form-select" name="estado" id="filtrarEstado"><option value="">Todos</option><?php foreach ($estadosEnvio as $estado): ?><option <?= $estadoFiltro === $estado ? 'selected' : '' ?>><?= admin_e($estado) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><button class="btn btn-primary">Filtrar</button></div>
    </form>
    <div class="table-responsive"><table class="table table-striped align-middle">
        <thead><tr><th>Envío</th><th>Compra</th><th>Cliente</th><th>Fecha</th><th>Dirección</th><th>Estado</th><th>Seguimiento</th><th>Acción</th></tr></thead>
        <tbody><?php foreach ($envios as $envio): ?><tr id="envio-<?= (int) $envio['id_envios'] ?>">
            <td>#<?= (int) $envio['id_envios'] ?></td><td>#<?= (int) $envio['id_gpu_ventas'] ?></td>
            <td><?= admin_e($envio['nombre'] . ' ' . $envio['apellido']) ?><br><small><?= admin_e($envio['correo']) ?></small></td>
            <td><?= admin_e($envio['fecha']) ?></td><td><?= admin_e($envio['direccion_envio']) ?></td><td><?= admin_e($envio['estado']) ?></td><td><?= admin_e($envio['codigo_seguimiento'] ?: 'Sin código') ?></td>
            <td><a class="btn btn-sm btn-outline-primary" href="<?= admin_e(admin_url_panel('envios') . '&editar=' . (int) $envio['id_envios']) ?>#editar-envio">Editar</a></td>
        </tr><?php endforeach; ?>
        <?php if (!$envios): ?><tr><td colspan="8" class="text-secondary text-center py-4">No hay envíos que coincidan con la búsqueda.</td></tr><?php endif; ?></tbody>
    </table></div>
    <nav class="d-flex flex-wrap align-items-center gap-3" aria-label="Páginas de envíos">
        <?php foreach (['Anterior'=>$pagina-1, 'Siguiente'=>$pagina+1] as $etiqueta=>$numero): if ($numero < 1 || $numero > $paginas) continue; ?>
            <a href="<?= admin_e(admin_url_panel('envios') . '&' . http_build_query(['pagina'=>$numero, 'buscar'=>$buscar, 'estado'=>$estadoFiltro])) ?>"><?= admin_e($etiqueta) ?></a>
        <?php endforeach; ?><span>Página <?= $pagina ?> de <?= $paginas ?> · <?= $total ?> registros</span>
    </nav>
</section>
<?php admin_layout_fin(); ?>
