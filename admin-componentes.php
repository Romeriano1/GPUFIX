<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
if (admin_es_cuenta_principal($conexion)) { header('Location: admin-tablas.php?tabla=componente',true,303); exit; }
$componentes = $conexion->query('SELECT c.id_componente,c.nombre,c.descripcion,COALESCE(SUM(s.cantidad),0) cantidad FROM componente c LEFT JOIN stock s ON s.id_componente=c.id_componente GROUP BY c.id_componente,c.nombre,c.descripcion ORDER BY c.nombre')->fetch_all(MYSQLI_ASSOC);
admin_layout_inicio('Componentes y repuestos');
?>
<h1 class="h3">Componentes y repuestos</h1><section class="panel"><div class="table-responsive"><table class="table"><thead><tr><th>Componente</th><th>Descripción</th><th>Stock</th></tr></thead><tbody>
<?php foreach($componentes as $fila): ?><tr><td><?= admin_e($fila['nombre']) ?></td><td><?= admin_e($fila['descripcion']) ?></td><td><?= (int)$fila['cantidad'] ?></td></tr><?php endforeach; ?>
<?php if(!$componentes): ?><tr><td colspan="3">Todavía no hay componentes.</td></tr><?php endif; ?></tbody></table></div></section>
<?php admin_layout_fin(); ?>
