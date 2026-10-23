<?php
$estaLogueado = !empty($_SESSION["usuario_id"]);
$hostMenu = strtolower((string) ($_SERVER["HTTP_HOST"] ?? "localhost"));
$hostMenu = preg_replace('/:\\d+$/', "", $hostMenu);
$mostrarSesionProvisoria = !$estaLogueado && in_array($hostMenu, ["localhost", "127.0.0.1"], true);
if ($mostrarSesionProvisoria && empty($_SESSION["csrf_sesion_provisoria"])) {
    $_SESSION["csrf_sesion_provisoria"] = bin2hex(random_bytes(32));
}
?>
<div class="cuenta-contenedor">
    <button id="cuentaButton" class="cuenta-button" type="button" aria-label="Abrir menú de perfil" aria-expanded="false" aria-controls="cuentaMenu">
        👤
    </button>
    <div id="cuentaMenu" class="cuenta-menu">
        <?php if ($estaLogueado): ?>
            <a href="mi-cuenta.php">Mi cuenta</a>
            <a href="cerrar-sesion.php">Cerrar sesión</a>
        <?php else: ?>
            <a href="login.php">Iniciar sesión</a>
            <?php if ($mostrarSesionProvisoria): ?>
                <form action="sesion-provisoria.php" method="post" class="cuenta-menu-form">
                    <input type="hidden" name="csrf_sesion_provisoria" value="<?= htmlspecialchars($_SESSION["csrf_sesion_provisoria"], ENT_QUOTES, "UTF-8") ?>">
                    <button type="submit" class="cuenta-menu-accion">Sesión provisoria</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        <a href="carrito.php">Ver carrito</a>
    </div>
</div>
