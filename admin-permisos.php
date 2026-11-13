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

function admin_roles_organigrama(): array
{
    return [
        'Dirección' => [
            'Dirección General' => array_keys(admin_secciones_permisos()),
        ],
        'Comercial' => [
            'Jefe Comercial' => ['admin.php', 'admin-cuentas.php', 'admin-productos.php', 'admin-presupuestos.php', 'admin-facturacion.php', 'admin-devoluciones.php', 'admin-comprobantes.php'],
            'Vendedores' => ['admin-cuentas.php', 'admin-productos.php', 'admin-presupuestos.php', 'admin-facturacion.php'],
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

function admin_cargar_roles_organigrama(mysqli $conexion): int
{
    $conexion->begin_transaction();
    $transaccionActiva = true;
    try {
        $puestos = $conexion->query('SELECT id_puesto,nombre FROM puesto ORDER BY id_puesto FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
        $existentes = [];
        foreach ($puestos as $puesto) {
            $nombre = mb_strtolower(trim($puesto['nombre']), 'UTF-8');
            $existentes[$nombre] = (int) $puesto['id_puesto'];
        }
        $permisos = admin_leer_permisos();
        $nuevosPermisos = [];
        $creados = 0;
        $insertar = $conexion->prepare('INSERT INTO puesto(nombre) VALUES(?)');
        foreach (admin_roles_organigrama() as $roles) {
            foreach ($roles as $nombre => $paneles) {
                $clave = mb_strtolower($nombre, 'UTF-8');
                $id = $existentes[$clave] ?? null;
                if ($id === null) {
                    $insertar->bind_param('s', $nombre);
                    $insertar->execute();
                    $id = (int) $conexion->insert_id;
                    $existentes[$clave] = $id;
                    $creados++;
                }
                if (!array_key_exists((string) $id, $permisos)) $nuevosPermisos[$id] = $paneles;
            }
        }
        $conexion->commit();
        $transaccionActiva = false;
        // Los roles sin permisos guardados quedan sin acceso y se completan al reintentar.
        if ($nuevosPermisos) admin_guardar_permisos_puestos($nuevosPermisos);
        return $creados;
    } catch (Throwable $error) {
        if ($transaccionActiva) $conexion->rollback();
        throw $error;
    }
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
        return '<a class="nav-link" href="admin.php">Estadísticas</a>'
            . '<a class="nav-link" href="admin-tablas.php">Tablas SQL</a>'
            . '<a class="nav-link" href="admin-comprobantes.php">Comprobantes</a>'
            . '<a class="nav-link" href="admin-empleos.php">Implementación de Empleos</a>';
    }
    $enlaces = '';
    foreach (admin_secciones_permisos() as $pagina => $nombre) {
        if (admin_puede_ver($conexion, $pagina)) $enlaces .= '<a class="nav-link" href="' . admin_e($pagina) . '">' . admin_e($nombre) . '</a>';
    }
    return $enlaces;
}
