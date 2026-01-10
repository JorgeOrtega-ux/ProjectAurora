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
     * json_decode convierte automáticamente los objetos anidados en arrays multidimensionales.
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
            $this->translations = [];
        }
    }

    /**
     * Obtiene una traducción por su clave soportando "dot notation".
     * Ejemplo: 'menu.home' busca en $this->translations['menu']['home']
     * Si no existe la traducción o la ruta es inválida, devuelve la clave original.
     */
    public function get($key) {
        $keys = explode('.', $key);
        $value = $this->translations;

        foreach ($keys as $segment) {
            // Verificamos si el nivel actual es un array y si existe la clave
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $key; // Retorna la clave original si no encuentra la ruta
            }
        }

        // Seguridad: Si el resultado final sigue siendo un array (ej. pediste solo 'menu'),
        // devolvemos la clave original porque no podemos imprimir un array.
        if (is_array($value)) {
            return $key;
        }

        return $value;
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