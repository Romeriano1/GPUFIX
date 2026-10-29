<?php
$estaLogueado = !empty($_SESSION["usuario_id"]);
$cantidadCarritoNavbar = 0;
if ($estaLogueado) {
    // La consulta usa su propia conexión: algunas páginas ya cerraron la suya.
    $cantidadCarritoNavbar = (static function (int $usuarioCarrito): int {
        $conexion = null;
        try {
            require __DIR__ . '/conexion.php';
            $consultaContador = $conexion->prepare(
                'SELECT COALESCE(SUM(d.cantidad), 0) FROM detalle_carrito d '
                . 'INNER JOIN carrito ca ON ca.id_carrito = d.id_carrito '
                . 'INNER JOIN cliente c ON c.id_cliente = ca.id_cliente '
                . 'WHERE c.id_correo = ?'
            );
            $consultaContador->bind_param('i', $usuarioCarrito);
            $consultaContador->execute();
            $totalCarrito = (int) $consultaContador->get_result()->fetch_row()[0];
            $consultaContador->close();
            return max(0, $totalCarrito);
        } catch (Throwable $errorContador) {
            error_log('Error al consultar el contador del carrito: ' . $errorContador->getMessage());
            return 0;
        } finally {
            if ($conexion instanceof mysqli) {
                $conexion->close();
            }
        }
    })((int) $_SESSION['usuario_id']);
}
$etiquetaCarritoNavbar = $cantidadCarritoNavbar > 0
    ? 'Ver carrito, ' . $cantidadCarritoNavbar . ($cantidadCarritoNavbar === 1 ? ' unidad' : ' unidades')
    : 'Ver carrito';
?>
<a class="carrito-navbar position-relative" href="carrito.php" aria-label="<?= htmlspecialchars($etiquetaCarritoNavbar, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($etiquetaCarritoNavbar, ENT_QUOTES, 'UTF-8') ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="M3 3h2l3 13h11l2-9H6" />
        <circle cx="9" cy="20" r="1" />
        <circle cx="18" cy="20" r="1" />
    </svg>
    <?php if ($cantidadCarritoNavbar > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" aria-hidden="true"><?= $cantidadCarritoNavbar ?></span>
    <?php endif; ?>
</a>
<div class="cuenta-contenedor">
    <button id="cuentaButton" class="cuenta-button position-relative" type="button" aria-label="<?= $estaLogueado ? 'Abrir menú de perfil, sesión iniciada' : 'Abrir menú de perfil' ?>" aria-expanded="false" aria-controls="cuentaMenu">
        👤
        <?php if ($estaLogueado): ?>
            <span class="position-absolute top-0 start-100 translate-middle p-1 bg-success border border-light rounded-circle" aria-hidden="true"></span>
        <?php endif; ?>
    </button>
    <div id="cuentaMenu" class="cuenta-menu">
        <?php if ($estaLogueado): ?>
            <a href="mi-cuenta.php">Mi cuenta</a>
            <a href="cerrar-sesion.php">Cerrar sesión</a>
        <?php else: ?>
            <a href="login.php">Iniciar sesión</a>
        <?php endif; ?>
    </div>
</div>
