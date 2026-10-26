
<?php
session_start();

unset(
    $_SESSION['empleado_id'],
    $_SESSION['empleado_nombre'],
    $_SESSION['empleado_apellido'],
    $_SESSION['admin_csrf'],
    $_SESSION['gpufix_admin'],
    $_SESSION['admin_correo']
);

session_regenerate_id(true);

header('Location: login-empleado.php');
exit;
