<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
header('Cache-Control: private, no-store');
$puedeEditar = admin_es_cuenta_principal($conexion);
$directorio = __DIR__ . '/comprobantes';
function archivosComprobante(int $id): array {
    $archivos = [];
    foreach (['pdf', 'jpg', 'png'] as $extension) {
        $ruta = __DIR__ . '/comprobantes/' . $id . '.' . $extension;
        if (is_file($ruta)) $archivos[$extension] = $ruta;
    }
    return $archivos;
}
function versionComprobante(int $id): string {
    $huellas = [];
    foreach (archivosComprobante($id) as $extension => $ruta) $huellas[$extension] = hash_file('sha256', $ruta);
    return hash('sha256', json_encode($huellas));
}
$error = '';
$mensaje = $_SESSION['mensaje_comprobantes'] ?? '';
unset($_SESSION['mensaje_comprobantes']);
$idSeleccionado = filter_input(INPUT_GET, 'venta', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validar_csrf();
    if (!$puedeEditar) { http_response_code(403); exit('Solo la cuenta principal puede modificar comprobantes.'); }
    $idSeleccionado = filter_input(INPUT_POST, 'venta', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $accion = is_string($_POST['accion'] ?? null) ? $_POST['accion'] : '';
    $transaccion = false;
    $respaldos = [];
    $archivosCambiados = false;
    $destino = null;
    try {
        if (!$idSeleccionado || !in_array($accion, ['guardar', 'eliminar'], true)) throw new RuntimeException('Seleccioná una venta válida.');
        if ($accion === 'eliminar' && ($_POST['confirmar'] ?? '') !== '1') throw new RuntimeException('Confirmá que querés quitar el comprobante.');
        $conexion->begin_transaction();
        $transaccion = true;
        $consulta = $conexion->prepare("SELECT id_gpu_ventas FROM gpu_ventas WHERE id_gpu_ventas = ? AND metodo_pago IN ('Transferencia', 'Transferencia (simulada)') FOR UPDATE");
        $consulta->bind_param('i', $idSeleccionado);
        $consulta->execute();
        if (!$consulta->get_result()->fetch_assoc()) throw new RuntimeException('La venta no existe o no corresponde a una transferencia.');
        if (!is_string($_POST['version'] ?? null) || !hash_equals(versionComprobante($idSeleccionado), $_POST['version'])) throw new RuntimeException('El comprobante cambió. Recargá la página antes de editarlo.');
        $archivo = null;
        if ($accion === 'guardar') {
            $archivo = $_FILES['comprobante'] ?? null;
            if (!is_array($archivo) || ($archivo['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($archivo['tmp_name'] ?? null) || !is_uploaded_file($archivo['tmp_name'])) throw new RuntimeException('Seleccioná un PDF, JPG o PNG de hasta 2 MB.');
            $tamano = filesize($archivo['tmp_name']);
            if (!$tamano || $tamano > 2 * 1024 * 1024) throw new RuntimeException('El archivo debe tener entre 1 byte y 2 MB.');
            $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
            $extension = ['application/pdf'=>'pdf', 'image/jpeg'=>'jpg', 'image/png'=>'png'][$tipo] ?? null;
            if (!$extension || ($extension !== 'pdf' && !@getimagesize($archivo['tmp_name']))) throw new RuntimeException('El contenido no es un PDF, JPG o PNG válido.');
            if (!is_dir($directorio) && !@mkdir($directorio, 0755) && !is_dir($directorio)) throw new RuntimeException('No se pudo crear la carpeta de comprobantes.');
            if (!is_file($directorio . '/.htaccess') && file_put_contents($directorio . '/.htaccess', "Require all denied\n", LOCK_EX) === false) throw new RuntimeException('No se pudo proteger la carpeta de comprobantes.');
            $destino = $directorio . '/' . $idSeleccionado . '.' . $extension;
        }
        foreach (archivosComprobante($idSeleccionado) as $ruta) {
            $contenido = file_get_contents($ruta);
            if ($contenido === false) throw new RuntimeException('No se pudo leer el comprobante anterior.');
            $respaldos[$ruta] = $contenido;
        }
        if ($accion === 'eliminar' && !$respaldos) throw new RuntimeException('Esta venta no tiene comprobante.');
        $archivosCambiados = true;
        foreach ($respaldos as $ruta => $contenido) if (!unlink($ruta)) throw new RuntimeException('No se pudo quitar el archivo anterior.');
        if ($archivo !== null && !move_uploaded_file($archivo['tmp_name'], $destino)) throw new RuntimeException('No se pudo guardar el archivo. Revisá los permisos de la carpeta comprobantes.');
        // Un archivo reemplazado o retirado debe revisarse otra vez; no reabre ventas canceladas.
        $actualizar = $conexion->prepare("UPDATE gpu_ventas SET estado = 'Pendiente de verificación' WHERE id_gpu_ventas = ? AND estado IN ('Simulada', 'Registrada', 'Pendiente de verificación', 'Verificada')");
        $actualizar->bind_param('i', $idSeleccionado);
        $actualizar->execute();
        $conexion->commit();
        $transaccion = false;
        $_SESSION['mensaje_comprobantes'] = $accion === 'guardar' ? 'Comprobante guardado. La venta conserva su cliente y sus productos.' : 'Comprobante eliminado. No se eliminó la venta.';
        header('Location: admin-comprobantes.php?venta=' . $idSeleccionado, true, 303);
        exit;
    } catch (Throwable $fallo) {
        if ($transaccion) $conexion->rollback();
        if ($archivosCambiados) {
            if ($destino && is_file($destino)) unlink($destino);
            foreach ($respaldos as $ruta => $contenido) {
                if (file_put_contents($ruta, $contenido, LOCK_EX) === false) error_log('No se pudo restaurar comprobante: ' . $ruta);
            }
        }
        error_log('Administración de comprobantes: ' . $fallo->getMessage());
        $error = $fallo instanceof mysqli_sql_exception ? 'No se pudo actualizar la venta.' : $fallo->getMessage();
    }
}
$ventas = $conexion->query("SELECT v.id_gpu_ventas, v.fecha_venta, v.estado, c.id_cliente, u.nombre, u.apellido, u.correo FROM gpu_ventas v INNER JOIN cliente c ON c.id_cliente = v.id_cliente INNER JOIN usuarios u ON u.id_correo = c.id_correo WHERE v.metodo_pago IN ('Transferencia', 'Transferencia (simulada)') ORDER BY v.id_gpu_ventas DESC")->fetch_all(MYSQLI_ASSOC);
admin_layout_inicio('Comprobantes');
?>
<h1 class="h3 mt-3">Comprobantes</h1>
<p>Archivos de transferencias vinculados a una compra y a su cliente. Podés verlos y descargarlos. La cuenta principal también puede agregarlos, reemplazarlos o quitarlos.</p>
<p>Al reemplazar o quitar un comprobante verificado, la transferencia vuelve a quedar pendiente de revisión.</p>
<?php if ($mensaje): ?><div class="alert alert-success" role="status"><?= admin_e($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
<form method="get" class="panel"><label for="ventaFiltro" class="form-label">Compra y cliente</label><div class="d-flex flex-wrap gap-2"><select name="venta" id="ventaFiltro" class="form-select" style="flex:1;min-width:180px"><option value="0">Todas las transferencias</option><?php foreach ($ventas as $venta): ?><option value="<?= (int)$venta['id_gpu_ventas'] ?>" <?= $idSeleccionado === (int)$venta['id_gpu_ventas'] ? 'selected' : '' ?>>Compra #<?= (int)$venta['id_gpu_ventas'] ?> · <?= admin_e($venta['nombre'] . ' ' . $venta['apellido'] . ' · ' . $venta['correo']) ?></option><?php endforeach; ?></select><button class="btn btn-primary">Mostrar</button></div></form>
<?php $mostradas = 0; foreach ($ventas as $venta): $id = (int)$venta['id_gpu_ventas']; if ($idSeleccionado && $id !== $idSeleccionado) continue; $mostradas++; $archivos = archivosComprobante($id); $version = versionComprobante($id); ?>
<section class="panel" id="venta-<?= $id ?>">
<h2 class="h5">Compra #<?= $id ?> · <?= admin_e($venta['nombre'] . ' ' . $venta['apellido']) ?></h2>
<p><?= admin_e($venta['correo']) ?><br>Fecha: <?= admin_e($venta['fecha_venta']) ?> · Estado: <?= admin_e($venta['estado']) ?></p>
<p><a href="admin-facturacion.php#venta-<?= $id ?>">Ver venta y verificar transferencia</a><?php if ($puedeEditar): ?> · <a href="admin-tablas.php?tabla=gpu_ventas&amp;editar=<?= $id ?>">Editar datos de la venta</a> · <a href="admin-tablas.php?tabla=cliente&amp;editar=<?= (int)$venta['id_cliente'] ?>">Ver cliente</a><?php endif; ?></p>
<?php if ($archivos): $extension = array_key_first($archivos); ?>
<p>Archivo: <?= admin_e('comprobantes/' . $id . '.' . $extension) ?> · Asociado a esta transferencia.</p>
<?php if ($extension !== 'pdf'): ?><a href="comprobante.php?compra=<?= $id ?>&amp;vista=1" target="_blank" rel="noopener"><img src="comprobante.php?compra=<?= $id ?>&amp;vista=1&amp;v=<?= admin_e($version) ?>" alt="Comprobante de la compra <?= $id ?>" class="img-thumbnail mb-3" style="max-width:100%;width:320px;max-height:360px;object-fit:contain" loading="lazy"></a><?php endif; ?>
<p class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" href="comprobante.php?compra=<?= $id ?>&amp;vista=1" target="_blank" rel="noopener">Ver comprobante<?= $extension === 'pdf' ? ' PDF' : '' ?></a><a class="btn btn-outline-secondary" href="comprobante.php?compra=<?= $id ?>">Descargar</a></p>
<?php else: ?><p>Esta compra todavía no tiene un archivo de comprobante.</p><?php endif; ?>
<?php if ($puedeEditar): ?>
<form method="post" enctype="multipart/form-data" class="border-top pt-3">
<input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="venta" value="<?= $id ?>"><input type="hidden" name="version" value="<?= admin_e($version) ?>"><input type="hidden" name="accion" value="guardar">
<label for="archivo-<?= $id ?>" class="form-label"><?= $archivos ? 'Reemplazar comprobante' : 'Agregar comprobante' ?> (PDF, JPG o PNG, hasta 2 MB)</label>
<input class="form-control mb-2" id="archivo-<?= $id ?>" type="file" name="comprobante" accept="application/pdf,image/jpeg,image/png" required><button class="btn btn-primary" type="submit"><?= $archivos ? 'Reemplazar archivo' : 'Agregar archivo' ?></button>
</form>
<?php if ($archivos): ?><form method="post" class="mt-3 border-top pt-3"><input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="venta" value="<?= $id ?>"><input type="hidden" name="version" value="<?= admin_e($version) ?>"><input type="hidden" name="accion" value="eliminar"><label class="d-block mb-2"><input type="checkbox" name="confirmar" value="1" required> Confirmo quitar el archivo de esta compra.</label><button class="btn btn-outline-danger" type="submit">Eliminar comprobante</button></form><?php endif; ?>
<?php endif; ?></section>
<?php endforeach; if (!$mostradas): ?><p>No hay transferencias para mostrar. Para adjuntar un comprobante debe existir primero una venta por transferencia vinculada a un cliente.</p><?php endif; ?>
<?php admin_layout_fin(); ?>
