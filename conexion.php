<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$servidor = getenv("GPUFIX_DB_HOST") ?: "127.0.0.1";
$usuario = getenv("GPUFIX_DB_USER") ?: "root";
$contrasenaBaseDatos = getenv("GPUFIX_DB_PASSWORD");
$baseDatos = getenv("GPUFIX_DB_NAME") ?: "if0_43068954_gpufix";
$puerto = (int) (getenv("GPUFIX_DB_PORT") ?: 3306);

$conexion = new mysqli(
    $servidor,
    $usuario,
    $contrasenaBaseDatos === false ? "" : $contrasenaBaseDatos,
    $baseDatos,
    $puerto
);

$conexion->set_charset("utf8mb4");
