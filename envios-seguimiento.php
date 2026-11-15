<?php
function envios_estados(): array
{
    return ['Pendiente de envío', 'En preparación', 'Listo para despacho', 'En camino', 'Entregado',
        'Retiro en tienda', 'Listo para retirar', 'Retirado', 'Cancelado'];
}

function envio_progreso(string $estado): array
{
    $esRetiro = in_array($estado, ['Retiro en tienda', 'Listo para retirar', 'Retirado'], true);
    $etapas = $esRetiro ? ['Retiro en tienda', 'Listo para retirar', 'Retirado']
        : ['Pendiente de envío', 'En preparación', 'Listo para despacho', 'En camino', 'Entregado'];
    $posicion = array_search($estado === 'Enviado' ? 'En camino' : $estado, $etapas, true);
    return ['etapas'=>$etapas, 'actual'=>$posicion, 'cancelado'=>$estado === 'Cancelado'];
}

function envio_consultar_cliente(mysqli $conexion, int $idCliente, string $codigo): ?array
{
    $consulta = $conexion->prepare('SELECT e.id_envios, e.id_gpu_ventas, e.fecha, e.estado, e.direccion_envio, e.codigo_seguimiento '
        . 'FROM envios e INNER JOIN gpu_ventas v ON v.id_gpu_ventas=e.id_gpu_ventas AND v.id_cliente=e.id_cliente '
        . 'WHERE e.id_cliente=? AND e.codigo_seguimiento=? ORDER BY e.id_envios DESC LIMIT 1');
    $consulta->bind_param('is', $idCliente, $codigo);
    $consulta->execute();
    $envio = $consulta->get_result()->fetch_assoc();
    $consulta->close();
    return $envio;
}
