<?php
require_once __DIR__.'/admin-comun.php';
admin_requerir_acceso();
require_once __DIR__.'/conexion.php';
$facturas=$conexion->query("SELECT f.id_factura,f.numero_factura,f.fecha_emision,f.total,COALESCE(u.correo,'Cliente no disponible') correo,f.id_gpu_ventas,f.id_reparacion FROM factura f LEFT JOIN cliente c ON c.id_cliente=f.id_cliente LEFT JOIN usuarios u ON u.id_correo=c.id_correo ORDER BY f.fecha_emision DESC,f.id_factura DESC");
$ventas=$conexion->query("SELECT v.id_gpu_ventas,v.fecha_venta,v.precio_total,v.metodo_pago,v.estado,COALESCE(u.correo,'Cliente no disponible') correo FROM gpu_ventas v LEFT JOIN cliente c ON c.id_cliente=v.id_cliente LEFT JOIN usuarios u ON u.id_correo=c.id_correo ORDER BY v.fecha_venta DESC,v.id_gpu_ventas DESC");
admin_layout_inicio('Facturación');
?>
<h1 class="h3 mt-3">Facturación y ventas</h1><p class="text-secondary">Listado de facturas emitidas y ventas registradas en la base de datos.</p>
<div class="panel"><h2 class="h5">Facturas</h2><div class="table-responsive"><table class="table table-striped"><thead><tr><th>N.º</th><th>Fecha</th><th>Cliente</th><th>Origen</th><th>Total</th></tr></thead><tbody>
<?php if($facturas->num_rows===0): ?><tr><td colspan="5" class="text-center text-secondary py-4">Todavía no hay facturas registradas.</td></tr><?php else: while($f=$facturas->fetch_assoc()): ?><tr><td><?=admin_e($f['numero_factura'])?></td><td><?=admin_e($f['fecha_emision'])?></td><td><?=admin_e($f['correo'])?></td><td><?=$f['id_gpu_ventas']?'Venta #'.(int)$f['id_gpu_ventas']:($f['id_reparacion']?'Reparación #'.(int)$f['id_reparacion']:'—')?></td><td>$ <?=number_format((float)$f['total'],2,',','.')?></td></tr><?php endwhile; endif; ?>
</tbody></table></div></div>
<div class="panel"><h2 class="h5">Ventas registradas</h2><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Venta</th><th>Fecha</th><th>Cliente</th><th>Método de pago</th><th>Estado</th><th>Total</th></tr></thead><tbody>
<?php if($ventas->num_rows===0): ?><tr><td colspan="6" class="text-center text-secondary py-4">Todavía no hay ventas registradas. Esta sección se completará cuando el proceso de compra guarde las operaciones en la tabla gpu_ventas.</td></tr><?php else: while($v=$ventas->fetch_assoc()): ?><tr><td>#<?=(int)$v['id_gpu_ventas']?></td><td><?=admin_e($v['fecha_venta'])?></td><td><?=admin_e($v['correo'])?></td><td><?=admin_e($v['metodo_pago']?:'No indicado')?></td><td><?=admin_e($v['estado']?:'Sin estado')?></td><td>$ <?=number_format((float)$v['precio_total'],2,',','.')?></td></tr><?php endwhile; endif; ?>
</tbody></table></div></div>
<?php $conexion->close(); admin_layout_fin(); ?>
