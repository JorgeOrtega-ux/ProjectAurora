<?php
// config/helpers/i18n.php

$GLOBALS['AURORA_TRANSLATIONS'] = [];

function load_translations($langCode) {
    $langCode = basename($langCode);
    $path = __DIR__ . '/../../includes/translations/' . $langCode . '.json';
    
    if (file_exists($path)) {
        $json = file_get_contents($path);
        $data = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $GLOBALS['AURORA_TRANSLATIONS'] = $data;
            return;
        }
    }
    
    $GLOBALS['AURORA_TRANSLATIONS'] = [];
}

/**
 * Función de traducción mejorada.
 * Soporta reemplazo de variables tipo sprintf.
 * Ejemplo: __('error.min_length', 8) reemplaza %s por 8 en el string.
 */
function __($key, ...$args) {
    $text = $key;
    if (isset($GLOBALS['AURORA_TRANSLATIONS'][$key])) {
        $text = $GLOBALS['AURORA_TRANSLATIONS'][$key];
    }
    
    if (!empty($args)) {
        return vsprintf($text, $args);
    }
    
    return $text;
}

function detect_browser_language() {
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