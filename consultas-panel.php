<?php
// Incluye los presupuestos actuales y las solicitudes anteriores vinculadas a una orden.
function consultas_presupuestos(mysqli $conexion): array
{
    return $conexion->query("
    SELECT p.id_presupuesto, p.id_orden_reparacion, p.fecha_emision,
           COALESCE(p.consulta, o.motivo, '') AS consulta, p.respuesta, p.fecha_respuesta,
           p.total, p.estado, u.nombre, u.apellido, u.correo,
           (NULLIF(TRIM(p.respuesta), '') IS NULL) AS sin_respuesta
    FROM presupuesto p
    LEFT JOIN orden_reparacion o ON o.id_orden_reparacion = p.id_orden_reparacion
    LEFT JOIN gpu_cliente g ON g.id_gpu_cliente = o.id_gpu_cliente
    INNER JOIN cliente c ON c.id_cliente = COALESCE(p.id_cliente, g.id_cliente)
    INNER JOIN usuarios u ON u.id_correo = c.id_correo
    WHERE COALESCE(p.estado, '') NOT IN ('Consulta de garantía', 'Reparación en garantía')
    UNION ALL
    SELECT NULL, o.id_orden_reparacion, o.fecha_ingreso, o.motivo, NULL, NULL,
           NULL, o.estado, u.nombre, u.apellido, u.correo, 1
    FROM orden_reparacion o
    INNER JOIN gpu_cliente g ON g.id_gpu_cliente = o.id_gpu_cliente
    INNER JOIN cliente c ON c.id_cliente = g.id_cliente
    INNER JOIN usuarios u ON u.id_correo = c.id_correo
    LEFT JOIN presupuesto p ON p.id_orden_reparacion = o.id_orden_reparacion
    WHERE p.id_presupuesto IS NULL
      AND o.fecha_egreso IS NULL
      AND LOWER(COALESCE(o.estado, '')) NOT IN ('finalizada', 'finalizado', 'cancelada', 'cancelado')
      AND NOT EXISTS (SELECT 1 FROM diagnostico d WHERE d.id_orden_reparacion=o.id_orden_reparacion)
      AND NOT EXISTS (SELECT 1 FROM reparacion r WHERE r.id_orden_reparacion=o.id_orden_reparacion)
    ORDER BY sin_respuesta DESC, fecha_emision DESC, id_presupuesto DESC
")->fetch_all(MYSQLI_ASSOC);
}

function enlace_presupuesto(array $fila): string
{
    $id = $fila['id_presupuesto'] ? 'p-' . $fila['id_presupuesto'] : 'o-' . $fila['id_orden_reparacion'];
    return admin_url_panel('presupuestos') . '#solicitud-' . $id;
}

// El llamador mantiene una transacción abierta: todas las bajas se confirman juntas.
function consultas_eliminar_presupuesto(mysqli $conexion, int $idPresupuesto, int $idOrden = 0): void
{
    $consultar = static function (string $sql, int $id) use ($conexion): mysqli_stmt {
        $consulta = $conexion->prepare($sql);
        $consulta->bind_param('i', $id);
        $consulta->execute();
        return $consulta;
    };
    if ($idPresupuesto > 0) {
        $fila = $consultar('SELECT id_orden_reparacion FROM presupuesto WHERE id_presupuesto=? FOR UPDATE', $idPresupuesto)->get_result()->fetch_assoc();
        if (!$fila) throw new RuntimeException('El presupuesto ya no existe.');
        $idOrden = (int) ($fila['id_orden_reparacion'] ?? 0);
    } elseif ($idOrden <= 0) {
        throw new RuntimeException('Seleccioná una consulta válida.');
    }
    if ($idOrden > 0) {
        if (!$consultar('SELECT id_orden_reparacion FROM orden_reparacion WHERE id_orden_reparacion=? FOR UPDATE', $idOrden)->get_result()->fetch_assoc()) throw new RuntimeException('La orden de la consulta ya no existe.');
        if (!$idPresupuesto && $consultar('SELECT id_presupuesto FROM presupuesto WHERE id_orden_reparacion=? FOR UPDATE', $idOrden)->get_result()->fetch_assoc()) throw new RuntimeException('La consulta cambió. Recargá y eliminá el presupuesto actual.');
    }
    if ($idPresupuesto > 0) {
        $consultar('DELETE FROM detalle_presupuesto WHERE id_presupuesto=?', $idPresupuesto);
        $consultar('DELETE FROM presupuesto WHERE id_presupuesto=?', $idPresupuesto);
    }
    if ($idOrden > 0) {
        $tienePresupuesto = (bool) $consultar('SELECT id_presupuesto FROM presupuesto WHERE id_orden_reparacion=? FOR UPDATE', $idOrden)->get_result()->fetch_assoc();
        $tieneDiagnostico = (bool) $consultar('SELECT id_diagnostico FROM diagnostico WHERE id_orden_reparacion=? FOR UPDATE', $idOrden)->get_result()->fetch_assoc();
        $tieneReparacion = (bool) $consultar('SELECT id_reparacion FROM reparacion WHERE id_orden_reparacion=? FOR UPDATE', $idOrden)->get_result()->fetch_assoc();
        if (!$tienePresupuesto && !$tieneDiagnostico && !$tieneReparacion) {
            $consultar('DELETE FROM orden_reparacion WHERE id_orden_reparacion=?', $idOrden);
        } elseif (!$idPresupuesto) {
            throw new RuntimeException('Esta orden tiene diagnósticos o reparaciones. Se conserva su historial y no corresponde a una consulta pendiente.');
        }
    }
}
