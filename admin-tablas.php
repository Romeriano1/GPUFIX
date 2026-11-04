<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
require_once __DIR__ . '/admin-edicion-datos.php';
$tablas = array_column($conexion->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all(MYSQLI_NUM), 0);
sort($tablas);
if (!admin_es_cuenta_principal($conexion)) {
    http_response_code(403);
    admin_layout_inicio('Tablas SQL');
    echo '<p>Esta sección está reservada a la cuenta principal de GPUFIX.</p>';
    admin_layout_fin();
    exit;
}
function admin_menu_tablas(string $actual = ''): void {
    global $tablas;
    echo '<link rel="stylesheet" href="admin-tablas.css?v=20261104-botones"><div class="tablas-estructura"><aside class="tablas-menu"><h2 class="h5">Tablas</h2><label class="form-label" for="filtrarTablas">Buscar tabla</label><input class="form-control mb-3" id="filtrarTablas" type="search" autocomplete="off"><nav aria-label="Tablas de la base de datos" id="listaTablas">';
    foreach ($tablas as $tabla) {
        $activa = $tabla === $actual;
        echo '<a class="tablas-enlace' . ($activa ? ' activa' : '') . '" ' . ($activa ? 'aria-current="page"' : '') . ' href="admin-tablas.php?tabla=' . rawurlencode($tabla) . '"><strong>' . admin_e(admin_etiqueta_dato($tabla)) . '</strong><small>' . admin_e($tabla) . '</small></a>';
    }
    echo '</nav><p id="tablasSinResultados" hidden>No hay tablas que coincidan.</p></aside><div class="tablas-contenido">';
    echo '<script>document.getElementById("filtrarTablas").addEventListener("input",function(){const normalizar=t=>t.normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLowerCase();const filtro=normalizar(this.value);let visibles=0;document.querySelectorAll("#listaTablas a").forEach(a=>{a.hidden=!normalizar(a.textContent).includes(filtro);if(!a.hidden)visibles++;});document.getElementById("tablasSinResultados").hidden=visibles>0;});</script>';
}
if (!isset($_GET['tabla'])) {
    admin_layout_inicio('Tablas SQL');
    admin_menu_tablas();
    echo '<section class="panel mt-0"><h1 class="h3">Administrar tablas</h1><p>Elegí una tabla en el menú para abrir sus registros.</p><p class="mb-0">Dentro de cada tabla tenés las opciones <strong>Agregar</strong>, <strong>Editar</strong> y <strong>Eliminar</strong>.</p></section></div></div>';
    admin_layout_fin();
    exit;
}
$_GET['modo'] = 'datos';
$seleccion = is_string($_GET['tabla']) && in_array($_GET['tabla'], $tablas, true) ? $_GET['tabla'] : '';
if ($seleccion === '') { http_response_code(404); exit('Tabla no disponible.'); }
admin_edicion_seccion(admin_etiqueta_dato($seleccion), [$seleccion], 'admin-tablas.php');
