<?php
// Reglas que las claves foráneas, por sí solas, no pueden comprobar.
function admin_reglas_relaciones(): array {
    return [
        ['El presupuesto debe estar vinculado a un cliente.',
         'SELECT p.id_presupuesto FROM presupuesto p WHERE p.id_cliente IS NULL',
         ['presupuesto'=>'p.id_presupuesto']],
        ['El empleado debe estar vinculado a un usuario.',
         'SELECT e.id_empleado FROM empleado e WHERE e.id_correo IS NULL',
         ['empleado'=>'e.id_empleado']],
        ['El cliente del envío debe ser el mismo que el de la venta.',
         'SELECT e.id_envios FROM envios e JOIN gpu_ventas v ON v.id_gpu_ventas=e.id_gpu_ventas WHERE e.id_cliente<>v.id_cliente',
         ['envios'=>'e.id_envios','gpu_ventas'=>'v.id_gpu_ventas']],
        ['La factura debe corresponder a una venta o una reparación, no a ambas ni a ninguna.',
         'SELECT f.id_factura FROM factura f WHERE (f.id_gpu_ventas IS NULL) = (f.id_reparacion IS NULL)',
         ['factura'=>'f.id_factura']],
        ['El cliente de la factura debe ser el mismo que el de la venta.',
         'SELECT f.id_factura FROM factura f JOIN gpu_ventas v ON v.id_gpu_ventas=f.id_gpu_ventas WHERE f.id_cliente<>v.id_cliente',
         ['factura'=>'f.id_factura','gpu_ventas'=>'v.id_gpu_ventas']],
        ['El cliente de la factura debe ser el dueño de la placa reparada.',
         'SELECT f.id_factura FROM factura f JOIN reparacion r ON r.id_reparacion=f.id_reparacion JOIN orden_reparacion o ON o.id_orden_reparacion=r.id_orden_reparacion JOIN gpu_cliente g ON g.id_gpu_cliente=o.id_gpu_cliente WHERE f.id_cliente<>g.id_cliente',
         ['factura'=>'f.id_factura','reparacion'=>'r.id_reparacion','orden_reparacion'=>'o.id_orden_reparacion','gpu_cliente'=>'g.id_gpu_cliente']],
        ['El cliente del presupuesto debe ser el dueño de la placa de esa orden de reparación.',
         'SELECT p.id_presupuesto FROM presupuesto p JOIN orden_reparacion o ON o.id_orden_reparacion=p.id_orden_reparacion JOIN gpu_cliente g ON g.id_gpu_cliente=o.id_gpu_cliente WHERE p.id_cliente<>g.id_cliente OR p.id_cliente IS NULL',
         ['presupuesto'=>'p.id_presupuesto','orden_reparacion'=>'o.id_orden_reparacion','gpu_cliente'=>'g.id_gpu_cliente']],
        ['Cada detalle de compra debe indicar una placa o un componente, no ambos ni ninguno.',
         'SELECT d.id_detalle_compra FROM detalle_compra d WHERE (d.id_gpu IS NULL) = (d.id_componente IS NULL)',
         ['detalle_compra'=>'d.id_detalle_compra']]
    ];
}

function admin_validar_relaciones(mysqli $db, string $tabla, string $id): void {
    foreach (admin_reglas_relaciones() as [$mensaje, $sql, $alcance]) {
        if (!isset($alcance[$tabla])) continue;
        // Encerrar el WHERE permite añadir el alcance incluso si contiene OR.
        [$inicio, $condicion] = explode(' WHERE ', $sql, 2);
        $consulta = $db->prepare($inicio . ' WHERE (' . $condicion . ') AND ' . $alcance[$tabla] . ' = ? LIMIT 1 FOR UPDATE');
        $consulta->bind_param('s', $id);
        $consulta->execute();
        $invalido = $consulta->get_result()->fetch_row();
        $consulta->close();
        if ($invalido) throw new RuntimeException($mensaje . ' Revisá el registro #' . $invalido[0] . '.');
    }
}
