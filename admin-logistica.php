<?php
require_once __DIR__.'/admin-comun.php';admin_requerir_acceso();
require_once __DIR__.'/stock-solicitudes.php';
if(!admin_es_logistica_deposito($conexion)){http_response_code(403);admin_layout_inicio('Acceso restringido');echo '<p>Este panel corresponde al personal de Logística asignado al Depósito.</p>';admin_layout_fin();exit;}
$error=solicitudes_panel_post($conexion,'admin-logistica.php');
$mensaje=$_SESSION['mensaje_solicitud_stock']??'';unset($_SESSION['mensaje_solicitud_stock']);
$inventario=solicitudes_inventario_deposito($conexion);
admin_layout_inicio('Logística y Depósito');
?>
<h1 class="h3 mt-3">Logística y Depósito</h1><p>Prepará los pedidos internos y despachá lo disponible. Compras y Stock se encarga del abastecimiento a proveedores.</p>
<div class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="#pedidos-sucursales">Pedidos de sucursales</a><?php if(admin_puede_ver($conexion,'admin-envios.php')): ?><a class="btn btn-outline-primary" href="<?= admin_e(admin_url_panel('envios')) ?>">Envíos a clientes</a><?php endif; ?></div>
<?php if($error): ?><div class="alert alert-danger mt-3" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
<?php if($mensaje): ?><div class="alert alert-success mt-3" role="status"><?= admin_e($mensaje) ?></div><?php endif; ?>
<details class="panel"><summary class="h5 mb-0">Existencias del depósito</summary><div class="table-responsive mt-3"><table class="table"><thead><tr><th>Producto</th><th>Disponible</th></tr></thead><tbody><?php foreach($inventario as $producto): ?><tr><td><?= admin_e($producto['nombre']) ?></td><td><?= $producto['cantidad'] ?></td></tr><?php endforeach; ?></tbody></table></div></details>
<div id="pedidos-sucursales"><h2 class="h4 mt-4">Pedidos de sucursales</h2><p class="text-secondary">Podés despachar parcialmente. El resto queda pendiente; no se agrega stock al destino hasta que confirme la recepción.</p><?php solicitudes_render_lista($conexion,'admin-logistica.php'); ?></div>
<?php admin_layout_fin(); ?>
