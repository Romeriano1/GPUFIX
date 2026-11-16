<?php
// La asignación consume inventario; no permite ajustes manuales de stock.
function componentes_asignar_reparacion(mysqli $db, int $orden, int $componente, int $cantidad, string $sucursal): void
{
    if (!admin_puede_ver($db, 'admin-reparaciones.php')) throw new RuntimeException('No tenés acceso a Reparaciones.');
    if ($orden < 1 || $componente < 1 || $cantidad < 1 || $cantidad > 1000000) throw new RuntimeException('Seleccioná un componente y una cantidad entre 1 y 1.000.000.');
    admin_validar_sucursal_empleado($db, $sucursal);
    $db->begin_transaction();
    try {
        $consulta = $db->prepare('SELECT estado FROM orden_reparacion WHERE id_orden_reparacion=? FOR UPDATE');
        $consulta->bind_param('i', $orden); $consulta->execute();
        $ordenActual = $consulta->get_result()->fetch_assoc(); $consulta->close();
        if (!$ordenActual || in_array($ordenActual['estado'], ['Finalizada', 'Entregada', 'Cancelada'], true)) throw new RuntimeException('La reparación no existe o ya está cerrada.');
        $consulta = $db->prepare('SELECT id_reparacion FROM reparacion WHERE id_orden_reparacion=? ORDER BY id_reparacion LIMIT 1 FOR UPDATE');
        $consulta->bind_param('i', $orden); $consulta->execute();
        $trabajo = $consulta->get_result()->fetch_assoc(); $consulta->close();
        if (!$trabajo) throw new RuntimeException('Primero cambiá la orden a En reparación para iniciar el trabajo.');
        $consulta = $db->prepare('SELECT id_componente FROM componente WHERE id_componente=? FOR UPDATE');
        $consulta->bind_param('i', $componente); $consulta->execute();
        $existe = $consulta->get_result()->fetch_row(); $consulta->close();
        if (!$existe) throw new RuntimeException('El componente ya no existe.');
        $consulta = $db->prepare('SELECT id_stock,cantidad FROM stock WHERE id_componente=? AND id_sucursal=? ORDER BY id_stock FOR UPDATE');
        $consulta->bind_param('is', $componente, $sucursal); $consulta->execute();
        $filas = $consulta->get_result()->fetch_all(MYSQLI_ASSOC); $consulta->close();
        $disponible = array_sum(array_map(static fn($fila) => max(0, (int)$fila['cantidad']), $filas));
        if ($disponible < $cantidad) throw new RuntimeException('No hay stock suficiente de ese componente en la sucursal seleccionada.');
        $pendiente = $cantidad;
        foreach ($filas as $fila) {
            $descontar = min($pendiente, max(0, (int)$fila['cantidad']));
            if (!$descontar) continue;
            $restante = (int)$fila['cantidad'] - $descontar;
            $estado = $restante > 0 ? 'Disponible' : 'Sin stock';
            $consulta = $db->prepare('UPDATE stock SET cantidad=?,estado=? WHERE id_stock=?');
            $consulta->bind_param('isi', $restante, $estado, $fila['id_stock']); $consulta->execute(); $consulta->close();
            $pendiente -= $descontar;
            if (!$pendiente) break;
        }
        $consulta = $db->prepare('INSERT INTO detalle_reparacion(id_reparacion,id_componente,cantidad) VALUES(?,?,?)');
        $consulta->bind_param('iii', $trabajo['id_reparacion'], $componente, $cantidad); $consulta->execute(); $consulta->close();
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}
