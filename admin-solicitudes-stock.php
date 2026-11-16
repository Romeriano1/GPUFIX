<?php
require_once __DIR__.'/admin-comun.php'; admin_requerir_acceso();
require_once __DIR__.'/stock-solicitudes.php';
if(empty($_SESSION['token_solicitud_stock']))$_SESSION['token_solicitud_stock']=bin2hex(random_bytes(32));
$error=solicitudes_panel_post($conexion,'admin-solicitudes-stock.php');
$mensaje=$_SESSION['mensaje_solicitud_stock']??'';unset($_SESSION['mensaje_solicitud_stock']);
$sucursales=array_values(array_filter(admin_sucursales_disponibles($conexion),static fn($s)=>(int)$s['id_sucursal']!==sucursal_id_deposito()));
$inventario=solicitudes_inventario_deposito($conexion);
$ingresos=($_GET['vista']??'')==='ingresos';
admin_layout_inicio($ingresos?'Ingresos a la sucursal':'Solicitudes de stock');
?>
<h1 class="h3 mt-3"><?= $ingresos?'Ingresos a la sucursal':'Solicitudes de stock' ?></h1>
<p>El Jefe Administrativo solicita la reposición, Compras y Stock valida y abastece el depósito, Logística despacha y la sucursal confirma lo recibido.</p>
<div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" href="admin-solicitudes-stock.php">Pedidos de stock</a><a class="btn btn-outline-primary" href="admin-solicitudes-stock.php?vista=ingresos">Ingresos a la sucursal</a><?php if(admin_es_compras_deposito($conexion)): ?><a class="btn btn-outline-primary" href="<?= admin_e(admin_url_panel('compras')) ?>">Compras a proveedores</a><?php endif; ?></div>
<?php if($error): ?><div class="alert alert-danger mt-3" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
<?php if($mensaje): ?><div class="alert alert-success mt-3" role="status"><?= admin_e($mensaje) ?></div><?php endif; ?>
<?php if(!$ingresos&&admin_puede_pedir_stock($conexion)&&$sucursales): ?>
<section class="panel"><h2 class="h5">Solicitar reposición</h2><form method="post" id="pedidoStock" class="d-grid gap-3">
<input type="hidden" name="admin_csrf" value="<?= admin_e(admin_csrf()) ?>"><input type="hidden" name="accion" value="crear_solicitud"><input type="hidden" name="token_solicitud" value="<?= admin_e($_SESSION['token_solicitud_stock']) ?>">
<div><label class="form-label" for="destinoStock">Sucursal de destino</label><select class="form-select" id="destinoStock" name="id_sucursal" required><?php foreach($sucursales as $sucursal): ?><option value="<?= (int)$sucursal['id_sucursal'] ?>"><?= admin_e($sucursal['nombre']) ?></option><?php endforeach; ?></select></div>
<p class="form-text mb-0">Placas: de 2 a 10 unidades por producto. Los componentes admiten cantidades enteras positivas. Una entrega parcial conserva los faltantes.</p>
<div id="lineasPedido"><div class="fila-pedido row g-3 align-items-end mb-3"><div class="col-md-8"><label class="form-label d-block">Producto<select class="form-select" name="producto[]" required><option value="">Seleccionar producto</option><?php foreach($inventario as $clave=>$producto): ?><option value="<?= admin_e($clave) ?>"><?= admin_e(($producto['tipo']==='gpu'?'Placa · ':'Componente · ').$producto['nombre']) ?></option><?php endforeach; ?></select></label></div><div class="col-md-2"><label class="form-label d-block">Cantidad<input class="form-control" name="cantidad[]" type="number" min="2" max="10" value="2" required></label></div><div class="col-md-2 mb-2"><button class="btn btn-outline-danger" type="button" data-quitar>Quitar</button></div></div></div>
<div><button class="btn btn-outline-primary" type="button" id="sumarLineaPedido">Agregar producto</button></div>
<div><label class="form-label" for="observacionesPedido">Observaciones (opcional)</label><textarea class="form-control" id="observacionesPedido" name="observaciones" rows="2" maxlength="3000"></textarea></div><div><button class="btn btn-primary">Enviar solicitud al depósito</button></div></form></section>
<script>
(()=>{const lista=document.getElementById('lineasPedido'),base=lista.firstElementChild.cloneNode(true);const ajustar=fila=>{const placa=fila.querySelector('select').value.startsWith('gpu:');const n=fila.querySelector('input');n.min=placa?'2':'1';n.max=placa?'10':'1000000';};lista.addEventListener('change',e=>{const fila=e.target.closest('.fila-pedido');if(fila)ajustar(fila);});lista.addEventListener('click',e=>{if(e.target.matches('[data-quitar]')&&lista.children.length>1)e.target.closest('.fila-pedido').remove();});document.getElementById('sumarLineaPedido').addEventListener('click',()=>{const fila=base.cloneNode(true);fila.querySelector('select').value='';lista.append(fila);});})();
</script>
<?php endif; ?>
<p class="small text-secondary mt-4">Faltantes = cantidad solicitada menos cantidad recibida. Las unidades en camino todavía no forman parte del stock vendible de la sucursal.</p>
<?php solicitudes_render_lista($conexion,'admin-solicitudes-stock.php');admin_layout_fin(); ?>
