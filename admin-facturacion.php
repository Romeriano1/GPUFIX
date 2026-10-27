<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
require_once __DIR__ . '/conexion.php';
header('Cache-Control: private, no-store');

function comprobanteVentaAdmin(int $idVenta): ?string
{
    foreach (['pdf', 'jpg', 'png'] as $extension) {
        $ruta = __DIR__ . '/comprobantes/' . $idVenta . '.' . $extension;
        if (is_file($ruta) && is_readable($ruta)) return $extension;
    }
    return null;
}

function numeroTarjetaAdmin(array $venta): ?string
{
    $ultimos = (string) ($venta['tarjeta_ultimos4'] ?? '');
    $longitud = (int) ($venta['tarjeta_longitud'] ?? 0);
    if (!preg_match('/^[0-9]{4}$/', $ultimos) || !in_array($longitud, [13, 16, 19], true)) return null;
    return trim(chunk_split(str_repeat('X', $longitud - 4), 4, ' ')) . ' ' . $ultimos;
}

$errorFacturacion = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validar_csrf();
    $idVenta = filter_input(INPUT_POST, 'id_gpu_ventas', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (($_POST['accion'] ?? '') !== 'verificar_transferencia' || !$idVenta) {
        $errorFacturacion = 'No se reconoció la transferencia seleccionada.';
    } elseif (comprobanteVentaAdmin($idVenta) === null) {
        $errorFacturacion = 'No hay un comprobante disponible para verificar esta transferencia.';
    } else {
        $verificar = $conexion->prepare("UPDATE gpu_ventas SET estado = 'Verificada' WHERE id_gpu_ventas = ? AND metodo_pago IN ('Transferencia', 'Transferencia (simulada)') AND estado IN ('Simulada', 'Registrada', 'Pendiente de verificación', 'Verificada')");
        $verificar->bind_param('i', $idVenta);
        $verificar->execute();
        $verificar->close();
        $consultar = $conexion->prepare("SELECT id_gpu_ventas FROM gpu_ventas WHERE id_gpu_ventas = ? AND metodo_pago IN ('Transferencia', 'Transferencia (simulada)') AND estado = 'Verificada'");
        $consultar->bind_param('i', $idVenta);
        $consultar->execute();
        $verificada = $consultar->get_result()->fetch_assoc();
        $consultar->close();
        if ($verificada) {
            header('Location: admin-facturacion.php?verificada=' . $idVenta . '#venta-' . $idVenta, true, 303);
            exit;
        }
        $errorFacturacion = 'La operación no es una transferencia pendiente o su estado ya no permite verificarla.';
    }
}

$facturas = $conexion->query("SELECT f.id_factura,f.numero_factura,f.fecha_emision,f.total,COALESCE(u.correo,'Cliente no disponible') correo,f.id_gpu_ventas,f.id_reparacion FROM factura f LEFT JOIN cliente c ON c.id_cliente=f.id_cliente LEFT JOIN usuarios u ON u.id_correo=c.id_correo ORDER BY f.fecha_emision DESC,f.id_factura DESC");
$ventas = $conexion->query("SELECT v.*,COALESCE(u.correo,'Cliente no disponible') correo FROM gpu_ventas v LEFT JOIN cliente c ON c.id_cliente=v.id_cliente LEFT JOIN usuarios u ON u.id_correo=c.id_correo ORDER BY v.fecha_venta DESC,v.id_gpu_ventas DESC");
admin_layout_inicio('Facturación');
?>
<h1 class="h3 mt-3">Facturación y ventas</h1>
<p class="text-secondary">Consultá las ventas, sus resúmenes y los comprobantes recibidos.</p>
<?php if ($errorFacturacion !== ''): ?><div class="alert alert-danger" role="alert"><?= admin_e($errorFacturacion) ?></div><?php endif; ?>
<?php if (filter_input(INPUT_GET, 'verificada', FILTER_VALIDATE_INT)): ?><div class="alert alert-success" role="status">Transferencia marcada como verificada.</div><?php endif; ?>

<div class="panel">
    <h2 class="h5">Facturas</h2>
    <div class="table-responsive"><table class="table table-striped">
        <thead><tr><th>N.º</th><th>Fecha</th><th>Cliente</th><th>Origen</th><th>Total</th></tr></thead>
        <tbody>
        <?php if ($facturas->num_rows === 0): ?>
            <tr><td colspan="5" class="text-center text-secondary py-4">Todavía no hay facturas registradas.</td></tr>
        <?php else: while ($f = $facturas->fetch_assoc()): ?>
            <tr>
                <td><?= admin_e($f['numero_factura']) ?></td><td><?= admin_e($f['fecha_emision']) ?></td><td><?= admin_e($f['correo']) ?></td>
                <td><?= $f['id_gpu_ventas'] ? 'Venta #' . (int) $f['id_gpu_ventas'] : ($f['id_reparacion'] ? 'Reparación #' . (int) $f['id_reparacion'] : '—') ?></td>
                <td>$ <?= number_format((float) $f['total'], 2, ',', '.') ?></td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table></div>
