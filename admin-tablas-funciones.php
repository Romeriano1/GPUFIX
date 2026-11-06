<?php
function admin_funciones_tablas(): array
{
    return [
        'usuarios' => 'cuentas', 'cliente' => 'cuentas', 'empleado' => 'cuentas',
        'puesto' => 'cuentas', 'carrito' => 'cuentas', 'detalle_carrito' => 'cuentas',
        'gpu' => 'productos', 'stock_gpu' => 'productos',
        'proveedor' => 'proveedores',
        'presupuesto' => 'presupuestos', 'detalle_presupuesto' => 'presupuestos',
        'gpu_cliente' => 'reparaciones', 'orden_reparacion' => 'reparaciones',
        'diagnostico' => 'reparaciones', 'reparacion' => 'reparaciones', 'detalle_reparacion' => 'reparaciones',
        'gpu_ventas' => 'facturacion', 'detalle_venta' => 'facturacion',
        'factura' => 'facturacion', 'detalle_factura' => 'facturacion', 'envios' => 'facturacion',
        'orden_compra' => 'compras', 'detalle_compra' => 'compras',
        'componente' => 'componentes', 'stock' => 'componentes'
    ];
}

function admin_permiso_tabla(string $tabla): string
{
    $funcion = admin_funciones_tablas()[$tabla] ?? '';
    return $funcion === '' ? '' : 'admin-' . $funcion . '.php';
}

function admin_tabs_tabla(string $tabla): void
{
    global $conexion;
    $url = 'admin-tablas.php?tabla=' . rawurlencode($tabla);
    echo '<nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Opciones de la tabla">';
    if (admin_es_cuenta_principal($conexion)) echo '<a class="btn btn-outline-primary" href="' . admin_e($url) . '">Agregar, editar y eliminar registros</a>';
    $funcion = admin_funciones_tablas()[$tabla] ?? '';
    if ($funcion !== '' && $funcion !== 'componentes') echo '<a class="btn btn-primary" href="' . admin_e($url . '&vista=acciones') . '">Funciones de ' . admin_e(admin_etiqueta_dato($tabla)) . '</a>';
    if ($tabla === 'gpu_ventas' && admin_puede_ver($conexion, 'admin-devoluciones.php')) echo '<a class="btn btn-outline-danger" href="' . admin_e($url . '&vista=acciones&accion_tabla=devoluciones') . '">Devoluciones</a>';
    echo '</nav>';
}
