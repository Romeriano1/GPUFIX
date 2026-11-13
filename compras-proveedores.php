<?php
require_once __DIR__ . "/sucursales.php";
// El llamador inicia y confirma la transacción de compra y stock.
function compras_validar_lineas(array $productos, array $cantidades, array $costos): array
{
    if (!$productos || count($productos) > 50 || count($productos) !== count($cantidades) || count($productos) !== count($costos)) {
        throw new RuntimeException('Agregá entre 1 y 50 productos y completá sus cantidades y costos.');
    }
    $productos = array_values($productos);
    $cantidades = array_values($cantidades);
    $costos = array_values($costos);
    $lineas = [];
    $totalCentavos = 0;
    foreach ($productos as $indice => $producto) {
        $cantidad = $cantidades[$indice];
        $costo = $costos[$indice];
        if (!is_string($producto) || !preg_match('/^(gpu|componente):([1-9][0-9]{0,9})$/D', $producto, $partes)) {
            throw new RuntimeException('Seleccioná un producto válido en cada fila.');
        }
        if (!is_string($cantidad) || !preg_match('/^[1-9][0-9]{0,6}$/D', $cantidad) || (int) $cantidad > 1000000) {
            throw new RuntimeException('Cada cantidad debe ser un número entero entre 1 y 1.000.000.');
        }
        $costo = is_string($costo) ? str_replace(',', '.', trim($costo)) : '';
        if (!preg_match('/^([0-9]{1,10})(?:\.([0-9]{1,2}))?$/D', $costo, $importe)) {
            throw new RuntimeException('Ingresá el costo unitario con hasta dos decimales, sin separador de miles.');
        }
        if (isset($lineas[$producto])) throw new RuntimeException('El mismo producto aparece más de una vez. Unificá su cantidad en una fila.');
        $centavos = (int) $importe[1] * 100 + (int) str_pad($importe[2] ?? '', 2, '0');
        $totalCentavos += $centavos * (int) $cantidad;
        if ($totalCentavos > 999999999999) throw new RuntimeException('El total de la compra supera el importe permitido de $9.999.999.999,99.');
        $lineas[$producto] = [
            'tipo' => $partes[1], 'id' => (int) $partes[2], 'cantidad' => (int) $cantidad,
            'costo' => intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT)
        ];
    }
    uasort($lineas, static fn(array $a, array $b): int => strcmp($a['tipo'], $b['tipo']) ?: $a['id'] <=> $b['id']);
    return ['lineas' => array_values($lineas), 'total' => intdiv($totalCentavos, 100) . '.' . str_pad((string) ($totalCentavos % 100), 2, '0', STR_PAD_LEFT)];
}

function compras_registrar_recepcion(mysqli $db, int $proveedor, int $empleado, array $compra, string $idSucursal): int
{
    sucursal_validar($db, $idSucursal);
    $buscar = $db->prepare('SELECT id_proveedor FROM proveedor WHERE id_proveedor = ? LOCK IN SHARE MODE');
    $buscar->bind_param('i', $proveedor);
    $buscar->execute();
    $existe = $buscar->get_result()->fetch_assoc();
    $buscar->close();
    if (!$existe) throw new RuntimeException('El proveedor seleccionado ya no existe. Elegí otro proveedor.');
    $crear = $db->prepare("INSERT INTO orden_compra (id_proveedor, id_empleado_recepcion, estado, total) VALUES (?, ?, 'Recibida', ?)");
    $crear->bind_param('iis', $proveedor, $empleado, $compra['total']);
    $crear->execute();
    $idCompra = (int) $db->insert_id;
    $crear->close();
    foreach ($compra['lineas'] as $linea) {
        $esGpu = $linea['tipo'] === 'gpu';
        $tablaProducto = $esGpu ? 'gpu' : 'componente';
        $campoProducto = $esGpu ? 'id_gpu' : 'id_componente';
        $tablaStock = $esGpu ? 'stock_gpu' : 'stock';
        $campoStock = $esGpu ? 'id_stock_gpu' : 'id_stock';
        $nombreProducto = $esGpu ? "CONCAT(marca, ' ', modelo)" : 'nombre';
        $idProducto = $linea['id'];
        $buscar = $db->prepare("SELECT $nombreProducto AS nombre FROM $tablaProducto WHERE $campoProducto = ? FOR UPDATE");
        $buscar->bind_param('i', $idProducto);
        $buscar->execute();
        $producto = $buscar->get_result()->fetch_assoc();
        $buscar->close();
        if (!$producto) throw new RuntimeException('Uno de los productos seleccionados ya no existe. Revisá la compra.');
        $buscar = $db->prepare("SELECT $campoStock AS id, cantidad FROM $tablaStock WHERE $campoProducto = ? AND id_sucursal = ? ORDER BY $campoStock FOR UPDATE");
        $buscar->bind_param('is', $idProducto, $idSucursal);
        $buscar->execute();
        $stocks = $buscar->get_result()->fetch_all(MYSQLI_ASSOC);
        $buscar->close();
        $cantidad = $linea['cantidad'];
        if ($stocks) {
            $stock = $stocks[0];
            if ((int) $stock['cantidad'] < 0 || (int) $stock['cantidad'] + $cantidad > 2147483647) {
                throw new RuntimeException('La cantidad resultante de stock no es válida. Revisá las cantidades del producto.');
            }
            $actualizar = $db->prepare("UPDATE $tablaStock SET cantidad = cantidad + ?, estado = 'Disponible' WHERE $campoStock = ?");
            $actualizar->bind_param('ii', $cantidad, $stock['id']);
        } else {
            $actualizar = $db->prepare("INSERT INTO $tablaStock ($campoProducto, cantidad, estado, id_sucursal) VALUES (?, ?, 'Disponible', ?)");
            $actualizar->bind_param('iis', $idProducto, $cantidad, $idSucursal);
        }
        $actualizar->execute();
        $actualizar->close();
        $gpu = $esGpu ? $idProducto : null;
        $componente = $esGpu ? null : $idProducto;
        $descripcion = mb_substr($producto['nombre'], 0, 255, 'UTF-8');
        $detalle = $db->prepare('INSERT INTO detalle_compra (id_orden_compra, id_gpu, id_componente, cantidad, precio_unitario, descripcion) VALUES (?, ?, ?, ?, ?, ?)');
        $detalle->bind_param('iiiiss', $idCompra, $gpu, $componente, $cantidad, $linea['costo'], $descripcion);
        $detalle->execute();
        $detalle->close();
    }
    return $idCompra;
}
