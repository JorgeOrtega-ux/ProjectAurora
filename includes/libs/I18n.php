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
            
            // Si el JSON es válido, lo cargamos. Si no, queda vacío.
            if (is_array($decoded)) {
                $this->translations = $decoded;
            }
        }
        // NOTA: Si el archivo no existe (ej. es-mx.json), $this->translations se queda vacío.
    }

    /**
     * Obtiene una traducción.
     * Si no existe la clave o el archivo de idioma, devuelve la CLAVE.
     */
    public function trans($key) {
        return $this->translations[$key] ?? $key;
    }

    /**
     * Devuelve todo el array para inyectarlo en JS
     */
    public function getAll() {
        return $this->translations;
    }
}
?>