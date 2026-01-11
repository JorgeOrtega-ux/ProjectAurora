<?php
// includes/core/Boot.php

// 0. Iniciar Sesión PHP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/Translator.php';
// Cargamos AuthServices globalmente
require_once __DIR__ . '/../../api/services/AuthServices.php';

// --- NUEVO: Sincronización de datos ---
// Si hay un usuario, refrescamos sus datos (rol, foto, estado) desde la BD
if (isset($_SESSION['user_id'])) {
    $auth = new AuthServices();
    $auth->refreshSessionData();
}
// --------------------------------------

// 1. Obtener preferencias de Cookie
$cookies = json_decode($_COOKIE['project_aurora_prefs'] ?? '{}', true);

$prefLang = $cookies['language'] ?? 'auto';
$prefTheme = $cookies['theme'] ?? 'sync';

// 2. Lógica de Detección Automática
if ($prefLang === 'auto' || empty($prefLang)) {
    $browserLangs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    $primaryLang = strtolower(substr($browserLangs[0], 0, 2)); 
    
    if ($primaryLang === 'en') {
        $prefLang = 'en-us';
    } else {
        $prefLang = 'es-latam';
    }
}

// 3. Inicializar el Traductor
Translator::getInstance()->load($prefLang);

// 4. Variables globales
global $currentTheme, $currentLang;
$currentTheme = $prefTheme;
$currentLang = $prefLang;
?>