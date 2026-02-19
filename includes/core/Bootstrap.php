<?php
// includes/core/bootstrap.php

// 1. ZONA HORARIA
date_default_timezone_set('America/Mexico_City');

// 2. CONFIGURACIÓN DE SESIONES Y COOKIES SEGURAS
// Evitar acceso a cookies desde JavaScript (Previene ataques XSS)
ini_set('session.cookie_httponly', 1);
// Prevenir envío de cookies en peticiones cruzadas (Previene ataques CSRF en conjunto con tu token)
ini_set('session.cookie_samesite', 'Strict');
// Obligar el uso de cookies para sesiones (Previene fijación de sesión por URL)
ini_set('session.use_only_cookies', 1);

// Si en el futuro usas HTTPS, descomenta esta línea:
// ini_set('session.cookie_secure', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generar token CSRF globalmente si no existe en la sesión
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. CARGA DE UTILIDADES Y ENTORNO
require_once __DIR__ . '/utils.php';

try {
    Utils::loadEnv(__DIR__ . '/../../.env');
} catch (Exception $e) {
    die("Error crítico del sistema: No se pudo cargar el archivo de configuración de entorno.");
}

// 4. CONEXIÓN A LA BASE DE DATOS Y MANEJO DE ERRORES GLOBALES
require_once __DIR__ . '/../../config/database.php';

// Hacemos la conexión global para que los handlers la puedan reutilizar sin instanciar de nuevo
global $dbConnection;

try {
    $database = new Database();
    $dbConnection = $database->getConnection();
} catch (PDOException $e) {
    // Detectar si la petición viene de la API, de la SPA (por cabecera) o si espera JSON
    $isApiRequest = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) ||
                    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                    (isset($_SERVER['HTTP_X_SPA_REQUEST']));

    if ($isApiRequest) {
        // Respuesta limpia en JSON para evitar romper el frontend
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => 'Error crítico: No se pudo establecer conexión con la base de datos.'
        ]);
        exit;
    } else {
        // Respuesta HTML elegante para navegación directa
        http_response_code(500);
        die("
            <div style='font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f5f5fa; margin: 0;'>
                <div style='text-align: center; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 400px;'>
                    <h1 style='color: #dc2626; font-size: 24px; margin-bottom: 12px;'>Error 500</h1>
                    <p style='color: #666; font-size: 15px; line-height: 1.5;'>Servicio no disponible temporalmente debido a un problema de conexión con la base de datos. Por favor, intenta de nuevo más tarde.</p>
                </div>
            </div>
        ");
    }
}
?>