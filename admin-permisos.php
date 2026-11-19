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
        'admin-facturacion.php' => 'Facturación',
        'admin-compras.php' => 'Compras y Stock',
        'admin-componentes.php' => 'Componentes y repuestos',
        'admin-devoluciones.php' => 'Devoluciones',
        'admin-comprobantes.php' => 'Comprobantes',
        'admin-empleos.php' => 'Empleados y permisos',
        'admin-personal.php' => 'Empleados'
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
            'Jefe Administrativo' => ['admin.php', 'admin-cuentas.php', 'admin-productos.php', 'admin-proveedores.php', 'admin-facturacion.php', 'admin-compras.php', 'admin-componentes.php', 'admin-devoluciones.php', 'admin-comprobantes.php'],
            'Contabilidad' => ['admin.php', 'admin-facturacion.php', 'admin-compras.php', 'admin-comprobantes.php'],
            'Facturación' => ['admin-facturacion.php', 'admin-devoluciones.php', 'admin-comprobantes.php'],
            'Compras y Stock' => ['admin-productos.php', 'admin-proveedores.php', 'admin-compras.php', 'admin-componentes.php'],
            'Logística' => [],
            'RRHH' => ['admin-cuentas.php', 'admin-personal.php'],
        ],
    ];
}

function admin_puede_ver(mysqli $conexion, string $pagina): bool
{
    $esPrincipal = admin_es_cuenta_principal($conexion);
    if ($esPrincipal && $pagina === 'admin-empleos.php') return true;
    if ($esPrincipal && isset(admin_secciones_permisos()[$pagina])) {
        $consulta = $conexion->prepare('SELECT id_puesto FROM empleado WHERE id_empleado=?');
        $empleado = (int) ($_SESSION['empleado_id'] ?? 0);
        $consulta->bind_param('i', $empleado);
        $consulta->execute();
        $puesto = $consulta->get_result()->fetch_row();
        $consulta->close();
        $configuracion = admin_leer_permisos();
        return $puesto && in_array($pagina, $configuracion[(string)$puesto[0]] ?? array_keys(admin_secciones_permisos()), true);
    }
    if ($pagina === 'admin-personal.php') return admin_es_rrhh($conexion);
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
    $consulta = $conexion->prepare('SELECT p.nombre,e.id_sucursal FROM empleado e INNER JOIN puesto p ON p.id_puesto=e.id_puesto WHERE e.id_empleado=?');
    $empleado = (int) ($_SESSION['empleado_id'] ?? 0);
    $consulta->bind_param('i', $empleado);
    $consulta->execute();
    $resultado = $consulta->get_result()->fetch_row();
    $consulta->close();
    return $resultado && admin_puesto_es_rrhh($resultado[0]) && (int)$resultado[1] === admin_id_sucursal_central();
}

function admin_puesto_es_rrhh(string $nombre): bool
{
    $nombre = mb_strtolower(trim($nombre), 'UTF-8');
    return str_contains($nombre, 'rrhh') || str_contains($nombre, 'recursos humanos');
}

function admin_sede_fija_puesto(string $nombre): ?int
{
    if (admin_puesto_es_rrhh($nombre)) return admin_id_sucursal_central();
    return null;
}

