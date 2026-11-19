<?php
function sucursales_listar(mysqli $conexion): array
{
    return $conexion->query('SELECT id_sucursal,nombre,direccion FROM sucursales ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
}

function sucursal_validar(mysqli $conexion, string $idSucursal): array
{
    foreach (sucursales_listar($conexion) as $sucursal) {
        if ((string)$sucursal['id_sucursal'] === $idSucursal) return $sucursal;
    }
    throw new RuntimeException('Seleccioná una sucursal existente.');
}

function sucursales_para_carrito(mysqli $conexion, array $articulos): array
{
    if (!$articulos) return [];
    $stock = [];
    foreach ($conexion->query('SELECT id_sucursal,id_gpu,SUM(cantidad) cantidad FROM stock_gpu GROUP BY id_sucursal,id_gpu') as $fila) {
        $stock[$fila['id_sucursal']][$fila['id_gpu']] = (int)$fila['cantidad'];
    }
    $disponibles = [];
    foreach (sucursales_listar($conexion) as $sucursal) {
        $alcanza = true;
        foreach ($articulos as $articulo) {
            if (($stock[$sucursal['id_sucursal']][$articulo['id_gpu']] ?? 0) < (int)$articulo['cantidad']) {
                $alcanza = false;
                break;
            }
        }
        if ($alcanza) $disponibles[(string)$sucursal['id_sucursal']] = $sucursal['nombre'] . ' (' . $sucursal['direccion'] . ')';
    }
    return $disponibles;
}
