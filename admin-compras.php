<?php
require_once __DIR__ . '/admin-comun.php';
admin_requerir_acceso();
if (admin_es_cuenta_principal($conexion)) {
    header('Location: ' . admin_url_panel('compras') . '&' . http_build_query(array_diff_key($_GET, ['tabla'=>1,'vista'=>1])), true, 303);
    exit;
}
define('GPUFIX_TABLA_ACCIONES', true);
require __DIR__ . '/admin-funciones/compras.php';
