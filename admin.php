<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
$indicadores = [
 'Usuarios registrados' => 'SELECT COUNT(*) FROM usuarios',
 'Clientes' => 'SELECT COUNT(*) FROM cliente',
 'Empleados' => 'SELECT COUNT(*) FROM empleado',
 'Productos' => 'SELECT COUNT(*) FROM gpu',
 'Unidades en stock' => 'SELECT COALESCE(SUM(cantidad),0) FROM stock_gpu',
 'Productos sin stock' => 'SELECT COUNT(*) FROM gpu g WHERE COALESCE((SELECT SUM(s.cantidad) FROM stock_gpu s WHERE s.id_gpu=g.id_gpu),0)=0',
 'Ventas registradas' => 'SELECT COUNT(*) FROM gpu_ventas',
 'Facturas emitidas' => 'SELECT COUNT(*) FROM factura',
 'Presupuestos' => 'SELECT COUNT(*) FROM presupuesto',
 'Órdenes de reparación' => 'SELECT COUNT(*) FROM orden_reparacion',
 'Envíos y retiros' => 'SELECT COUNT(*) FROM envios',
 'Proveedores' => 'SELECT COUNT(*) FROM proveedor'
];
$valores = [];
foreach ($indicadores as $titulo => $sql) $valores[$titulo] = $conexion->query($sql)->fetch_row()[0];
$facturado = $conexion->query('SELECT COALESCE(SUM(total),0) FROM factura')->fetch_row()[0];
$mensuales = $conexion->query("SELECT DATE_FORMAT(fecha_emision,'%Y-%m') mes, COUNT(*) cantidad, SUM(total) total FROM factura GROUP BY mes ORDER BY mes DESC LIMIT 12")->fetch_all(MYSQLI_ASSOC);
$estados = [];
foreach (['gpu_ventas','presupuesto','orden_reparacion','envios'] as $tabla) $estados[$tabla] = $conexion->query("SELECT COALESCE(NULLIF(estado,''),'Sin estado') estado, COUNT(*) cantidad FROM `$tabla` GROUP BY estado ORDER BY cantidad DESC")->fetch_all(MYSQLI_ASSOC);
admin_layout_inicio('Estadísticas');
?>
<h1>Estadísticas de GPUFIX</h1>
<p>Datos actuales de la base. Se actualizan al recargar la página.</p>
<div class="row g-3">
<?php foreach ($valores as $titulo => $valor): ?>
<div class="col-6 col-lg-3"><section class="panel h-100 m-0"><h2 class="h6"><?= admin_e($titulo) ?></h2><strong class="h2"><?= number_format((float)$valor,0,',','.') ?></strong></section></div>
<?php endforeach; ?>
</div>
<section class="panel"><h2 class="h5">Total de facturas registradas</h2><strong class="h2">$ <?= number_format((float)$facturado,2,',','.') ?></strong><p class="mb-0">Suma de todas las facturas, independientemente del estado de pago o devolución.</p></section>
<section class="panel"><h2 class="h5">Facturación por mes</h2><div class="table-responsive"><table class="table"><thead><tr><th>Mes</th><th>Facturas</th><th>Total</th></tr></thead><tbody>
<?php foreach ($mensuales as $mes): ?><tr><td><?= admin_e($mes['mes']) ?></td><td><?= (int)$mes['cantidad'] ?></td><td>$ <?= number_format((float)$mes['total'],2,',','.') ?></td></tr><?php endforeach; ?>
<?php if (!$mensuales): ?><tr><td colspan="3">Todavía no hay facturas.</td></tr><?php endif; ?>
</tbody></table></div></section>
<div class="row">
<?php foreach ($estados as $tabla => $filas): ?><div class="col-md-6"><section class="panel"><h2 class="h5"><?= admin_e(['gpu_ventas'=>'Ventas','presupuesto'=>'Presupuestos','orden_reparacion'=>'Reparaciones','envios'=>'Envíos y retiros'][$tabla]) ?> por estado</h2><table class="table"><thead><tr><th>Estado</th><th>Cantidad</th></tr></thead><tbody><?php foreach ($filas as $fila): ?><tr><td><?= admin_e($fila['estado']) ?></td><td><?= (int)$fila['cantidad'] ?></td></tr><?php endforeach; ?><?php if (!$filas): ?><tr><td colspan="2">Sin registros.</td></tr><?php endif; ?></tbody></table></section></div><?php endforeach; ?>
</div>
<?php admin_layout_fin(); ?>
