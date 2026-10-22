<?php
session_start();

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $parametrosCookie = session_get_cookie_params();
    setcookie(
        session_name(),
        "",
        time() - 42000,
        $parametrosCookie["path"],
        $parametrosCookie["domain"],
        $parametrosCookie["secure"],
        $parametrosCookie["httponly"]
    );
}

session_destroy();

header("Location: login.php");
exit;
