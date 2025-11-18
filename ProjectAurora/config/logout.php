<?php
// logout.php
session_start();

// 1. Vaciar el array de sesión
$_SESSION = [];

// 2. Borrar la cookie de sesión del navegador (para limpieza profunda)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destruir la sesión en el servidor
session_destroy();

// 4. Redirigir al login
// Asumimos que 'login' es manejado por tu router
header("Location: ../login");
exit;
?>