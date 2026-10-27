<?php
$estaLogueado = !empty($_SESSION["usuario_id"]);
?>
<a class="carrito-navbar" href="carrito.php" aria-label="Ver carrito" title="Ver carrito">
    <svg xmlns="http://www.w3.org/2000/svg" width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="M3 3h2l3 13h11l2-9H6" />
        <circle cx="9" cy="20" r="1" />
        <circle cx="18" cy="20" r="1" />
    </svg>
</a>
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
        <?php endif; ?>
    </div>
</div>
