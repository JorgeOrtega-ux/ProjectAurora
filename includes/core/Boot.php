<?php
// includes/core/Boot.php

// 0. CARGAR VARIABLES DE ENTORNO (.ENV)
// Definimos la ruta al archivo .env (asumiendo que está 2 niveles arriba de este archivo)
$envPath = __DIR__ . '/../../.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Separar nombre y valor
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Eliminar comillas si existen
            $value = trim($value, '"\'');

            // Cargar en variables de entorno si no existen
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// 1. Iniciar Sesión PHP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Generación de Token CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// ---------------------------------------

require_once __DIR__ . '/Translator.php';
// Cargamos AuthServices globalmente
require_once __DIR__ . '/../../api/services/AuthServices.php';

// --- Sincronización de datos ---
// Si hay un usuario, refrescamos sus datos (rol, foto, estado) desde la BD
if (isset($_SESSION['user_id'])) {
    $auth = new AuthServices();
    $auth->refreshSessionData();
}
// --------------------------------------

// 2. Obtener preferencias de Cookie
$cookies = json_decode($_COOKIE['project_aurora_prefs'] ?? '{}', true);

$prefLang = $cookies['language'] ?? 'auto';
$prefTheme = $cookies['theme'] ?? 'sync';

// 3. Lógica de Detección Automática
if ($prefLang === 'auto' || empty($prefLang)) {
    $browserLangs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    $primaryLang = strtolower(substr($browserLangs[0], 0, 2)); 
    
    if ($primaryLang === 'en') {
        $prefLang = 'en-us';
    } else {
        $prefLang = 'es-latam';
    }
}

// 4. Inicializar el Traductor
Translator::getInstance()->load($prefLang);

// 5. Variables globales
global $currentTheme, $currentLang;
$currentTheme = $prefTheme;
$currentLang = $prefLang;
?>