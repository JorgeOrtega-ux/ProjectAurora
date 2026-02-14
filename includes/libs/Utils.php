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

    // ===============================================================================================
    // CORE: MANEJO DE ERRORES Y SEGURIDAD (ORIGINAL)
    // ===============================================================================================

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

        $wsIp = $_ENV['APP_HOST_IP'] ?? '127.0.0.1';
        $wsSources = "ws://localhost:8765 ws://{$wsIp}:8765";

        // [MODIFICADO] Se agrega storage.googleapis.com a media-src para permitir el anuncio de prueba
        header("Content-Security-Policy: " .
            "default-src 'self'; " .
            "script-src 'self' https://challenges.cloudflare.com https://unpkg.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net 'nonce-$cspNonce'; " .
            "style-src 'self' https://fonts.googleapis.com https://cdnjs.cloudflare.com 'unsafe-inline'; " .
            "img-src 'self' data: https://ui-avatars.com; " .
            "font-src 'self' https://fonts.gstatic.com; " .
            "frame-src https://challenges.cloudflare.com; " .
            "connect-src 'self' https://challenges.cloudflare.com https://unpkg.com https://cdn.jsdelivr.net {$wsSources}; " . 
            "worker-src 'self' blob:; " .
            "media-src 'self' blob: https://storage.googleapis.com; " . // <--- AQUI ESTA EL CAMBIO IMPORTANTE
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

        $statusFile = __DIR__ . '/../sections/system/status-screen.php';
        
        if (file_exists($statusFile)) {
            $isSystemError = true;
            $showInterface = false; 
            
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
            } catch (Exception $e) { }
        }

        echo "<div style='font-family:sans-serif; text-align:center; padding:50px;'>";
        echo "<h1 style='color:#333;'>Error Crítico del Sistema</h1>";
        echo "<p style='color:#666;'>Ha ocurrido un error irrecuperable. Contacte al administrador.</p>";
        echo "<a href='/ProjectAurora/' style='color:#007bff;'>Volver al inicio</a>";
        echo "</div>";
        exit;
    }

    // ===============================================================================================
    // CONFIGURACIÓN E I18N
    // ===============================================================================================

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

    // ===============================================================================================
    // HELPERS WEB Y RESPUESTAS
    // ===============================================================================================

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

    // ===============================================================================================
    // VALIDACIÓN DE DATOS CENTRALIZADA
    // ===============================================================================================

    public static function validateUserValue($pdo, $field, $value, $excludeUserId = null) {
        $value = trim($value);

        if ($field === 'username') {
            $minLen = (int)self::getServerConfig($pdo, 'username_min_length', '4');
            $maxLen = (int)self::getServerConfig($pdo, 'username_max_length', '20');

            if (strlen($value) < $minLen || strlen($value) > $maxLen) {
                return ['success' => false, 'message' => "El nombre de usuario debe tener entre $minLen y $maxLen caracteres."];
            }

            if (!preg_match('/^[a-zA-Z0-9._-]+$/', $value)) {
                return ['success' => false, 'message' => 'El usuario solo puede contener letras, números, puntos, guiones y guiones bajos.'];
            }

            $sql = "SELECT COUNT(*) FROM users WHERE username = ?";
            $params = [$value];
            if ($excludeUserId) {
                $sql .= " AND id != ?";
                $params[] = $excludeUserId;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Este nombre de usuario ya está ocupado.'];
            }
        }

        if ($field === 'email') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Formato de correo electrónico inválido.'];
            }

            $allowedDomainsStr = self::getServerConfig($pdo, 'email_allowed_domains', '*');
            if ($allowedDomainsStr !== '*') {
                $allowedDomains = array_map('trim', explode(',', $allowedDomainsStr));
                $emailDomain = substr(strrchr($value, "@"), 1);
                
                if (!in_array($emailDomain, $allowedDomains)) {
                    return ['success' => false, 'message' => "El dominio @$emailDomain no está permitido en este servidor."];
                }
            }

            $prefix = explode('@', $value)[0];
            $minPrefixLen = (int)self::getServerConfig($pdo, 'email_min_prefix_length', '3');
            if (strlen($prefix) < $minPrefixLen) {
                return ['success' => false, 'message' => "La parte local del correo debe tener al menos $minPrefixLen caracteres."];
            }

            $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
            $params = [$value];
            if ($excludeUserId) {
                $sql .= " AND id != ?";
                $params[] = $excludeUserId;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Este correo electrónico ya está registrado.'];
            }
        }

        return ['success' => true];
    }

    public static function validatePassword($pdo, $password) {
        $minLen = (int)self::getServerConfig($pdo, 'password_min_length', '6');
        if (strlen($password) < $minLen) {
            return ['success' => false, 'message' => "La contraseña debe tener al menos $minLen caracteres."];
        }
        return ['success' => true];
    }

    public static function isValidDomain($domain) {
        return (bool)preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $domain);
    }

    // ===============================================================================================
    // PROCESAMIENTO DE IMÁGENES (Upload Centralizado) Y COLOR DOMINANTE
    // ===============================================================================================

    // [NUEVO] Método para obtener color dominante (Promedio simple redimensionando a 1px)
    public static function getDominantColor($imageResource) {
        $width = imagesx($imageResource);
        $height = imagesy($imageResource);
        
        // Creamos una imagen de 1x1
        $tmpImg = imagecreatetruecolor(1, 1);
        
        // Redimensionamos la imagen original a 1x1, lo que promedia todos los píxeles
        imagecopyresampled($tmpImg, $imageResource, 0, 0, 0, 0, 1, 1, $width, $height);
        
        // Obtenemos el color del único píxel
        $rgb = imagecolorat($tmpImg, 0, 0);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        
        imagedestroy($tmpImg);
        
        // Devolvemos en formato HEX
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }

    public static function processImageUpload($pdo, $file, $uuid, $type = 'custom') {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Error en la subida del archivo (Código: ' . $file['error'] . ').'];
        }

        $maxSizeMB = (int)self::getServerConfig($pdo, 'upload_avatar_max_size', '2');
        if ($file['size'] > $maxSizeMB * 1024 * 1024) {
            return ['success' => false, 'message' => "La imagen excede el tamaño máximo permitido de {$maxSizeMB}MB."];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        
        if (!in_array($mime, $allowedMimes)) {
            return ['success' => false, 'message' => 'Formato no soportado. Usa JPG, PNG o WEBP.'];
        }

        $maxDim = (int)self::getServerConfig($pdo, 'upload_avatar_max_dim', '2000');
        list($width, $height) = getimagesize($file['tmp_name']);
        if ($width > $maxDim || $height > $maxDim) {
            return ['success' => false, 'message' => "Las dimensiones máximas permitidas son {$maxDim}x{$maxDim}px."];
        }

        $srcImage = null;
        switch ($mime) {
            case 'image/jpeg': $srcImage = imagecreatefromjpeg($file['tmp_name']); break;
            case 'image/png':  $srcImage = imagecreatefrompng($file['tmp_name']); break;
            case 'image/webp': $srcImage = imagecreatefromwebp($file['tmp_name']); break;
        }

        if (!$srcImage) {
            return ['success' => false, 'message' => 'Error al procesar la imagen. Archivo corrupto.'];
        }

        // [NUEVO] Calcular color dominante ANTES de redimensionar o recortar (usamos la imagen completa cargada)
        $dominantColor = self::getDominantColor($srcImage);

        // Recorte cuadrado centrado
        $size = min($width, $height);
        $x = ($width - $size) / 2;
        $y = ($height - $size) / 2;

        $dstImage = imagecreatetruecolor(512, 512); 
        
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
        $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
        imagefilledrectangle($dstImage, 0, 0, 512, 512, $transparent);

        imagecopyresampled($dstImage, $srcImage, 0, 0, $x, $y, 512, 512, $size, $size);

        $fileName = $uuid . '-' . time() . '.png';
        $subDir = ($type === 'custom') ? 'custom' : 'default';
        $relativePath = "public/storage/profilePicture/$subDir/$fileName";
        $absolutePath = __DIR__ . '/../../' . $relativePath;
        
        $dir = dirname($absolutePath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        if (imagepng($dstImage, $absolutePath, 9)) {
            imagedestroy($srcImage);
            imagedestroy($dstImage);

            $data = file_get_contents($absolutePath);
            $base64 = 'data:image/png;base64,' . base64_encode($data);

            return [
                'success' => true,
                'db_path' => $relativePath,
                'base64' => $base64,
                'dominant_color' => $dominantColor // Retornamos el color calculado
            ];
        }

        return ['success' => false, 'message' => 'Error al guardar la imagen en el servidor.'];
    }

    // ===============================================================================================
    // HELPERS DE USUARIO Y AVATAR
    // ===============================================================================================

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

    // Usamos GD local para generar avatares por defecto
    public static function generateDefaultProfilePicture($name, $outputPath) {
        $width = 256; $height = 256;
        $im = imagecreatetruecolor($width, $height);
        
        $hash = md5($name);
        $r = hexdec(substr($hash, 0, 2));
        $g = hexdec(substr($hash, 2, 2));
        $b = hexdec(substr($hash, 4, 2));
        
        $bgColor = imagecolorallocate($im, $r, $g, $b);
        imagefilledrectangle($im, 0, 0, $width, $height, $bgColor);
        
        $textColor = imagecolorallocate($im, 255, 255, 255);
        $fontPath = __DIR__ . '/../../public/assets/fonts/Inter-Bold.ttf'; 
        
        $initial = strtoupper(substr($name, 0, 1));
        
        if (file_exists($fontPath)) {
            $bbox = imagettfbbox(100, 0, $fontPath, $initial);
            $x = $bbox[0] + (imagesx($im) / 2) - ($bbox[4] / 2) - 10;
            $y = $bbox[1] + (imagesy($im) / 2) - ($bbox[5] / 2) - 5;
            imagettftext($im, 100, 0, $x, $y, $textColor, $fontPath, $initial);
        } else {
            $font = 5;
            $fw = imagefontwidth($font);
            $fh = imagefontheight($font);
            $x = ($width - $fw * strlen($initial)) / 2;
            $y = ($height - $fh) / 2;
            imagestring($im, $font, $x, $y, $initial, $textColor);
        }

        $dir = dirname($outputPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $saved = imagepng($im, $outputPath);
        imagedestroy($im);
        return $saved;
    }

    // ===============================================================================================
    // SEGURIDAD, RATE LIMITING Y LOGS (MEJORADOS)
    // ===============================================================================================

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
            $sql = "INSERT INTO security_logs (user_identifier, action_type, ip_address, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))";
            $stmt = $pdo->prepare($sql);
            $logId = $identifier ?? 'Anonymous';
            $stmt->execute([$logId, $actionType, $ip, $minutes]);
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

    // ===============================================================================================
    // UTILIDADES DEL SISTEMA (FILES, TIME, UA)
    // ===============================================================================================

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
        if ($bytes === 0) return '0 B';
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' bytes';
    }

    // Cálculo seguro de tiempo transcurrido
    public static function timeElapsedString($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime(is_int($datetime) ? "@$datetime" : $datetime);
        $diff = $now->diff($ago);

        $weeks = floor($diff->d / 7);
        $days = $diff->d - ($weeks * 7);

        $map = [
            'y' => ['value' => $diff->y, 'label' => 'año'],
            'm' => ['value' => $diff->m, 'label' => 'mes'],
            'w' => ['value' => $weeks,   'label' => 'semana'],
            'd' => ['value' => $days,    'label' => 'día'],
            'h' => ['value' => $diff->h, 'label' => 'hora'],
            'i' => ['value' => $diff->i, 'label' => 'minuto'],
            's' => ['value' => $diff->s, 'label' => 'segundo'],
        ];

        $string = [];
        foreach ($map as $key => $info) {
            if ($info['value'] > 0) {
                $string[] = $info['value'] . ' ' . $info['label'] . ($info['value'] > 1 ? 's' : '');
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? 'hace ' . implode(', ', $string) : 'justo ahora';
    }

    // Protección Path Traversal
    public static function securePath($baseDir, $relativePath) {
        $cleanPath = str_replace('..', '', $relativePath);
        $cleanPath = ltrim($cleanPath, '/\\');
        
        $fullPath = $baseDir . '/' . $cleanPath;
        $realPath = realpath($fullPath);
        
        if ($realPath && file_exists($realPath) && strpos($realPath, realpath($baseDir)) === 0) {
            return $realPath;
        }
        
        return false;
    }

    // ===============================================================================================
    // PRIVILEGIOS Y JERARQUÍA (Consolidado)
    // ===============================================================================================

    public static function getRoleLevel($role) {
        $hierarchy = [
            'founder'       => 3,
            'administrator' => 2,
            'moderator'     => 1,
            'user'          => 0
        ];
        return $hierarchy[$role] ?? 0;
    }

    public static function checkUserPrivileges($pdo, $userId, $allowedRoles = [], $require2fa = false) {
        if (!$userId) return ['allowed' => false, 'reason' => 'no_session'];

        $stmt = $pdo->prepare("SELECT role, two_factor_enabled FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return ['allowed' => false, 'reason' => 'user_not_found'];

        if (!empty($allowedRoles)) {
            $reqRoles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
            if (!in_array($user['role'], $reqRoles)) {
                return ['allowed' => false, 'reason' => 'role_mismatch'];
            }
        }

        if ($require2fa) {
            $isGlobal2faActive = self::getServerConfig($pdo, 'security_admin_require_2fa', '1') === '1';

            if ($isGlobal2faActive) {
                if ((int)$user['two_factor_enabled'] !== 1) {
                    return ['allowed' => false, 'reason' => '2fa_not_enabled', 'message' => 'Esta acción requiere activar 2FA.'];
                }
                if (empty($_SESSION['is_2fa_verified'])) {
                    return ['allowed' => false, 'reason' => '2fa_not_verified', 'message' => 'Verifica tu identidad con 2FA.'];
                }
            }
        }

        return ['allowed' => true, 'reason' => null];
    }

    public static function checkHierarchicalAccess($pdo, $requesterId, $targetId) {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$requesterId]);
        $reqRole = $stmt->fetchColumn();
        $reqLevel = self::getRoleLevel($reqRole);

        $stmt->execute([$targetId]);
        $targetRole = $stmt->fetchColumn();
        $targetLevel = self::getRoleLevel($targetRole);

        if ($requesterId == $targetId) {
            return ['allowed' => true, 'role' => $targetRole];
        }

        if ($reqRole === 'founder' || $reqLevel > $targetLevel) {
            return ['allowed' => true, 'role' => $targetRole];
        }

        return ['allowed' => false, 'message' => 'Jerarquía insuficiente.'];
    }
}
?>