<?php
// includes/core/Translator.php

class Translator {
    private static $instance = null;
    private $translations = [];
    private $currentLang = 'es-latam'; 

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Carga el archivo de idioma.
     * Si el archivo no existe, NO carga ningún fallback.
     */
    public function load($langCode) {
        $this->currentLang = $langCode;
        
        // Ruta al archivo JSON
        $filePath = __DIR__ . '/../lang/' . $langCode . '.json';
        
        if (file_exists($filePath)) {
            $json = file_get_contents($filePath);
            $this->translations = json_decode($json, true) ?? [];
        } else {
            // Si no existe el archivo, dejamos las traducciones vacías.
            // Esto hará que el sistema muestre las claves (keys) directamente.
            $this->translations = [];
        }
    }

    /**
     * Obtiene una traducción por su clave.
     * Si no existe la traducción, devuelve la clave misma.
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