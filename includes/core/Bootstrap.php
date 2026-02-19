<?php
// includes/core/Bootstrap.php

// 1. CARGAR AUTOLOADER DE COMPOSER
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\Utils;
use App\Config\Database;

// 2. CARGA DE UTILIDADES Y ENTORNO
// Movemos esto al principio para poder leer las variables de entorno para la configuración de seguridad
try {
    Utils::loadEnv(__DIR__ . '/../../.env');
} catch (Exception $e) {
    die("Error crítico del sistema: No se pudo cargar el archivo de configuración de entorno.");
}

// Determinar el entorno (por defecto local si no está definido)
$appEnv = getenv('APP_ENV') ?: 'local';
// Detectar si la conexión actual es segura (HTTPS)
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

// 3. FORZAR HTTPS EN PRODUCCIÓN (A nivel de aplicación)
if ($appEnv === 'production' && !$isSecure) {
    $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirectUrl);
    exit;
}

// 4. CABECERAS DE SEGURIDAD HTTP (Security Headers)
// Evita que el navegador adivine el tipo MIME
header('X-Content-Type-Options: nosniff');
// Evita el Clickjacking (solo permite iframes del mismo dominio)
header('X-Frame-Options: SAMEORIGIN');
// Controla la información de referencia enviada a otros sitios
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy (CSP)
// Permite recursos locales, fuentes de Google, avatares de ui-avatars y estilos/scripts inline necesarios para el funcionamiento
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https://ui-avatars.com; connect-src 'self'; frame-ancestors 'self';");

// HSTS: Obliga a usar HTTPS por un año (solo se envía si la conexión ya es segura para evitar bloquear localhost)
if ($isSecure) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// 5. ZONA HORARIA
date_default_timezone_set('America/Mexico_City');

// 6. CONFIGURACIÓN DE SESIONES Y COOKIES SEGURAS
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);

// Habilitar cookie_secure dinámicamente si estamos en producción o si ya estamos en HTTPS
if ($appEnv === 'production' || $isSecure) {
    ini_set('session.cookie_secure', 1);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generar token CSRF globalmente si no existe en la sesión
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 7. CONEXIÓN A LA BASE DE DATOS Y MANEJO DE ERRORES GLOBALES
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