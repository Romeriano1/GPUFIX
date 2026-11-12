<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
require_once __DIR__ . '/admin-edicion-datos.php';
$tablas = array_column($conexion->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all(MYSQLI_NUM), 0);
sort($tablas);
function admin_menu_tablas(string $actual = ''): void {
    global $tablas, $conexion;
    echo '<link rel="stylesheet" href="admin-tablas.css?v=20261104-botones"><div class="tablas-estructura"><aside class="tablas-menu"><h2 class="h5">Tablas</h2><a class="btn btn-outline-primary mb-3" href="admin-tablas.php">Elegir tabla</a><label class="form-label" for="filtrarTablas">Buscar tabla</label><input class="form-control mb-3" id="filtrarTablas" type="search" autocomplete="off"><nav aria-label="Tablas de la base de datos" id="listaTablas">';
    foreach ($tablas as $tabla) {
        $activa = $tabla === $actual;
        echo '<a class="tablas-enlace' . ($activa ? ' activa' : '') . '" ' . ($activa ? 'aria-current="page"' : '') . ' href="admin-tablas.php?tabla=' . rawurlencode($tabla) . '"><strong>' . admin_e(admin_etiqueta_dato($tabla)) . '</strong><small>' . admin_e($tabla) . '</small></a>';
    }
    if (admin_puede_ver($conexion, 'admin-comprobantes.php')) echo '<a class="tablas-enlace" href="admin-comprobantes.php"><strong>Comprobantes</strong><small>Archivos vinculados a ventas</small></a>';
    echo '</nav><p id="tablasSinResultados" hidden>No hay tablas que coincidan.</p></aside><div class="tablas-contenido">';
    echo '<script>document.getElementById("filtrarTablas").addEventListener("input",function(){const normalizar=t=>t.normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLowerCase();const filtro=normalizar(this.value);let visibles=0;document.querySelectorAll("#listaTablas a").forEach(a=>{a.hidden=!normalizar(a.textContent).includes(filtro);if(!a.hidden)visibles++;});document.getElementById("tablasSinResultados").hidden=visibles>0;});</script>';
}
if (!isset($_GET['tabla'])) {
    admin_layout_inicio('Tablas SQL');
    admin_menu_tablas();
    echo '<section class="panel mt-0"><h1 class="h3">Tablas SQL</h1><p>Elegí una de las ' . count($tablas) . ' tablas del menú. Después seleccioná la columna cuyos valores querés consultar o editar.</p></section></div></div>';
    admin_layout_fin();
    exit;
}
$seleccion = is_string($_GET['tabla']) && in_array($_GET['tabla'], $tablas, true) ? $_GET['tabla'] : '';
if ($seleccion === '') { http_response_code(404); exit('Tabla no disponible.'); }
$funcion = admin_funciones_tablas()[$seleccion] ?? '';
$esPrincipal = admin_es_cuenta_principal($conexion);
if (($_GET['vista'] ?? '') === 'acciones') {
    if (!$esPrincipal && !admin_puede_ver($conexion, admin_permiso_tabla($seleccion))) { http_response_code(403); exit('Esta función necesita acceso a todas sus tablas relacionadas.'); }
    if (($_GET['accion_tabla'] ?? '') === 'devoluciones') {
        if ($seleccion !== 'gpu_ventas' || !admin_puede_ver($conexion, 'admin-devoluciones.php')) { http_response_code(403); exit('Acceso restringido.'); }
        $funcion = 'devoluciones';
    }
    if ($funcion === 'componentes') {
        admin_layout_inicio(admin_etiqueta_dato($seleccion));
        admin_menu_tablas($seleccion);
        echo '<h1 class="h3">' . admin_e(admin_etiqueta_dato($seleccion)) . '</h1>';
        $filas = $conexion->query('SELECT * FROM `' . $seleccion . '` LIMIT 100')->fetch_all(MYSQLI_ASSOC);
        echo '<div class="table-responsive"><table class="table"><tbody>';
        foreach ($filas as $fila) { echo '<tr>'; foreach ($fila as $campo => $valor) echo '<td><strong>' . admin_e($campo) . '</strong><br>' . admin_e($valor) . '</td>'; echo '</tr>'; }
        echo '</tbody></table></div></div></div>';
        admin_layout_fin();
        exit;
    }
    if (!in_array($funcion, array_values(admin_funciones_tablas()), true) && $funcion !== 'devoluciones') { http_response_code(404); exit('Funciones no disponibles.'); }
    define('GPUFIX_TABLA_ACCIONES', true);
    $GLOBALS['tabla_funciones_actual'] = $seleccion;
    $_GET['modo'] = 'acciones';
    require __DIR__ . '/admin-' . $funcion . '.php';
    exit;
}
$_GET['modo'] = 'datos';
admin_edicion_seccion(admin_etiqueta_dato($seleccion), [$seleccion], 'admin-tablas.php');
