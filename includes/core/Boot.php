<?php
// includes/core/boot.php

require_once __DIR__ . '/Translator.php';

// 1. Obtener preferencias de Cookie
$cookies = json_decode($_COOKIE['project_aurora_prefs'] ?? '{}', true);
$prefLang = $cookies['language'] ?? 'auto';
$prefTheme = $cookies['theme'] ?? 'sync';

// 2. Lógica de Detección Automática (Extraída de tu código original)
if ($prefLang === 'auto' || empty($prefLang)) {
    $browserLangs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    $primaryLang = strtolower(substr($browserLangs[0], 0, 5)); 
    
    // Mapeo simple para decidir el default si está en auto
    if (strpos($primaryLang, 'en') === 0) {
        $prefLang = 'en-us';
    } else {
        // Por defecto para todo lo demás (incluido español)
        $prefLang = 'es-latam';
    }
}

// 3. Inicializar el Traductor
Translator::getInstance()->load($prefLang);

// 4. Variables globales para las vistas
// (Esto evita tener que recalcularlas en cada archivo)
global $currentTheme, $currentLang;
$currentTheme = $prefTheme;
$currentLang = $prefLang;
?>