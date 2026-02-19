<?php
// includes/core/router.php
namespace App\Core;

class Router {
    private $routes;
    private $basePath;

    public function __construct($routes) {
        $this->routes = $routes;
        // Ajusta esto si cambias la carpeta en el futuro
        $this->basePath = '/ProjectAurora'; 
    }

    public function resolve() {
        // 1. Obtener la URL solicitada
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // 2. Limpiar el 'base path' para obtener la ruta relativa pura
        // Si la URL es /ProjectAurora/explore, queremos solo /explore
        if (strpos($requestUri, $this->basePath) === 0) {
            $relativePath = substr($requestUri, strlen($this->basePath));
        } else {
            $relativePath = $requestUri;
        }

        // --- SOLUCIÓN: Limpiar el slash (/) al final de la ruta ---
        // Si la ruta tiene más de 1 caracter (no es simplemente '/') y termina en '/', lo removemos.
        if (strlen($relativePath) > 1 && substr($relativePath, -1) === '/') {
            $relativePath = rtrim($relativePath, '/');
        }

        // Normalizar raíz vacía a '/'
        if ($relativePath === '' || $relativePath === false) {
            $relativePath = '/';
        }

        // 3. Buscar en la lista de rutas
        if (array_key_exists($relativePath, $this->routes)) {
            return $this->routes[$relativePath];
        }

        // 4. SI NO EXISTE -> Retornar la vista 404
        return '404.php';
    }
}
?>