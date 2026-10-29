<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
require_once __DIR__ . '/admin-edicion-datos.php';
admin_edicion_seccion('Componentes y repuestos', ['componente', 'stock'], 'admin-componentes.php');
admin_layout_inicio('Componentes y repuestos');
?>
<h1 class="h3 mt-3">Componentes y repuestos</h1>
<p>Consultá los registros y sus detalles desde la edición de esta sección.</p>
<?php admin_layout_fin();
