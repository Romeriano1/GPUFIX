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
        'admin-comprobantes.php' => 'Comprobantes',
        'admin-empleos.php' => 'Empleados y permisos',
        'admin-asistencia.php' => 'Asistencia'
    ];
}

function admin_leer_permisos(): array
{
    $archivo = __DIR__ . '/permisos-puestos.php';
    if (!is_file($archivo)) return [];
    $contenido = file_get_contents($archivo);
    if ($contenido !== false) $contenido = str_replace(["\r\n", "\r"], "\n", $contenido);
    $prefijo = "<?php exit; ?>\n";
    if ($contenido === false || !str_starts_with($contenido, $prefijo)) {
        throw new RuntimeException('No se pudo leer la configuración de permisos.');
    }
    return json_decode(substr($contenido, strlen($prefijo)), true, 512, JSON_THROW_ON_ERROR);
}

function admin_roles_organigrama(): array
{
    return [
        'Dirección' => [
            'Dirección General' => array_keys(admin_secciones_permisos()),
        ],
        'Comercial' => [
            'Jefe Comercial' => ['admin.php', 'admin-cuentas.php', 'admin-productos.php', 'admin-presupuestos.php', 'admin-facturacion.php', 'admin-devoluciones.php', 'admin-comprobantes.php'],
            'Atención al Cliente' => ['admin-cuentas.php', 'admin-presupuestos.php'],
            'Presupuestos' => ['admin-productos.php', 'admin-presupuestos.php', 'admin-componentes.php'],
        ],
        'Técnica' => [
            'Jefe Técnico' => ['admin.php', 'admin-productos.php', 'admin-presupuestos.php', 'admin-reparaciones.php', 'admin-componentes.php'],
            'Técnicos de Diagnóstico' => ['admin-reparaciones.php'],
            'Técnicos de Reparación y Mantenimiento' => ['admin-reparaciones.php', 'admin-componentes.php'],
            'Técnicos de Laboratorio' => ['admin-reparaciones.php'],
        ],
        'Administración y RRHH' => [
            'Jefe Administrativo y RRHH' => ['admin.php', 'admin-cuentas.php', 'admin-productos.php', 'admin-proveedores.php', 'admin-facturacion.php', 'admin-compras.php', 'admin-componentes.php', 'admin-devoluciones.php', 'admin-comprobantes.php'],
            'Contabilidad' => ['admin.php', 'admin-facturacion.php', 'admin-compras.php', 'admin-comprobantes.php'],
            'Facturación' => ['admin-facturacion.php', 'admin-devoluciones.php', 'admin-comprobantes.php'],
            'Compras y Stock' => ['admin-productos.php', 'admin-proveedores.php', 'admin-compras.php', 'admin-componentes.php'],
            'RRHH' => ['admin-cuentas.php'],
        ],
    ];
}

function admin_puede_ver(mysqli $conexion, string $pagina): bool
{
    if (admin_es_cuenta_principal($conexion)) return true;
    if ($pagina === 'admin-asistencia.php') return admin_es_cuenta_principal($conexion) || admin_es_rrhh($conexion);
    if ($pagina === 'admin-empleos.php' && admin_es_cuenta_principal($conexion)) return true;
    if (!isset(admin_secciones_permisos()[$pagina])) return false;
    $consulta = $conexion->prepare('SELECT e.id_puesto,p.nombre FROM empleado e INNER JOIN puesto p ON p.id_puesto=e.id_puesto WHERE e.id_empleado = ?');
    $empleado = (int) ($_SESSION['empleado_id'] ?? 0);
    $consulta->bind_param('i', $empleado);
    $consulta->execute();
    $puesto = $consulta->get_result()->fetch_row();
    $configuracion = admin_leer_permisos();
    $paneles = $puesto ? ($configuracion[(string)$puesto[0]] ?? ($puesto[1] === 'Administrador' ? array_keys(admin_secciones_permisos()) : [])) : [];
    return in_array($pagina, $paneles, true);
}

