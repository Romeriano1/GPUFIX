<?php
$paginaNavbar = basename($_SERVER['SCRIPT_NAME'] ?? '');
$busquedaNavbar = is_string($_GET['buscar'] ?? null) ? $_GET['buscar'] : '';
?>
<nav class="navbar tienda-navbar" aria-label="Navegación principal">
    <a class="navbar-brand" href="index.php">GPU FIX <span>&amp; STORE</span></a>
    <button class="tienda-menu-toggle" id="tiendaMenuButton" type="button" aria-controls="tiendaMenu" aria-expanded="false" aria-label="Abrir menú">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        <span>Menú</span>
    </button>
    <div class="tienda-menu" id="tiendaMenu">
        <ul class="navbar-nav tienda-enlaces">
            <?php foreach (['index.php'=>'Inicio', 'productos.php'=>'Productos', 'reparar.php'=>'Reparar GPU'] as $rutaNavbar=>$textoNavbar): ?>
                <li class="nav-item"><a class="nav-link<?= $paginaNavbar === $rutaNavbar ? ' active' : '' ?>" href="<?= $rutaNavbar ?>" <?= $paginaNavbar === $rutaNavbar ? 'aria-current="page"' : '' ?>><?= $textoNavbar ?></a></li>
            <?php endforeach; ?>
        </ul>
        <form class="search-form tienda-busqueda" action="busqueda.php" method="get" role="search">
            <input class="form-control" type="search" name="buscar" placeholder="Buscar GPU" aria-label="Buscar GPU" value="<?= htmlspecialchars($busquedaNavbar, ENT_QUOTES, 'UTF-8') ?>">
            <button class="btn" type="submit">Buscar</button>
        </form>
        <div class="navbar-derecha tienda-cuenta">
            <button id="themeButton" class="theme-button" type="button" aria-label="Cambiar tema">☀️</button>
            <?php include __DIR__ . '/menu-perfil.php'; ?>
        </div>
    </div>
</nav>
