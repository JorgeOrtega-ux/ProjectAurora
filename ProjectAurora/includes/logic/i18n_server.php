<?php
// includes/logic/i18n_server.php

class I18n {
    private static $translations = [];
    private static $currentLang = 'es-latam';
    private static $loaded = false;

    public static function load($lang) {
        // Evitar recarga si ya está cargado el mismo idioma
        if (self::$loaded && self::$currentLang === $lang) return;

        self::$currentLang = $lang;
        
        // Ruta relativa desde includes/logic/ hacia public/assets/translations/
        $filePath = __DIR__ . '/../../public/assets/translations/' . $lang . '.json';
        
        // Fallback a es-latam si el archivo no existe
        if (!file_exists($filePath)) {
            $filePath = __DIR__ . '/../../public/assets/translations/es-latam.json';
        }

        if (file_exists($filePath)) {
            $json = file_get_contents($filePath);
            self::$translations = json_decode($json, true) ?? [];
        }
        
        self::$loaded = true;
    }

    public static function get($key, $vars = []) {
        $keys = explode('.', $key);
        $current = self::$translations;

        foreach ($keys as $k) {
            if (isset($current[$k])) {
                $current = $current[$k];
            } else {
                return $key; // Devolver la clave si no se encuentra
            }
        }

        // Si es un string, reemplazamos variables
        if (is_string($current)) {
            foreach ($vars as $variable => $value) {
                $current = str_replace('{' . $variable . '}', $value, $current);
            }
            return $current;
        }

        return $key;
    }
}

// Helper function global para usar en las vistas más fácilmente
if (!function_exists('trans')) {
    function trans($key, $vars = []) {
        return I18n::get($key, $vars);
    }
}
?>