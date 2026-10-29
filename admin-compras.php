<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
require_once __DIR__ . '/admin-edicion-datos.php';
admin_edicion_seccion('Compras a proveedores', ['orden_compra', 'detalle_compra'], 'admin-compras.php');
admin_layout_inicio('Compras a proveedores');
?>
<h1 class="h3 mt-3">Compras a proveedores</h1>
<p>Consultá los registros y sus detalles desde la edición de esta sección.</p>
<?php admin_layout_fin();
