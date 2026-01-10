<?php
// includes/core/Boot.php

require_once __DIR__ . '/Translator.php';

// 1. Obtener preferencias de Cookie
// Decodificamos la cookie de preferencias si existe
$cookies = json_decode($_COOKIE['project_aurora_prefs'] ?? '{}', true);

// Obtenemos el idioma y tema, con valores por defecto iniciales
$prefLang = $cookies['language'] ?? 'auto';
$prefTheme = $cookies['theme'] ?? 'sync';

// 2. Lógica de Detección Automática
// Si el usuario no ha elegido idioma ('auto'), intentamos adivinar por el navegador
if ($prefLang === 'auto' || empty($prefLang)) {
    $browserLangs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    
    // Tomamos los primeros 2 caracteres (ej. 'en' de 'en-US') o 5 si prefieres
    $primaryLang = strtolower(substr($browserLangs[0], 0, 2)); 
    
    // Lógica simple de fallback inicial SOLO para usuarios nuevos en modo auto
    if ($primaryLang === 'en') {
        $prefLang = 'en-us';
    } else {
        // Por defecto asumimos español si no es inglés
        $prefLang = 'es-latam';
    }
}

// 3. Inicializar el Traductor
// Pasamos el idioma resultante directamente.
// Si $prefLang es un idioma que no tienes (ej. 'fr-fr'), 
// el Translator cargará un array vacío y mostrará las claves.
Translator::getInstance()->load($prefLang);

// 4. Variables globales para las vistas
global $currentTheme, $currentLang;
$currentTheme = $prefTheme;
$currentLang = $prefLang;
?>