function admin_sucursal_para_puesto(mysqli $conexion, int $idPuesto, ?int $sucursal): ?int
{
    $consulta = $conexion->prepare('SELECT nombre FROM puesto WHERE id_puesto=?');
    $consulta->bind_param('i', $idPuesto); $consulta->execute();
    $puesto = $consulta->get_result()->fetch_row(); $consulta->close();
    if (!$puesto) throw new RuntimeException('Seleccioná un puesto existente.');
    if (admin_puesto_es_rrhh($puesto[0])) {
        require_once __DIR__ . '/sucursales.php';
        sucursal_validar($conexion, (string)admin_id_sucursal_central());
        return admin_id_sucursal_central();
    }
    require_once __DIR__ . '/sucursales.php';
    if ($sucursal !== null) sucursal_validar($conexion, (string)$sucursal);
    return $sucursal;
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

function admin_puede_gestionar_componentes(mysqli $conexion, ?int $idEmpleado = null): bool
{
    $idEmpleado = $idEmpleado ?? (int) ($_SESSION['empleado_id'] ?? 0);
    if ($idEmpleado === admin_id_principal()) return true;
    $consulta = $conexion->prepare('SELECT p.nombre FROM empleado e INNER JOIN puesto p ON p.id_puesto=e.id_puesto WHERE e.id_empleado=?');
    $consulta->bind_param('i', $idEmpleado);
    $consulta->execute();
    $puesto = $consulta->get_result()->fetch_row();
    $consulta->close();
    $nombre = mb_strtolower(trim($puesto[0] ?? ''), 'UTF-8');
    return in_array($nombre, ['jefe técnico', 'administrador', 'dirección general'], true);
}

function admin_acceso_todas_sucursales(mysqli $conexion, ?int $idEmpleado = null): bool
{
    $idEmpleado = $idEmpleado ?? (int) ($_SESSION['empleado_id'] ?? 0);
    if ($idEmpleado === admin_id_principal()) return true;
    $consulta = $conexion->prepare('SELECT p.nombre FROM empleado e INNER JOIN puesto p ON p.id_puesto=e.id_puesto WHERE e.id_empleado=?');
    $consulta->bind_param('i', $idEmpleado); $consulta->execute();
    $puesto = $consulta->get_result()->fetch_row(); $consulta->close();
    return in_array(mb_strtolower(trim($puesto[0] ?? ''), 'UTF-8'), ['administrador', 'dirección general'], true);
}

function admin_sucursal_empleado(mysqli $conexion, ?int $idEmpleado = null): ?int
{
    $idEmpleado = $idEmpleado ?? (int) ($_SESSION['empleado_id'] ?? 0);
    $consulta = $conexion->prepare('SELECT id_sucursal FROM empleado WHERE id_empleado=?');
    $consulta->bind_param('i', $idEmpleado); $consulta->execute();
    $idSucursal = $consulta->get_result()->fetch_row()[0] ?? null; $consulta->close();
    return $idSucursal === null ? null : (int) $idSucursal;
}

function admin_sucursales_disponibles(mysqli $conexion): array
{
    require_once __DIR__ . '/sucursales.php';
    $sucursales = sucursales_listar($conexion);
    if (admin_acceso_todas_sucursales($conexion)) return $sucursales;
    $idSucursal = admin_sucursal_empleado($conexion);
    return array_values(array_filter($sucursales, static fn($local) => (int)$local['id_sucursal'] === $idSucursal));
}

function admin_validar_sucursal_empleado(mysqli $conexion, string $idSucursal, ?int $idEmpleado = null): void
{
    require_once __DIR__ . '/sucursales.php';
    sucursal_validar($conexion, $idSucursal);
    if (admin_acceso_todas_sucursales($conexion, $idEmpleado)) return;
    $asignada = admin_sucursal_empleado($conexion, $idEmpleado);
    if ($asignada === null) throw new RuntimeException('El empleado necesita una sucursal asignada. Solicitá que la configuren en Empleados.');
    if ((string)$asignada !== $idSucursal) throw new RuntimeException('Solo podés gestionar el stock de tu sucursal.');
}


function admin_datos_laborales(mysqli $conexion, ?int $empleado = null): array
{
    $empleado = $empleado ?? (int)($_SESSION['empleado_id'] ?? 0);
    $q = $conexion->prepare('SELECT p.nombre,e.id_sucursal FROM empleado e JOIN puesto p ON p.id_puesto=e.id_puesto WHERE e.id_empleado=?');
    $q->bind_param('i', $empleado); $q->execute(); $fila = $q->get_result()->fetch_assoc(); $q->close();
    return $fila ?: ['nombre'=>'', 'id_sucursal'=>null];
}

function admin_puede_recibir_proveedores(mysqli $conexion, ?int $empleado = null): bool
{
    require_once __DIR__ . '/sucursales.php';
    if (admin_acceso_todas_sucursales($conexion, $empleado)) return true;
    $datos = admin_datos_laborales($conexion, $empleado);
    return mb_strtolower(trim($datos['nombre']), 'UTF-8') === 'compras y stock' && $datos['id_sucursal'] !== null;
}

function admin_puede_ajustar_stock_placas(mysqli $conexion): bool
{
    return admin_acceso_todas_sucursales($conexion);
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
