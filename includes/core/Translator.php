<?php
// includes/core/Translator.php

class Translator {
    private static $instance = null;
    private $translations = [];
    private $currentLang = 'es-latam'; // Fallback por defecto

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Carga el archivo de idioma
     */
    public function load($langCode) {
        $this->currentLang = $langCode;
        
        // Ruta al archivo JSON
        $filePath = __DIR__ . '/../lang/' . $langCode . '.json';
        
        if (file_exists($filePath)) {
            $json = file_get_contents($filePath);
            $this->translations = json_decode($json, true) ?? [];
        } else {
            // Si no existe, intentar cargar el fallback (es-latam)
            $fallbackPath = __DIR__ . '/../lang/es-latam.json';
            if (file_exists($fallbackPath)) {
                $json = file_get_contents($fallbackPath);
                $this->translations = json_decode($json, true) ?? [];
            } else {
                $this->translations = [];
            }
        }
    }

    /**
     * Obtiene una traducción por su clave
     */
    public function get($key) {
        return $this->translations[$key] ?? $key;
    }

    public function getCurrentLang() {
        return $this->currentLang;
    }
}

/**
 * Función helper global para usar en las vistas: <?php echo __('menu.home'); ?>
 */
if (!function_exists('__')) {
    function __($key) {
        return Translator::getInstance()->get($key);
    }
}
?>