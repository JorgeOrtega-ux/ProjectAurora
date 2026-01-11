<?php
// public/auth-action.php
require_once __DIR__ . '/../includes/core/Auth.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$basePath = '/ProjectAurora/'; // Ajustar si es necesario
$redirectUrl = 'http://192.168.1.158/ProjectAurora/';

$auth = new Auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if ($action === 'register') {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $result = $auth->register($username, $email, $password);

        if ($result['status']) {
            header("Location: " . $redirectUrl);
            exit;
        } else {
            // Regresar con error (podrías mejorar esto pasando parámetros en la URL)
            header("Location: " . $basePath . "register?error=" . urlencode($result['message']));
            exit;
        }
    }

    if ($action === 'login') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $result = $auth->login($email, $password);

        if ($result['status']) {
            header("Location: " . $redirectUrl);
            exit;
        } else {
            header("Location: " . $basePath . "login?error=" . urlencode($result['message']));
            exit;
        }
    }
}

// Logout vía GET
if ($action === 'logout') {
    $auth->logout();
    header("Location: " . $basePath . "login");
    exit;
}

// Si no hay acción, volver al inicio
header("Location: " . $basePath);
?>