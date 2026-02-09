<?php
// includes/libs/Utils.php

namespace Aurora\Libs;

use Aurora\Libs\I18n;
use Aurora\Libs\Logger;
use Predis\Client;
use Predis\Session\Handler;
use Exception;
use PDO;
use PDOException;
use DateTime;

class Utils {

    private static $redisInstance = null;

    public static function setRedis($redis) {
        self::$redisInstance = $redis;
    }

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

        // [MODIFICADO] Leemos la IP del archivo .env
        // Si no está definida en .env, usamos 127.0.0.1 como fallback seguro
        $wsIp = $_ENV['APP_HOST_IP'] ?? '127.0.0.1';

        // Construimos las fuentes permitidas para WebSocket
        // Permitimos localhost por defecto y añadimos la IP configurada
        $wsSources = "ws://localhost:8765 ws://{$wsIp}:8765";

        header("Content-Security-Policy: " .
            "default-src 'self'; " .
            "script-src 'self' https://challenges.cloudflare.com https://unpkg.com https://cdnjs.cloudflare.com 'nonce-$cspNonce'; " .
            "style-src 'self' https://fonts.googleapis.com https://cdnjs.cloudflare.com 'unsafe-inline'; " .
            "img-src 'self' data: https://ui-avatars.com; " .
            "font-src 'self' https://fonts.gstatic.com; " .
            "frame-src https://challenges.cloudflare.com; " .
            // [MODIFICADO] Inyectamos $wsSources aquí
            "connect-src 'self' https://challenges.cloudflare.com https://unpkg.com {$wsSources}; " . 
            "object-src 'none'; " .
            "base-uri 'self';"
        );

        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: strict-origin-when-cross-origin");

