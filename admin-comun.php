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
    $enlaceCuentas = '<a class="nav-link" href="admin-cuentas.php">Cuentas</a>';

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
        .presupuesto-admin > summary {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            list-style: none;
            cursor: pointer;
        }
        .presupuesto-admin > summary::-webkit-details-marker { display: none; }
        .presupuesto-admin > summary::after {
            content: "";
            flex-shrink: 0;
            width: 9px;
            height: 9px;
            border-right: 2px solid #253b80;
            border-bottom: 2px solid #253b80;
            transform: rotate(45deg);
        }
        .presupuesto-admin[open] > summary::after { transform: rotate(225deg); }
        .presupuesto-admin > summary:focus-visible { outline: 2px solid #253b80; outline-offset: 3px; }
        .presupuesto-admin-resumen { flex: 1; min-width: 0; overflow-wrap: anywhere; }
        .presupuesto-admin > summary > .badge { max-width: 12rem; white-space: normal; }
        .presupuesto-admin-contenido { padding: 18px; border-top: 1px solid #dee2e6; }
        .panel, .row > * { min-width: 0; }
        .panel { overflow-wrap: anywhere; }
        .table-responsive { max-width: 100%; }
        @media (max-width: 1199px) {
            .navbar > .container { flex-wrap: wrap; }
            .navbar-collapse .navbar-nav { flex-wrap: wrap; }
        }
        @media (max-width: 575px) {
            .navbar-brand { font-size: 1rem; white-space: normal; max-width: calc(100% - 64px); }
            .panel { padding: 16px 12px; margin: 14px 0; }
            .panel h1 { font-size: 1.5rem; }
            .panel h2 { font-size: 1.25rem; }
            .panel .d-flex { flex-wrap: wrap; }
            .panel input, .panel select, .panel textarea { font-size: 16px; }
            .presupuesto-admin > summary { padding: 14px 12px; gap: 10px; flex-wrap: wrap; }
            .presupuesto-admin-resumen { flex-basis: calc(100% - 24px); }
            .presupuesto-admin > summary::after { margin-left: auto; }
            .presupuesto-admin-contenido { padding: 14px 12px; }
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
