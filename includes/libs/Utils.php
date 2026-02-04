<?php
// includes/libs/Utils.php

require_once __DIR__ . '/I18n.php';
require_once __DIR__ . '/Logger.php';

class Utils {

    // Propiedad estática para almacenar la instancia de Redis globalmente
    private static $redisInstance = null;

    // Setter para inyectar Redis desde el bootstrap
    public static function setRedis($redis) {
        self::$redisInstance = $redis;
    }

    /**
     * Obtiene la dirección IP del cliente de manera segura y centralizada.
     * Utilizar esta función en lugar de acceder a $_SERVER['REMOTE_ADDR'] directamente.
     */
    public static function getClientIp() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function initErrorHandlers() {
        set_exception_handler(function ($e) {
            Logger::app('Uncaught Exception', [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            self::showGenericErrorPage();
        });

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if (!(error_reporting() & $errno)) {
                return false;
            }

            $level = 'ERROR';
            switch ($errno) {
                case E_WARNING: $level = 'WARNING'; break;
                case E_NOTICE:  $level = 'NOTICE'; break;
                default:        $level = 'CRITICAL'; break;
            }

            Logger::log('app', $errstr, [
                'file' => $errfile,
                'line' => $errline,
                'errno' => $errno
            ], $level);

            return true;
        });

        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                Logger::log('app', 'Fatal Error (Shutdown)', [
                    'message' => $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line']
                ], 'CRITICAL');
                self::showGenericErrorPage();
            }
        });
        
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
    }

   public static function applySecurityHeaders() {
        $cspNonce = base64_encode(random_bytes(16));

        header("Content-Security-Policy: " .
            "default-src 'self'; " .
            "script-src 'self' https://challenges.cloudflare.com https://unpkg.com https://cdnjs.cloudflare.com 'nonce-$cspNonce'; " .
            "style-src 'self' https://fonts.googleapis.com https://cdnjs.cloudflare.com 'unsafe-inline'; " .
            "img-src 'self' data: https://ui-avatars.com; " .
            "font-src 'self' https://fonts.gstatic.com; " .
            "frame-src https://challenges.cloudflare.com; " .
            "connect-src 'self' https://challenges.cloudflare.com https://unpkg.com ws://localhost:8765 ws://192.168.1.157:8765; " . 
            "object-src 'none'; " .
            "base-uri 'self';"
        );

        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: strict-origin-when-cross-origin");

        return $cspNonce;
    }

    public static function showGenericErrorPage() {
        if (!headers_sent()) {
            http_response_code(500);
        }
        $isApi = (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) || 
                 (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor (500). Contacte a soporte.']);
        } else {
            echo "<div style='font-family:sans-serif; text-align:center; padding:50px;'>";
            echo "<h1 style='color:#333;'>Error del Sistema</h1>";
            echo "<p style='color:#666;'>Ha ocurrido un error inesperado. El incidente ha sido registrado.</p>";
            echo "<a href='/ProjectAurora/' style='color:#007bff;'>Volver al inicio</a>";
            echo "</div>";
        }
        exit;
    }

    /**
     * Obtiene configuración del servidor usando Redis Cache-Aside.
     */
    public static function getServerConfig($pdo, $key, $default = '0') {
        $redisKey = 'server:config:all';

        // 1. Intentar leer de Redis
        if (self::$redisInstance) {
            try {
                $cachedValue = self::$redisInstance->hget($redisKey, $key);
                
                if ($cachedValue !== null) {
                    return $cachedValue;
                }

                if (self::$redisInstance->hlen($redisKey) > 0) {
                    return $default;
                }

            } catch (Exception $e) {
                error_log("Redis Error in getServerConfig: " . $e->getMessage());
            }
        }

        // 2. Fallback a MySQL
        try {
            $stmt = $pdo->prepare("SELECT config_key, config_value FROM server_config");
            $stmt->execute();
            $allConfig = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            if ($allConfig && self::$redisInstance) {
                try {
                    self::$redisInstance->hmset($redisKey, $allConfig);
                    self::$redisInstance->expire($redisKey, 86400); 
                } catch (Exception $e) {
                }
            }

            return isset($allConfig[$key]) ? $allConfig[$key] : $default;

        } catch (Exception $e) {
            return $default;
        }
    }

    public static function initI18n() {
        $userLang = $_SESSION['preferences']['language'] ?? $_COOKIE['guest_language'] ?? 'es-latam';
        $userLang = basename($userLang);
        return new I18n($userLang);
    }

    public static function jsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function validateCsrf($i18n) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                Logger::security('CSRF Validation Failed', [
                    'post_token' => $_POST['csrf_token'] ?? 'null',
                    'session_token' => $_SESSION['csrf_token'] ?? 'null'
                ]);
                self::jsonResponse(['success' => false, 'message' => $i18n->t('api.security_error')]);
            }
        }
    }

    public static function getGlobalAvatarSrc() {
        $src = '';
        if (isset($_SESSION['user_id'])) {
            if (!empty($_SESSION['avatar'])) {
                $avatarFile = __DIR__ . '/../../' . $_SESSION['avatar'];
                if (file_exists($avatarFile)) {
                    $mimeType = mime_content_type($avatarFile);
                    $data = file_get_contents($avatarFile);
                    $src = 'data:' . $mimeType . ';base64,' . base64_encode($data);
                }
            }
            if (empty($src)) {
                $name = $_SESSION['username'] ?? 'User';
                // Fallback visual con length=1
                $src = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=random&color=fff&length=1";
            }
        }
        return $src;
    }

    public static function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Genera y guarda una imagen de perfil por defecto usando una paleta de colores específica.
     */
    public static function generateDefaultProfilePicture($name, $outputPath) {
        // Lista estricta de colores permitidos
        $colors = [
            '2563EB', // Azul
            '16A34A', // Verde
            '7C3AED', // Morado
            'DC2626', // Rojo
            'EA580C', // Naranja
            '374151'  // Gris oscuro
        ];

        // Seleccionar color aleatorio
        $selectedColor = $colors[array_rand($colors)];

        // Preparar el nombre para la URL
        $encodedName = urlencode($name);

        // Construir la URL de ui-avatars con el color seleccionado
        $url = "https://ui-avatars.com/api/?name={$encodedName}&background={$selectedColor}&color=fff&size=512&font-size=0.5&bold=true&length=1";

        try {
            // Obtener el contenido de la imagen
            $imageData = file_get_contents($url);

            if ($imageData !== false) {
                // Asegurarse de que el directorio de destino exista
                $dir = dirname($outputPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                // Guardar la imagen en el disco
                if (file_put_contents($outputPath, $imageData) !== false) {
                    return true;
                }
            }
        } catch (Exception $e) {
            Logger::log('app', 'Error generando avatar default: ' . $e->getMessage(), ['path' => $outputPath], 'ERROR');
        }

        return false;
    }
}
?>