        return $cspNonce;
    }

    /**
     * Muestra la página de error genérica (500).
     * Intenta reutilizar el diseño de status-screen.php si es posible.
     */
    public static function showGenericErrorPage() {
        if (!headers_sent()) {
            http_response_code(500);
        }

        // 1. Detección de API/AJAX
        $isApi = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) || 
                 (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
        
        if ($isApi) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => 'Error interno del sistema. El incidente ha sido registrado.',
                'error_code' => 'CRITICAL_SYSTEM_ERROR'
            ]);
            exit;
        }

        // 2. Intento de Carga Centralizada con status-screen.php
        $statusFile = __DIR__ . '/../sections/system/status-screen.php';
        
        if (file_exists($statusFile)) {
            // Definimos banderas para controlar status-screen
            $isSystemError = true;
            $showInterface = false; 
            
            // Inyectamos CSS Crítico Inline para asegurar que se vea bien sin styles.css
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error del Sistema</title>';
            echo '<style>
                :root { --text-primary: #111827; --text-secondary: #6b7280; --bg-hover-light: #f3f4f6; --border-light: #e5e7eb; --action-primary: #000; }
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; background: #f9fafb; min-height: 100vh; display: flex; flex-direction: column; }
                .component-layout-centered { flex: 1; display: flex; align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
                .component-card { background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 32px; max-width: 400px; width: 100%; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
                .component-page-title { margin: 0 0 10px; font-size: 24px; font-weight: 700; font-family: inherit; }
                .component-page-description { color: var(--text-secondary); margin-bottom: 24px; line-height: 1.5; font-size: 15px; }
                .component-button { display: inline-flex; justify-content: center; align-items: center; width: 100%; padding: 12px; background: #2563EB; color: white; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; box-sizing: border-box; text-decoration: none; font-size: 14px; transition: background 0.2s; }
                .component-button:hover { background: #1d4ed8; }
                .material-symbols-rounded { font-family: "Material Symbols Rounded", sans-serif; display: inline-block; }
            </style>';
            echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />';
            echo '</head><body>';

            try {
                include $statusFile;
                echo '</body></html>';
                exit; 
            } catch (Exception $e) {
                // Si falla el include, el script sigue al fallback
            }
        }

        // 3. Fallback de Emergencia (Si status-screen.php no existe)
        echo "<div style='font-family:sans-serif; text-align:center; padding:50px;'>";
        echo "<h1 style='color:#333;'>Error Crítico del Sistema</h1>";
        echo "<p style='color:#666;'>Ha ocurrido un error irrecuperable. Contacte al administrador.</p>";
        echo "<a href='/ProjectAurora/' style='color:#007bff;'>Volver al inicio</a>";
        echo "</div>";
        exit;
    }

    public static function getServerConfig($pdo, $key, $default = '0') {
        $redisKey = 'server:config:all';

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

        try {
            $stmt = $pdo->prepare("SELECT config_key, config_value FROM server_config");
            $stmt->execute();
            $allConfig = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            if ($allConfig && self::$redisInstance) {
                try {
                    self::$redisInstance->hmset($redisKey, $allConfig);
                    self::$redisInstance->expire($redisKey, 86400); 
                } catch (Exception $e) {}
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

    public static function generateDefaultProfilePicture($name, $outputPath) {
        $colors = ['2563EB', '16A34A', '7C3AED', 'DC2626', 'EA580C', '374151'];
        $selectedColor = $colors[array_rand($colors)];
        $encodedName = urlencode($name);
        $url = "https://ui-avatars.com/api/?name={$encodedName}&background={$selectedColor}&color=fff&size=512&font-size=0.5&bold=true&length=1";

        try {
            $imageData = file_get_contents($url);
            if ($imageData !== false) {
                $dir = dirname($outputPath);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                if (file_put_contents($outputPath, $imageData) !== false) return true;
            }
        } catch (Exception $e) {
            Logger::log('app', 'Error generando avatar default: ' . $e->getMessage(), ['path' => $outputPath], 'ERROR');
        }
        return false;
    }

    // =========================================================
    // NUEVAS FUNCIONES CENTRALIZADAS
    // =========================================================

    public static function checkSecurityLimit($pdo, $actionType, $limit, $minutes, $identifier = '') {
        $ip = self::getClientIp();

        if (self::$redisInstance) {
            try {
                $ipKey = "rate_limit:{$actionType}:ip:{$ip}";
                $userKey = $identifier ? "rate_limit:{$actionType}:user:{$identifier}" : null;

                $ipCount = self::$redisInstance->get($ipKey);
                if ($ipCount && (int)$ipCount >= $limit) return true;

                if ($userKey) {
                    $userCount = self::$redisInstance->get($userKey);
                    if ($userCount && (int)$userCount >= $limit) return true;
                }
                return false; 
            } catch (Exception $e) {
                error_log("Redis checkSecurityLimit Error: " . $e->getMessage());
            }
        }

        $sql = "SELECT COUNT(*) as failures FROM security_logs WHERE (ip_address = ? OR user_identifier = ?) AND action_type = ? AND created_at > (NOW() - INTERVAL $minutes MINUTE)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ip, $identifier, $actionType]);
        $result = $stmt->fetch();
        return ($result && $result['failures'] >= $limit);
    }

    public static function logSecurityAction($pdo, $actionType, $minutes = 15, $identifier = null) {
        $ip = self::getClientIp();
        
        try {
            $sql = "INSERT INTO security_logs (user_identifier, action_type, ip_address) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $logId = $identifier ?? 'Anonymous';
            $stmt->execute([$logId, $actionType, $ip]);
        } catch (Exception $e) { 
            error_log("DB logSecurityAction Error: " . $e->getMessage());
        }

        if (self::$redisInstance) {
            $ipKey = "rate_limit:{$actionType}:ip:{$ip}";
            $userKey = $identifier ? "rate_limit:{$actionType}:user:{$identifier}" : null;

            self::incrementRedisCounter($ipKey, $minutes);
            if ($userKey) {
                self::incrementRedisCounter($userKey, $minutes);
            }
        }
    }

    public static function checkFirewallFlood($redis, $limit = 60, $seconds = 60) {
        if (!$redis) return false;

        try {
            $ip = self::getClientIp();
            $key = "firewall:flood:" . $ip;
            
            $requests = $redis->incr($key);
            
            if ($requests === 1) {
                $redis->expire($key, $seconds);
            }
            
            return $requests > $limit;
        } catch (Exception $e) {
            return false;
        }
    }

    private static function incrementRedisCounter($key, $minutes) {
        if (!self::$redisInstance) return;
        try {
            $current = self::$redisInstance->incr($key);
            if ($current === 1) {
                self::$redisInstance->expire($key, $minutes * 60);
            }
        } catch (Exception $e) {
            error_log("Redis RateLimit Error (INCR): " . $e->getMessage());
        }
    }

    public static function parseUserAgent($ua) {
        $platform = 'Desconocido'; 
        $browser = 'Desconocido';
        
        if (preg_match('/windows|win32/i', $ua)) $platform = 'Windows';
        elseif (preg_match('/macintosh|mac os x/i', $ua)) $platform = 'Mac OS';
        elseif (preg_match('/linux/i', $ua)) $platform = 'Linux';
        elseif (preg_match('/android/i', $ua)) $platform = 'Android';
        elseif (preg_match('/iphone|ipad|ipod/i', $ua)) $platform = 'iOS';
        
        if (preg_match('/MSIE|Trident/i', $ua)) $browser = 'Internet Explorer';
        elseif (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
        elseif (preg_match('/Chrome/i', $ua)) $browser = 'Chrome';
        elseif (preg_match('/Safari/i', $ua)) $browser = 'Safari';
        elseif (preg_match('/Opera|OPR/i', $ua)) $browser = 'Opera';
        elseif (preg_match('/Edge/i', $ua)) $browser = 'Edge';
        
        return ['platform' => $platform, 'browser' => $browser];
    }

    public static function notifyWebSocket($type, $payload = []) {
        if (!self::$redisInstance) return false;
        
        try {
            $msg = array_merge(['cmd' => $type], $payload);
            self::$redisInstance->publish('aurora_ws_control', json_encode($msg));
            return true;
        } catch (Exception $e) {
            error_log("Error publicando en Redis (WS Notify): " . $e->getMessage());
            return false;
        }
    }

    public static function formatSize($bytes) {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' bytes';
    }

    // ===============================================================================
    // [NUEVO] SISTEMA DE PRIVILEGIOS CENTRALIZADO
    // ===============================================================================

    /**
     * Retorna el nivel numérico de un rol para comparaciones jerárquicas.
     * @param string $role
     * @return int
     */
    public static function getRoleLevel($role) {
        $hierarchy = [
            'founder'       => 3,
            'administrator' => 2,
            'moderator'     => 1,
            'user'          => 0
        ];
        return $hierarchy[$role] ?? 0;
    }

    /**
     * Verifica si un usuario cumple con requisitos de rol y seguridad 2FA.
     * @param PDO $pdo Conexión BD
     * @param int $userId ID del usuario
     * @param array $allowedRoles Roles permitidos (array vacío = cualquiera)
     * @param bool $require2fa Si es true, exige que el usuario tenga 2FA activado Y verificado en sesión
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
   public static function checkUserPrivileges($pdo, $userId, $allowedRoles = [], $require2fa = false) {
        if (!$userId) return ['allowed' => false, 'reason' => 'no_session'];

        $stmt = $pdo->prepare("SELECT role, two_factor_enabled FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return ['allowed' => false, 'reason' => 'user_not_found'];

        // 1. Verificación de Rol
        if (!empty($allowedRoles)) {
            if (!in_array($user['role'], $allowedRoles)) {
                return ['allowed' => false, 'reason' => 'role_mismatch'];
            }
        }

        // 2. Verificación de 2FA (Condicional Global)
        if ($require2fa) {
            // Leemos la configuración global (Default '1' = Activado)
            $isGlobal2faActive = self::getServerConfig($pdo, 'security_admin_require_2fa', '1') === '1';

            // Solo verificamos si el sistema lo exige globalmente
            if ($isGlobal2faActive) {
                
                // A. ¿Tiene 2FA activado en su cuenta?
                $has2faEnabled = (int)$user['two_factor_enabled'] === 1;
                if (!$has2faEnabled) {
                    return ['allowed' => false, 'reason' => '2fa_not_enabled'];
                }

                // B. ¿Ya validó el código en esta sesión?
                if (empty($_SESSION['is_2fa_verified'])) {
                    return ['allowed' => false, 'reason' => '2fa_not_verified'];
                }
            }
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Verifica si el usuario 'requester' tiene mayor o igual nivel jerárquico que 'target'.
     * @param PDO $pdo
     * @param int $requesterId
     * @param int $targetId
     * @return array
     */
    public static function checkHierarchicalAccess($pdo, $requesterId, $targetId) {
        // Obtener rol del solicitante
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$requesterId]);
        $reqRole = $stmt->fetchColumn();
        $reqLevel = self::getRoleLevel($reqRole);

        // Obtener rol del objetivo
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $targetRole = $stmt->fetchColumn();
        $targetLevel = self::getRoleLevel($targetRole);

        // Auto-modificación permitida (acceso base)
        if ($requesterId == $targetId) {
            return ['allowed' => true, 'role' => $targetRole];
        }

        // Regla: Solicitante > Objetivo
        if ($reqLevel > $targetLevel) {
            return ['allowed' => true, 'role' => $targetRole];
        }

        return ['allowed' => false, 'message' => 'Jerarquía insuficiente.'];
    }
}
?>