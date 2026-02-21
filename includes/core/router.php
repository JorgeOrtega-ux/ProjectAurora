<?php
// includes/core/router.php
namespace App\Core;

use App\Core\Logger;

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
        if (strpos($requestUri, $this->basePath) === 0) {
            $relativePath = substr($requestUri, strlen($this->basePath));
        } else {
            $relativePath = $requestUri;
        }

        // 3. Limpiar el slash (/) al final de la ruta
        if (strlen($relativePath) > 1 && substr($relativePath, -1) === '/') {
            $relativePath = rtrim($relativePath, '/');
        }

        // Normalizar raíz vacía a '/'
        if ($relativePath === '' || $relativePath === false) {
            $relativePath = '/';
        }

        // 4. Buscar la ruta en el mapa
        if (!array_key_exists($relativePath, $this->routes)) {
            Logger::system("Intento de acceso a ruta no encontrada (404): $relativePath", Logger::LEVEL_WARNING);
            return ['view' => '404.php', 'access' => 'public', 'layout' => 'main'];
        }

        $routeConfig = $this->routes[$relativePath];

        // Compatibilidad por si en un futuro declaras rutas sin array
        if (is_string($routeConfig)) {
            $routeConfig = ['view' => $routeConfig, 'access' => 'public', 'layout' => 'main'];
        }

        // --- SISTEMA DE PROTECCIÓN DE RUTAS ---
        $access = $routeConfig['access'] ?? 'public';
        $isLoggedIn = isset($_SESSION['user_id']);
        $isApiRequest = !empty($_SERVER['HTTP_X_SPA_REQUEST']);

        // A) Ruta protegida (solo logueados) pero el usuario NO está logueado
        if ($access === 'auth' && !$isLoggedIn) {
            Logger::system("Intento de acceso no autorizado a ruta protegida: $relativePath", Logger::LEVEL_INFO);
            if ($isApiRequest) {
                header('X-SPA-Redirect: ' . $this->basePath . '/login');
                exit;
            }
            header("Location: {$this->basePath}/login");
            exit;
        }

        // B) Ruta para invitados (ej. login) pero el usuario YA está logueado
        if ($access === 'guest' && $isLoggedIn) {
            if ($relativePath !== '/settings/guest') {
                Logger::system("Usuario logueado intentó acceder a ruta de invitado: $relativePath", Logger::LEVEL_INFO);
                if ($isApiRequest) {
                    header('X-SPA-Redirect: ' . $this->basePath . '/');
                    exit;
                }
                header("Location: {$this->basePath}/");
                exit;
            }
        }

        // Retornamos el array con la vista y el layout para que index.php decida qué pintar
        return $routeConfig;
    }
}
?>