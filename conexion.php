<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$configuracionLocal = [];
$archivoConfiguracion = __DIR__ . "/conexion-config.local.php";
$hostSolicitud = strtolower((string) ($_SERVER["HTTP_HOST"] ?? "localhost"));
$hostSolicitud = preg_replace('/:\\d+$/', "", $hostSolicitud);
$esServidorLocal = in_array($hostSolicitud, ["localhost", "127.0.0.1"], true);

if (!$esServidorLocal && is_file($archivoConfiguracion)) {
    $cargada = require $archivoConfiguracion;
    if (is_array($cargada)) {
        $configuracionLocal = $cargada;
    }
}

$leerConfiguracion = static function ($variable, $clave, $predeterminado = "") use ($configuracionLocal) {
    $valorEntorno = getenv($variable);
    if ($valorEntorno !== false && $valorEntorno !== "") {
        return $valorEntorno;
    }
    return $configuracionLocal[$clave] ?? $predeterminado;
};

$servidor = $leerConfiguracion("GPUFIX_DB_HOST", "host", "127.0.0.1");
$usuario = $leerConfiguracion("GPUFIX_DB_USER", "user", "root");
$contrasenaBaseDatos = $leerConfiguracion("GPUFIX_DB_PASSWORD", "password", "");
$baseDatos = $leerConfiguracion("GPUFIX_DB_NAME", "database", "if0_43068954_gpufix");
$puerto = (int) $leerConfiguracion("GPUFIX_DB_PORT", "port", 3306);

$conexion = new mysqli(
    $servidor,
    $usuario,
    $contrasenaBaseDatos,
    $baseDatos,
    $puerto
);

$conexion->set_charset("utf8mb4");
