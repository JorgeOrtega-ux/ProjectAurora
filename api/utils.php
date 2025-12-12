<?php
// api/utils.php

// ==========================================
// 1. CONFIGURACIÓN COMPARTIDA (Directorios)
// ==========================================
define('UPLOAD_BASE_DIR', __DIR__ . '/../public/assets/uploads/avatars/');
define('DIR_CUSTOM', UPLOAD_BASE_DIR . 'custom/');
define('DIR_DEFAULT', UPLOAD_BASE_DIR . 'default/');
define('URL_BASE_AVATARS', 'assets/uploads/avatars/');

if (!is_dir(DIR_CUSTOM)) @mkdir(DIR_CUSTOM, 0755, true);
if (!is_dir(DIR_DEFAULT)) @mkdir(DIR_DEFAULT, 0755, true);

// ==========================================
// 2. FUNCIONES DE RESPUESTA Y RED
// ==========================================

function sendJsonResponse($status, $message, $redirectUrl = null, $data = []) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'redirect' => $redirectUrl,
        'data' => $data
    ]);
    exit;
}

function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) $ip = $_SERVER['HTTP_CLIENT_IP'];
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $ip;
}

// ==========================================
// 3. FUNCIONES DE VALIDACIÓN Y FORMATO
// ==========================================

/**
 * Valida requisitos de email. 
 * Acepta $allowedDomains dinámico.
 */
function validateEmailRequirements($email, $allowedDomains = []) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return __('api.error.email_format');
    
    $atPos = strrpos($email, '@');
    if ($atPos === false) return __('api.error.email_format');
    
    $prefix = substr($email, 0, $atPos);
    $domain = strtolower(substr($email, $atPos + 1));
    
    if (strlen($prefix) < 4) return __('api.error.email_format'); 
    
    // Validación de dominios permitidos
    if (!empty($allowedDomains)) {
        $normalizedAllowed = array_map('strtolower', $allowedDomains);
        if (!in_array($domain, $normalizedAllowed)) {
            return __('api.error.email_domain');
        }
    }
    
    return true;
}

/**
 * Genera un UUID v4 seguro criptográficamente.
 * CORRECCIÓN: Se eliminó el fallback a mt_rand(). Si random_int() falla,
 * debe lanzarse la excepción para no comprometer la seguridad.
 */
function generate_uuid() {
    // Si el sistema no tiene suficiente entropía, lanzará una Exception.
    // Esto es el comportamiento deseado en seguridad (Fail Secure).
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0x0fff) | 0x4000, random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
    );
}

/**
 * Genera un código numérico de 6 dígitos seguro.
 * CORRECCIÓN: Se eliminó el fallback a mt_rand().
 */
function generate_verification_code() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// ==========================================
// 4. FUNCIONES DE LOGS Y SEGURIDAD (BD)
// ==========================================

function logUserAccess($pdo, $userId) {
    try {
        $ip = getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $ip, $userAgent]);
    } catch (Exception $e) { }
}

/**
 * Registra la sesión actual en la base de datos para gestión de dispositivos.
 */
function registerActiveSession($pdo, $userId) {
    try {
        $sessionId = session_id();
        $ip = getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

        $pdo->prepare("DELETE FROM active_sessions WHERE session_id = ?")->execute([$sessionId]);

        $stmt = $pdo->prepare("INSERT INTO active_sessions (user_id, session_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $sessionId, $ip, $userAgent]);
    } catch (Exception $e) { }
}

/**
 * Parsea el User Agent para mostrar algo legible en la UI
 */
function parseUserAgentSimple($ua) {
    $browser = __('global.unknown_browser');
    $os = __('global.unknown_system');
    $icon = 'device_unknown'; 

    if (preg_match('/windows|win32/i', $ua)) { $os = 'Windows'; $icon = 'desktop_windows'; }
    elseif (preg_match('/macintosh|mac os x/i', $ua)) { $os = 'macOS'; $icon = 'laptop_mac'; }
    elseif (preg_match('/linux/i', $ua)) { $os = 'Linux'; $icon = 'terminal'; }
    elseif (preg_match('/android/i', $ua)) { $os = 'Android'; $icon = 'phone_android'; }
    elseif (preg_match('/iphone|ipad|ipod/i', $ua)) { $os = 'iOS'; $icon = 'phone_iphone'; }

    if (preg_match('/MSIE|Trident/i', $ua)) $browser = 'Internet Explorer';
    elseif (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/Chrome/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/Safari/i', $ua)) $browser = 'Safari';
    elseif (preg_match('/Edge/i', $ua)) $browser = 'Edge';

    return ['os' => $os, 'browser' => $browser, 'icon' => $icon];
}

function logSecurityEvent($pdo, $identifier, $actionType) {
    try {
        $ip = getClientIP();
        $identifier = substr($identifier, 0, 250);
        $stmt = $pdo->prepare("INSERT INTO security_logs (user_identifier, action_type, ip_address, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$identifier, $actionType, $ip]);
    } catch (Exception $e) {}
}

function checkRateLimit($pdo, $identifier, $actionType, $limit, $minutes, $checkByIp = false) {
    $ip = getClientIP();
    
    if ($checkByIp) {
        $sql = "SELECT COUNT(*) FROM security_logs WHERE ip_address = ? AND action_type = ? AND created_at > (NOW() - INTERVAL ? MINUTE)";
        $params = [$ip, $actionType, $minutes];
    } else {
        $sql = "SELECT COUNT(*) FROM security_logs WHERE (user_identifier = ? OR ip_address = ?) AND action_type = ? AND created_at > (NOW() - INTERVAL ? MINUTE)";
        $params = [$identifier, $ip, $actionType, $minutes];
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->fetchColumn();

        if ($count >= $limit) {
            sendJsonResponse('error', __('api.error.rate_limit'));
        }
    } catch (Exception $e) {}
}

// ==========================================
// 5. FUNCIONES DE PERFIL Y AVATAR
// ==========================================

function logProfileChange($pdo, $userId, $type, $oldValue, $newValue) {
    try {
        $stmt = $pdo->prepare("INSERT INTO user_profile_history (user_id, change_type, old_value, new_value) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $type, $oldValue, $newValue]);
    } catch (Exception $e) { }
}

function checkProfileChangeLimit($pdo, $userId, $type, $daysInterval, $maxChanges) {
    try {
        $sql = "SELECT COUNT(*) FROM user_profile_history WHERE user_id = ? AND change_type = ? AND created_at > (NOW() - INTERVAL $daysInterval DAY)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $type]);
        $count = $stmt->fetchColumn();
        return ($count < $maxChanges);
    } catch (Exception $e) {
        return false;
    }
}

function ensureDefaultAvatarExists($uuid, $username) {
    $targetFile = DIR_DEFAULT . $uuid . '.png';
    if (!file_exists($targetFile)) {
        try {
            // CORRECCIÓN: Uso exclusivo de random_int()
            $randColorDec = random_int(0, 0xFFFFFF);
            $randomColor = str_pad(dechex($randColorDec), 6, '0', STR_PAD_LEFT);
            
            $url = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=" . $randomColor . "&color=fff&size=128&format=png";
            $imgContent = @file_get_contents($url);
            if ($imgContent) {
                @file_put_contents($targetFile, $imgContent);
                return true;
            }
        } catch (Exception $e) { }
        return false;
    }
    return true; 
}
?>