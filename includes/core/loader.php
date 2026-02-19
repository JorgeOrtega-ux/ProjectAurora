<?php
// includes/core/loader.php
namespace App\Core;

class Loader {
    private $viewsPath;

    public function __construct() {
        // Define la ruta base donde están las vistas
        $this->viewsPath = __DIR__ . '/../views/';
    }

    public function load($viewName) {
        $file = $this->viewsPath . $viewName;
        
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<div class='error-404'>Vista no encontrada: " . htmlspecialchars($viewName) . "</div>";
        }
    }
}
?>