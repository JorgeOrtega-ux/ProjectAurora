<?php
namespace Aurora\Libs;

class I18n {
    private $translations = [];
    private $languageCode;

    public function __construct($languageCode) {
        $this->languageCode = $languageCode;
        $this->loadTranslations();
    }

    private function loadTranslations() {
        $filePath = __DIR__ . '/../../translations/' . $this->languageCode . '.json';

        if (file_exists($filePath)) {
            $jsonContent = file_get_contents($filePath);
            $decoded = json_decode($jsonContent, true);
            
            if (is_array($decoded)) {
                $this->translations = $decoded;
            }
        }
    }

    public function t($key, $args = []) {
        $keys = explode('.', $key);
        $value = $this->translations;

        foreach ($keys as $nestedKey) {
            if (isset($value[$nestedKey])) {
                $value = $value[$nestedKey];
            } else {
                return $key;
            }
        }

        if (is_string($value)) {
            if (!empty($args)) {
                return vsprintf($value, $args);
            }
            return $value;
        }

        return is_string($value) ? $value : $key;
    }

    public function getAll() {
        return $this->translations;
    }
}
?>