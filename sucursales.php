<?php
function sucursales_listar(mysqli $conexion): array
{
    $direcciones = [
        'Sucursal Florida' => 'Florida 537',
        'Sucursal Rivadavia' => 'Av Rivadavia 5376, local 1'
    ];
    $resultado = $conexion->query("SELECT DISTINCT ubicacion FROM stock_gpu WHERE ubicacion IS NOT NULL AND TRIM(ubicacion) <> '' UNION SELECT DISTINCT ubicacion FROM stock WHERE ubicacion IS NOT NULL AND TRIM(ubicacion) <> ''");
    foreach ($resultado as $fila) {
        if (!isset($direcciones[$fila['ubicacion']])) $direcciones[$fila['ubicacion']] = '';
    }
    $sucursales = [];
    foreach ($direcciones as $nombre => $direccion) $sucursales[] = ['ubicacion'=>$nombre, 'nombre'=>$nombre, 'direccion'=>$direccion];
    return $sucursales;
}

function sucursal_validar(mysqli $conexion, string $ubicacion): array
{
    foreach (sucursales_listar($conexion) as $sucursal) {
        if ($sucursal['ubicacion'] === $ubicacion) return $sucursal;
    }
    throw new RuntimeException('Seleccioná una sucursal existente.');
}

function sucursales_para_carrito(mysqli $conexion, array $articulos): array
{
    if (!$articulos) return [];
    $stock = [];
    foreach ($conexion->query('SELECT ubicacion,id_gpu,SUM(cantidad) cantidad FROM stock_gpu WHERE ubicacion IS NOT NULL GROUP BY ubicacion,id_gpu') as $fila) {
        $stock[$fila['ubicacion']][$fila['id_gpu']] = (int)$fila['cantidad'];
    }
    $disponibles = [];
    foreach (sucursales_listar($conexion) as $sucursal) {
        $alcanza = true;
        foreach ($articulos as $articulo) {
            if (($stock[$sucursal['ubicacion']][$articulo['id_gpu']] ?? 0) < (int)$articulo['cantidad']) {
                $alcanza = false;
                break;
            }
        }
        if ($alcanza) $disponibles[(string)$sucursal['ubicacion']] = $sucursal['nombre'] . ' (' . $sucursal['direccion'] . ')';
    }
    return $disponibles;
}