function admin_es_rrhh(mysqli $conexion): bool
{
    $consulta = $conexion->prepare("SELECT 1 FROM empleado e INNER JOIN puesto p ON p.id_puesto=e.id_puesto WHERE e.id_empleado=? AND LOWER(TRIM(p.nombre))='rrhh'");
    $empleado = (int) ($_SESSION['empleado_id'] ?? 0);
    $consulta->bind_param('i', $empleado);
    $consulta->execute();
    $resultado = (bool) $consulta->get_result()->fetch_row();
    $consulta->close();
    return $resultado;
}

function admin_puede_eliminar(mysqli $conexion): bool
{
    if (admin_es_cuenta_principal($conexion)) return true;
    $consulta = $conexion->prepare('SELECT p.nombre FROM empleado e INNER JOIN puesto p ON p.id_puesto=e.id_puesto WHERE e.id_empleado=?');
    $empleado = (int) ($_SESSION['empleado_id'] ?? 0);
    $consulta->bind_param('i', $empleado);
    $consulta->execute();
    $puesto = $consulta->get_result()->fetch_row();
    $consulta->close();
    $nombre = mb_strtolower(trim($puesto[0] ?? ''), 'UTF-8');
    return in_array($nombre, ['administrador', 'dirección general'], true)
        || preg_match('/^jefe\s+/u', $nombre) === 1;
}

function admin_guardar_paneles_puesto(int $puestoId, array $seleccion): void
{
    admin_guardar_permisos_puestos([$puestoId => $seleccion]);
}

function admin_guardar_permisos_puestos(array $cambios): void
{
    foreach ($cambios as $puestoId => $seleccion) {
        if ((int) $puestoId < 1 || !is_array($seleccion)) throw new RuntimeException('Rol no válido.');
        foreach ($seleccion as $panel) {
            if (!is_string($panel) || !isset(admin_secciones_permisos()[$panel])) throw new RuntimeException('Panel no disponible.');
        }
    }
    $archivo = __DIR__ . '/permisos-puestos.php';
    $candado = fopen($archivo . '.lock', 'c');
    if (!$candado) throw new RuntimeException('No se pudo abrir la configuración de permisos.');
    try {
        if (!flock($candado, LOCK_EX)) throw new RuntimeException('No se pudo guardar la configuración de permisos.');
        $permisos = admin_leer_permisos();
        foreach ($cambios as $puestoId => $seleccion) {
            $permisos[(string) $puestoId] = array_values(array_unique($seleccion));
        }
        $contenido = "<?php exit; ?>\n" . json_encode($permisos, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $temporal = tempnam(__DIR__, '.permisos-');
        if (!$temporal) throw new RuntimeException('No se pudo preparar el archivo.');
        try {
            if (file_put_contents($temporal, $contenido) !== strlen($contenido) || !rename($temporal, $archivo)) throw new RuntimeException('No se pudieron guardar los paneles del rol.');
        } finally { if (is_file($temporal)) unlink($temporal); }
    } finally { flock($candado, LOCK_UN); fclose($candado); }
}

function admin_puede_tabla(mysqli $conexion, string $tabla): bool
{
    return admin_es_cuenta_principal($conexion);
}


function admin_menu_permitido(mysqli $conexion): string
{
    if (admin_es_cuenta_principal($conexion)) {
        $enlaces = '<a class="nav-link" href="admin-tablas.php">Tablas SQL</a>';
        foreach (admin_secciones_permisos() as $pagina => $nombre) {
            if (admin_puede_ver($conexion, $pagina)) $enlaces .= '<a class="nav-link" href="' . admin_e($pagina) . '">' . admin_e($nombre) . '</a>';
        }
        return $enlaces;
    }
    $enlaces = '';
    foreach (admin_secciones_permisos() as $pagina => $nombre) {
        if (admin_puede_ver($conexion, $pagina)) $enlaces .= '<a class="nav-link" href="' . admin_e($pagina) . '">' . admin_e($nombre) . '</a>';
    }
    return $enlaces;
}
