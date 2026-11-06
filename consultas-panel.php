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
    ORDER BY sin_respuesta DESC, fecha_emision DESC, id_presupuesto DESC
")->fetch_all(MYSQLI_ASSOC);
}

function enlace_presupuesto(array $fila): string
{
    $id = $fila['id_presupuesto'] ? 'p-' . $fila['id_presupuesto'] : 'o-' . $fila['id_orden_reparacion'];
    return 'admin-tablas.php?tabla=presupuesto&vista=acciones#solicitud-' . $id;
}
