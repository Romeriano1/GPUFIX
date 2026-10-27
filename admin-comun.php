<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
function admin_requerir_acceso(): void {
    if (empty($_SESSION['empleado_id'])) {
        header('Location: login-empleado.php');
        exit;
    }
    global $conexion;
    require_once __DIR__ . '/conexion.php';
    $validar = $conexion->prepare('SELECT e.id_empleado FROM empleado e INNER JOIN usuarios u ON u.id_correo = e.id_correo WHERE e.id_empleado = ? LIMIT 1');
    $idEmpleado = (int) $_SESSION['empleado_id'];
    $validar->bind_param('i', $idEmpleado);
    $validar->execute();
    $existe = $validar->get_result()->fetch_assoc();
    $validar->close();
    if (!$existe) {
        unset($_SESSION['empleado_id'], $_SESSION['empleado_nombre'], $_SESSION['empleado_apellido'], $_SESSION['gpufix_admin'], $_SESSION['admin_correo'], $_SESSION['admin_csrf']);
        header('Location: login-empleado.php');
        exit;
    }
}
function admin_es_cuenta_principal(mysqli $conexion): bool {
    $consulta = $conexion->prepare("SELECT e.id_empleado FROM empleado e INNER JOIN usuarios u ON u.id_correo = e.id_correo INNER JOIN puesto p ON p.id_puesto = e.id_puesto WHERE e.id_empleado = ? AND u.correo = 'gpufixstore@gmail.com' AND p.nombre = 'Administrador' LIMIT 1");
    $idEmpleado = (int) ($_SESSION['empleado_id'] ?? 0);
    $consulta->bind_param('i', $idEmpleado);
    $consulta->execute();
    $principal = (bool) $consulta->get_result()->fetch_assoc();
    $consulta->close();
    return $principal;
}
function admin_csrf(): string {
    if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['admin_csrf'];
}
function admin_validar_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = (string)($_POST['admin_csrf'] ?? '');
        if (empty($_SESSION['admin_csrf']) || !hash_equals($_SESSION['admin_csrf'], $token)) {
            http_response_code(403);
            exit('La sesión del formulario venció. Volvé atrás y recargá la página.');
        }
    }
}
function admin_e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function admin_layout_inicio(string $titulo): void {
    $csrf = admin_csrf();
    $enlaceCuentas = isset($GLOBALS['conexion']) && admin_es_cuenta_principal($GLOBALS['conexion'])
        ? '<a class="nav-link" href="admin-cuentas.php">Cuentas</a>' : '';

    echo '<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>'.admin_e($titulo).' - GPUFIX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f3f5f9;
            color: #202938;
        }
        .navbar {
            background: #111827;
        }
        .navbar-brand, .nav-link {
            color: #fff !important;
        }
        .container {
            max-width: 1200px;
        }
        .panel {
            background: white;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 3px 15px #10182812;
            margin: 22px 0;
        }
        .table {
            vertical-align: middle;
        }
        .btn-primary {
            background: #253b80;
            border-color: #253b80;
        }
        .small-muted {
            color: #667085;
            font-size: .9rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand fw-bold" href="admin.php">
                GPUFIX | Administración
            </a>

            <button class="navbar-toggler bg-light"
                    data-bs-toggle="collapse"
                    data-bs-target="#adminNav"
                    aria-controls="adminNav"
                    aria-expanded="false"
                    aria-label="Mostrar menú">
                ☰
            </button>

            <div class="collapse navbar-collapse" id="adminNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="admin.php">Inicio</a>
                    <a class="nav-link" href="admin-productos.php">Productos y stock</a>
                    <a class="nav-link" href="admin-proveedores.php">Proveedores</a>
                    '.$enlaceCuentas.'
                    <a class="nav-link" href="admin-reparaciones.php">Reparaciones</a>
                    <a class="nav-link" href="admin-presupuestos.php">Presupuestos</a>
                    <a class="nav-link" href="admin-facturacion.php">Facturación</a>
                    <a class="nav-link" href="admin-devoluciones.php">Devoluciones</a>
                    <a class="nav-link" href="cerrar-sesion-admin.php">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-3">';
}

function admin_layout_fin(): void {
    echo '</main><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>';
}
