<?php
// includes/libs/I18n.php

class I18n {
    private $translations = [];
    private $languageCode;

    public function __construct($languageCode) {
        $this->languageCode = $languageCode;
        $this->loadTranslations();
    }

    private function loadTranslations() {
        // Ruta al archivo JSON basada en el código de idioma (ej: es-latam.json)
        $filePath = __DIR__ . '/../../translations/' . $this->languageCode . '.json';

        if (file_exists($filePath)) {
            $jsonContent = file_get_contents($filePath);
            $decoded = json_decode($jsonContent, true);
            
            // Si el JSON es válido, lo cargamos.
            if (is_array($decoded)) {
                $this->translations = $decoded;
            }
        }
    }

    /**
     * Obtiene una traducción usando notación de puntos (ej: 'auth.login.title').
     * Soporta JSON anidado.
     * Si no encuentra la clave, devuelve la clave misma.
     */
    public function t($key) {
        $keys = explode('.', $key);
        $value = $this->translations;

        foreach ($keys as $nestedKey) {
            if (isset($value[$nestedKey])) {
                $value = $value[$nestedKey];
            } else {
                return $key; // No encontrado, devolver clave original
            }
        }

        // Asegurarse de devolver un string (por si la clave apunta a un array intermedio)
        return is_string($value) ? $value : $key;
    }

    /**
     * Devuelve todo el array para inyectarlo en JS
     */
    public function getAll() {
        return $this->translations;
    }
}
?>