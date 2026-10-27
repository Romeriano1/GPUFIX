<?php
require_once __DIR__.'/admin-comun.php';
admin_requerir_acceso();
require_once __DIR__.'/conexion.php';
$productos = (int)$conexion->query("SELECT COUNT(*) total FROM gpu")->fetch_assoc()['total'];
$unidades = (int)$conexion->query("SELECT COALESCE(SUM(cantidad),0) total FROM stock_gpu")->fetch_assoc()['total'];
$ventas = (int)$conexion->query("SELECT COUNT(*) total FROM gpu_ventas")->fetch_assoc()['total'];
$facturas = (int)$conexion->query("SELECT COUNT(*) total FROM factura")->fetch_assoc()['total'];
admin_layout_inicio('Inicio');
?>
<h1 class="h3 mt-3">Panel de administración</h1><p class="text-secondary">Desde acá podés administrar el catálogo, el inventario, las facturas y las devoluciones.</p>
<div class="row g-3 mt-2">
<div class="col-sm-6 col-lg-3"><div class="panel"><div class="small-muted">Productos cargados</div><div class="display-6 fw-semibold"><?=$productos?></div><a href="admin-productos.php">Administrar productos →</a></div></div>
<div class="col-sm-6 col-lg-3"><div class="panel"><div class="small-muted">Unidades en stock</div><div class="display-6 fw-semibold"><?=$unidades?></div><a href="admin-productos.php">Configurar stock →</a></div></div>
<div class="col-sm-6 col-lg-3"><div class="panel"><div class="small-muted">Ventas registradas</div><div class="display-6 fw-semibold"><?=$ventas?></div><a href="admin-facturacion.php">Ver ventas →</a></div></div>
<div class="col-sm-6 col-lg-3"><div class="panel"><div class="small-muted">Facturas emitidas</div><div class="display-6 fw-semibold"><?=$facturas?></div><a href="admin-facturacion.php">Ver facturación →</a></div></div>
</div>
<div class="panel"><h2 class="h5">Antes de probar</h2><ul class="mb-0"><li>Los productos y el stock se guardan en la base de datos actual.</li><li>La sección de facturación muestra las ventas y facturas que existan en la base de datos.</li><li>La devolución permite registrar el estado de la operación y reintegrar las unidades al stock cuando corresponde. No procesa una transferencia de dinero real.</li></ul></div>
<?php $conexion->close(); admin_layout_fin(); ?>
