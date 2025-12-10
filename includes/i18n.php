<?php
// includes/i18n.php

// Variable global para almacenar las traducciones cargadas
$GLOBALS['AURORA_TRANSLATIONS'] = [];

/**
 * Carga el archivo de traducción basado en el código de idioma.
 * Si el archivo no existe, NO carga nada (se mostrarán las claves).
 */
function load_translations($langCode) {
    // Sanitización básica del código para evitar Directory Traversal
    $langCode = basename($langCode);
    
    $path = __DIR__ . '/translations/' . $langCode . '.json';
    
    // CORRECCIÓN: Eliminamos el bloque "if (!file_exists) { fallback... }"
    // Ahora, si el archivo no existe, simplemente no entra al if de carga.
    
    if (file_exists($path)) {
        $json = file_get_contents($path);
        $data = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $GLOBALS['AURORA_TRANSLATIONS'] = $data;
            return;
        }
    }
    
    // Si no existe el archivo o el JSON está mal, array vacío.
    // Esto hará que las funciones __() y window.t() devuelvan la clave.
    $GLOBALS['AURORA_TRANSLATIONS'] = [];
}

/**
 * Función helper global para traducir (PHP Side)
 */
function __($key) {
    if (isset($GLOBALS['AURORA_TRANSLATIONS'][$key])) {
        return $GLOBALS['AURORA_TRANSLATIONS'][$key];
    }
    // Retorna la clave tal cual si no hay traducción
    return $key;
}

/**
 * Detecta el idioma del navegador.
 */
function detect_browser_language() {
    // Lista de idiomas que "teóricamente" soporta tu lógica, 
    // aunque no tengas los JSON creados aún.
    $supported = [
        'es-419' => ['es', 'es-mx', 'es-ar', 'es-co', 'es-cl', 'es-pe', 'es-ve'],
        'en-US'  => ['en', 'en-us', 'en-ca', 'en-gb'],
        'fr-FR'  => ['fr', 'fr-fr', 'fr-ca'],
        'pt-BR'  => ['pt', 'pt-br', 'pt-pt']
    ];

    $fallback = 'es-419'; 
    
    if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        return $fallback;
    }

    $userLangs = explode(',', strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']));
    
    foreach ($userLangs as $langStr) {
        $langCode = trim(explode(';', $langStr)[0]);
        foreach ($supported as $key => $variants) {
            if ($key === $langCode || in_array($langCode, $variants)) {
                return $key;
            }
        }
    }
    
    return $fallback;
}
?>