<?php
// includes/core/Boot.php

// 0. Iniciar Sesión PHP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/Translator.php';
// Cargamos Auth para que esté disponible si se necesita verificar sesión globalmente
require_once __DIR__ . '/Auth.php';

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