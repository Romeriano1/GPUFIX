<?php
$estaLogueado = !empty($_SESSION["usuario_id"]);
?>
<div class="cuenta-contenedor">
    <button id="cuentaButton" class="cuenta-button" type="button" aria-label="Abrir menú de perfil" aria-expanded="false" aria-controls="cuentaMenu">
        👤
    </button>
    <div id="cuentaMenu" class="cuenta-menu">
        <?php if ($estaLogueado): ?>
            <a href="cerrar-sesion.php">Cerrar sesión</a>
        <?php else: ?>
            <a href="login.php">Iniciar sesión</a>
        <?php endif; ?>
        <a href="carrito.php">Ver carrito</a>
    </div>
</div>
