<?php
// includes/core/utils.php
namespace App\Core;

use Exception;

class Utils {
    
    /**
     * Envía una respuesta JSON estandarizada y detiene la ejecución.
     */
    public static function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    /**
     * Obtiene y decodifica el cuerpo de la petición (JSON).
     */
    public static function getJsonInput() {
        return json_decode(file_get_contents("php://input"));
    }

    /**
     * Valida el token CSRF comparándolo con la sesión.
     */
    public static function validateCSRF($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Genera un identificador único (UUID).
     */
    public static function generateUUID() {
        return bin2hex(random_bytes(16));
    }

    /**
     * Genera un avatar a partir del nombre, lo descarga y lo guarda.
     * Devuelve la ruta relativa para ser guardada en la base de datos.
     */
    public static function generateAndSaveAvatar($name, $uuid, $absoluteStorageDir, $relativeWebDir) {
        if (!file_exists($absoluteStorageDir)) {
            mkdir($absoluteStorageDir, 0777, true);
        }

        $allowedColors = ['2563eb', '16a34a', '7c3aed', 'dc2626', 'ea580c', '374151'];
        $randomColor = $allowedColors[array_rand($allowedColors)];
        $nameEncoded = urlencode($name);
        
        $avatarUrl = "https://ui-avatars.com/api/?name={$nameEncoded}&background={$randomColor}&color=fff&size=512&length=1&format=png";
        
        // Suprimir warnings en caso de que falle la petición externa
        $avatarContent = @file_get_contents($avatarUrl);
        $avatarFilename = $uuid . '.png';
        
        if ($avatarContent) {
            file_put_contents($absoluteStorageDir . $avatarFilename, $avatarContent);
            return $relativeWebDir . $avatarFilename;
        }
        
        return ''; // Fallback en caso de no poder descargar
    }

    /**
     * Carga las variables de entorno desde el archivo .env
     */
    public static function loadEnv($path) {
        if (!file_exists($path)) {
            throw new Exception("El archivo .env no existe en la ruta especificada.");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Ignorar los comentarios
            if (strpos(trim($line), '#') === 0) continue;
            
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Remover comillas del valor si las tiene
                $value = trim($value, '"\'');

                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}
?>