</div>

<div class="panel">
    <h2 class="h5">Ventas registradas</h2>
    <div class="table-responsive"><table class="table table-striped align-middle">
        <thead><tr><th>Venta</th><th>Fecha</th><th>Cliente</th><th>Método de pago</th><th>Estado</th><th>Total</th><th>Detalle del pago</th></tr></thead>
        <tbody>
        <?php if ($ventas->num_rows === 0): ?>
            <tr><td colspan="7" class="text-center text-secondary py-4">Todavía no hay ventas registradas.</td></tr>
        <?php else: while ($v = $ventas->fetch_assoc()):
            $idVenta = (int) $v['id_gpu_ventas'];
            $metodo = trim((string) preg_replace('/\\s*\\(simulada\\)/iu', '', (string) $v['metodo_pago']));
            $esTransferencia = $metodo === 'Transferencia';
            $esTarjeta = str_starts_with($metodo, 'Tarjeta ');
            $estado = (string) $v['estado'];
            if ($estado === 'Simulada') $estado = $esTransferencia ? 'Pendiente de verificación' : 'Registrada';
            $extension = $esTransferencia ? comprobanteVentaAdmin($idVenta) : null;
            $numeroOculto = $esTarjeta ? numeroTarjetaAdmin($v) : null;
        ?>
            <tr id="venta-<?= $idVenta ?>">
                <td>#<?= $idVenta ?></td><td><?= admin_e($v['fecha_venta']) ?></td><td><?= admin_e($v['correo']) ?></td>
                <td><?= admin_e($metodo ?: 'No indicado') ?></td><td><?= admin_e($estado ?: 'Sin estado') ?></td>
                <td class="text-nowrap">$ <?= number_format((float) $v['precio_total'], 2, ',', '.') ?></td>
                <td style="min-width:240px">
                <?php if ($esTransferencia && $extension !== null): ?>
                    <?php if ($extension !== 'pdf'): ?>
                        <a href="comprobante.php?compra=<?= $idVenta ?>&amp;vista=1" target="_blank" rel="noopener">
                            <img src="comprobante.php?compra=<?= $idVenta ?>&amp;vista=1" alt="Comprobante de transferencia de la venta <?= $idVenta ?>" class="img-thumbnail mb-2" width="220" loading="lazy">
                        </a>
                    <?php else: ?>
                        <p class="mb-2">Comprobante PDF</p>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <a class="btn btn-sm btn-outline-primary" href="comprobante.php?compra=<?= $idVenta ?>&amp;vista=1" target="_blank" rel="noopener">Ver comprobante</a>
                        <a class="btn btn-sm btn-outline-secondary" href="comprobante.php?compra=<?= $idVenta ?>">Descargar</a>
                    </div>
                    <?php if (in_array($v['estado'], ['Simulada', 'Registrada', 'Pendiente de verificación'], true)): ?>
                        <form method="post" action="admin-facturacion.php">
                            <input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>">
                            <input type="hidden" name="accion" value="verificar_transferencia">
                            <input type="hidden" name="id_gpu_ventas" value="<?= $idVenta ?>">
                            <button class="btn btn-sm btn-success" type="submit">Marcar comprobante verificado</button>
                        </form>
                    <?php endif; ?>
                <?php elseif ($esTransferencia): ?>
                    <span class="text-secondary">Comprobante no disponible.</span>
                <?php elseif ($esTarjeta): ?>
                    <details>
                        <summary class="btn btn-sm btn-outline-primary">Ver resumen</summary>
                        <?php if ($numeroOculto !== null): ?>
                            <dl class="mt-3 mb-0">
                                <dt>Tarjeta</dt><dd class="text-nowrap font-monospace"><?= admin_e($numeroOculto) ?></dd>
                                <dt>Titular</dt><dd><?= admin_e($v['tarjeta_titular']) ?></dd>
                                <dt>Vencimiento</dt><dd><?= admin_e($v['tarjeta_vencimiento']) ?></dd>
                                <dt>DNI del titular</dt><dd><?= admin_e($v['tarjeta_dni']) ?></dd>
                            </dl>
                        <?php else: ?>
                            <p class="mt-3 mb-0 text-secondary">Esta venta es anterior al resumen de tarjeta y no tiene esos datos guardados.</p>
                        <?php endif; ?>
                    </details>
                <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table></div>
</div>
<?php $conexion->close(); admin_layout_fin(); ?>
