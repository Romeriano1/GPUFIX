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
    if ($funcion !== '' && $funcion !== 'componentes' && admin_puede_ver($conexion, admin_permiso_tabla($tabla))) echo '<a class="btn btn-primary" href="' . admin_e($url . '&vista=acciones') . '">Funciones de ' . admin_e(admin_etiqueta_dato($tabla)) . '</a>';
    if ($tabla === 'gpu_ventas' && admin_puede_ver($conexion, 'admin-devoluciones.php')) echo '<a class="btn btn-outline-danger" href="' . admin_e($url . '&vista=acciones&accion_tabla=devoluciones') . '">Devoluciones</a>';
    echo '</nav>';
}

function admin_mostrar_tabla_empleado(mysqli $conexion, string $tabla): void
{
    $tablas = array_column($conexion->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all(MYSQLI_NUM), 0);
    if (!in_array($tabla, $tablas, true) || !admin_puede_tabla($conexion, $tabla)) { http_response_code(403); exit('Acceso restringido.'); }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(403); exit('Abrí las funciones habilitadas para realizar esta operación.'); }
    $pagina = max(1, filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1);
    $cantidad = (int)$conexion->query('SELECT COUNT(*) FROM `' . $tabla . '`')->fetch_row()[0];
    $pagina = min($pagina, max(1, (int)ceil($cantidad / 25)));
    $offset = ($pagina - 1) * 25;
    $filas = $conexion->query('SELECT * FROM `' . $tabla . '` LIMIT 25 OFFSET ' . $offset)->fetch_all(MYSQLI_ASSOC);
    $columnas = array_column($conexion->query('SHOW COLUMNS FROM `' . $tabla . '`')->fetch_all(MYSQLI_ASSOC), 'Field');
    admin_layout_inicio(admin_etiqueta_dato($tabla));
    admin_menu_tablas($tabla);
    admin_tabs_tabla($tabla);
    echo '<section class="panel"><h1 class="h3">' . admin_e(admin_etiqueta_dato($tabla)) . '</h1><div class="table-responsive"><table class="table"><thead><tr>';
    foreach ($columnas as $columna) echo '<th>' . admin_e(admin_etiqueta_dato($columna)) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($filas as $fila) {
        echo '<tr>';
        foreach ($fila as $campo => $valor) echo '<td>' . ($campo === 'contraseña' ? 'Contraseña guardada' : admin_e($valor)) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div><nav class="d-flex gap-3">';
    if ($pagina > 1) echo '<a href="admin-tablas.php?tabla=' . rawurlencode($tabla) . '&amp;pagina=' . ($pagina - 1) . '">Anterior</a>';
    if ($pagina * 25 < $cantidad) echo '<a href="admin-tablas.php?tabla=' . rawurlencode($tabla) . '&amp;pagina=' . ($pagina + 1) . '">Siguiente</a>';
    echo '</nav></section></div></div>';
    admin_layout_fin();
}
