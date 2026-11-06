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
    return $puesto && in_array($pagina, $configuracion[(string)$puesto[0]] ?? [], true);
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
