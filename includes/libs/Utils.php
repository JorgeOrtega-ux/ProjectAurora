<?php
// includes/libs/Utils.php

require_once __DIR__ . '/I18n.php';
require_once __DIR__ . '/Logger.php';

class Utils {

    // [NUEVO] Propiedad estática para almacenar la instancia de Redis globalmente
    private static $redisInstance = null;

    // [NUEVO] Setter para inyectar Redis desde el bootstrap
    public static function setRedis($redis) {
        self::$redisInstance = $redis;
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
     * [OPTIMIZADO] Obtiene configuración del servidor usando Redis Cache-Aside.
     * Lee de 'server:config:all' (Hash). Si no existe, carga TODO de MySQL y lo guarda en Redis.
     */
    public static function getServerConfig($pdo, $key, $default = '0') {
        $redisKey = 'server:config:all';

        // 1. Intentar leer de Redis
        if (self::$redisInstance) {
            try {
                // HGET es muy rápido (O(1))
                $cachedValue = self::$redisInstance->hget($redisKey, $key);
                
                if ($cachedValue !== null) {
                    return $cachedValue;
                }

                // Si llegamos aquí, la clave específica no está, O el hash entero no existe.
                // Verificamos si el hash existe comprobando su longitud para evitar stampedes por claves inexistentes.
                if (self::$redisInstance->hlen($redisKey) > 0) {
                    // El hash existe pero la clave no -> La clave no existe en BD.
                    return $default;
                }

            } catch (Exception $e) {
                // Si falla Redis, silencio y fallback a DB
                error_log("Redis Error in getServerConfig: " . $e->getMessage());
            }
        }

        // 2. Fallback a MySQL (Cache Miss o Redis no disponible)
        try {
            // [OPTIMIZACIÓN] Cargamos TODA la configuración de una vez para "calentar" el caché
            $stmt = $pdo->prepare("SELECT config_key, config_value FROM server_config");
            $stmt->execute();
            $allConfig = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Retorna ['key' => 'val', ...]

            if ($allConfig && self::$redisInstance) {
                try {
                    // Guardamos todo en Redis de una sola vez
                    self::$redisInstance->hmset($redisKey, $allConfig);
                    // Expiración de seguridad (ej. 24 horas) para evitar datos zombies eternos
                    self::$redisInstance->expire($redisKey, 86400); 
                } catch (Exception $e) {
                    // Ignorar error de escritura en caché
                }
            }

            // Retornamos el valor solicitado o el default
            return isset($allConfig[$key]) ? $allConfig[$key] : $default;

        } catch (Exception $e) {
            return $default;
        }
    }

    public static function initI18n() {
        // [FIX] Prioridad: 1. Sesión (Usuario) 2. Cookie (Invitado) 3. Default
        $userLang = $_SESSION['preferences']['language'] ?? $_COOKIE['guest_language'] ?? 'es-latam';
        
        // Limpieza de seguridad básica para evitar Path Traversal
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
                $src = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=random&color=fff";
            }
        }
        return $src;
    }
}
?>