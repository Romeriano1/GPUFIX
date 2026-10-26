<?php
session_start();
if (empty($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}
$idVenta = filter_input(INPUT_GET, "compra", FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) ?: 0;
try {
    require_once __DIR__ . "/conexion.php";
    $usuarioId = (int) $_SESSION["usuario_id"];
    $consulta = $conexion->prepare("SELECT v.id_gpu_ventas FROM gpu_ventas v INNER JOIN cliente c ON c.id_cliente = v.id_cliente WHERE v.id_gpu_ventas = ? AND c.id_correo = ? AND v.metodo_pago = 'Transferencia (simulada)' LIMIT 1");
    $consulta->bind_param("ii", $idVenta, $usuarioId);
    $consulta->execute();
    $existe = $consulta->get_result()->fetch_assoc();
    $consulta->close();
    $conexion->close();
    if ($existe) {
        foreach (["pdf" => "application/pdf", "jpg" => "image/jpeg", "png" => "image/png"] as $extension => $tipo) {
            $ruta = __DIR__ . "/comprobantes/" . $idVenta . "." . $extension;
            if (is_file($ruta) && is_readable($ruta)) {
                header("Content-Type: " . $tipo);
                header('Content-Disposition: attachment; filename="comprobante-compra-' . $idVenta . '.' . $extension . '"');
                header("Content-Length: " . filesize($ruta));
                header("X-Content-Type-Options: nosniff");
                header("Cache-Control: private, no-store");
                session_write_close();
                readfile($ruta);
                exit;
            }
        }
    }
    http_response_code(404);
    echo "Comprobante no encontrado.";
} catch (Throwable $error) {
    error_log("Error al descargar comprobante: " . $error->getMessage());
    http_response_code(503);
    echo "No pudimos cargar el comprobante. Intentá nuevamente.";
}
