<?php
function admin_secciones_permisos(): array
{
    return [
        'admin.php' => 'Estadísticas',
        'admin-cuentas.php' => 'Cuentas',
        'admin-productos.php' => 'Productos y stock',
        'admin-proveedores.php' => 'Proveedores',
        'admin-presupuestos.php' => 'Presupuestos',
        'admin-reparaciones.php' => 'Reparaciones',
        'admin-facturacion.php' => 'Facturación y envíos',
        'admin-compras.php' => 'Compras a proveedores',
        'admin-componentes.php' => 'Componentes y repuestos',
        'admin-devoluciones.php' => 'Devoluciones',
        'admin-comprobantes.php' => 'Comprobantes'
    ];
}

function admin_leer_permisos(): array
{
    $archivo = __DIR__ . '/permisos-puestos.php';
    if (!is_file($archivo)) return [];
    $contenido = file_get_contents($archivo);
    $prefijo = "<?php exit; ?>\n";
    if ($contenido === false || !str_starts_with($contenido, $prefijo)) {
        throw new RuntimeException('No se pudo leer la configuración de permisos.');
    }
    return json_decode(substr($contenido, strlen($prefijo)), true, 512, JSON_THROW_ON_ERROR);
}

function admin_puede_ver(mysqli $conexion, string $pagina): bool
{
    if (admin_es_cuenta_principal($conexion)) return true;
    if (!isset(admin_secciones_permisos()[$pagina])) return false;
    $consulta = $conexion->prepare('SELECT id_puesto FROM empleado WHERE id_empleado = ?');
    $empleado = (int) ($_SESSION['empleado_id'] ?? 0);
    $consulta->bind_param('i', $empleado);
    $consulta->execute();
    $puesto = $consulta->get_result()->fetch_row();
    $configuracion = admin_leer_permisos();
    if (isset($configuracion['empleados'][(string)$empleado])) {
        $asignadas = $configuracion['empleados'][(string)$empleado];
        if ($pagina === 'admin.php') return false;
        if ($pagina === 'admin-comprobantes.php' || $pagina === 'admin-devoluciones.php') {
            $requeridas = ['gpu_ventas', 'cliente', 'usuarios'];
        } else {
            $requeridas = array_keys(array_filter(admin_funciones_tablas(), static fn ($funcion) => 'admin-' . $funcion . '.php' === $pagina));
        }
        return $requeridas && !array_diff($requeridas, $asignadas);
    }
    return $puesto && in_array($pagina, $configuracion[(string)$puesto[0]] ?? [], true);
}

function admin_puede_tabla(mysqli $conexion, string $tabla): bool
{
    if (admin_es_cuenta_principal($conexion)) return true;
    $configuracion = admin_leer_permisos();
    $empleado = (string) ($_SESSION['empleado_id'] ?? 0);
    if (isset($configuracion['empleados'][$empleado])) {
        return in_array($tabla, $configuracion['empleados'][$empleado], true);
    }
    return admin_puede_ver($conexion, admin_permiso_tabla($tabla));
}

function admin_menu_permitido(mysqli $conexion): string
{
    if (admin_es_cuenta_principal($conexion)) {
        return '<a class="nav-link" href="admin.php">Estadísticas</a>'
            . '<a class="nav-link" href="admin-tablas.php">Tablas SQL</a>'
            . '<a class="nav-link" href="admin-comprobantes.php">Comprobantes</a>'
            . '<a class="nav-link" href="admin-empleos.php">Implementación de Empleos</a>';
    }
    $enlaces = '';
    if (admin_puede_ver($conexion, 'admin.php')) {
        $enlaces .= '<a class="nav-link" href="admin.php">Estadísticas</a>';
    }
    $enlaces .= '<a class="nav-link" href="admin-tablas.php">Tablas SQL</a>';
    if (admin_puede_ver($conexion, 'admin-comprobantes.php')) {
        $enlaces .= '<a class="nav-link" href="admin-comprobantes.php">Comprobantes</a>';
    }
    return $enlaces;
}
