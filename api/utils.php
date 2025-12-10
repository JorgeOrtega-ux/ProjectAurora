<?php
// api/utils.php

// ==========================================
// 1. CONFIGURACIÓN COMPARTIDA (Directorios)
// ==========================================
// Definimos las rutas relativas a la carpeta API
define('UPLOAD_BASE_DIR', __DIR__ . '/../public/assets/uploads/avatars/');
define('DIR_CUSTOM', UPLOAD_BASE_DIR . 'custom/');
define('DIR_DEFAULT', UPLOAD_BASE_DIR . 'default/');
define('URL_BASE_AVATARS', 'assets/uploads/avatars/');

// Asegurar que existan los directorios
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

function validateEmailRequirements($email) {
    // Verificar formato básico
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return __('api.error.email_format');
    
    $atPos = strrpos($email, '@');
    if ($atPos === false) return __('api.error.email_format');
    
    $prefix = substr($email, 0, $atPos);
    $domain = strtolower(substr($email, $atPos + 1));
    
    // Verificar longitud mínima del usuario (ej. a@b.c es muy corto)
    if (strlen($prefix) < 4) return __('api.error.email_format'); 
    
    // Lista blanca de dominios permitidos
    $allowedDomains = ['gmail.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'yahoo.com'];
    if (!in_array($domain, $allowedDomains)) return __('api.error.email_domain');
    
    return true;
}

function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function generate_verification_code() {
    return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
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
// 5. FUNCIONES DE AVATAR
// ==========================================

function ensureDefaultAvatarExists($uuid, $username) {
    $targetFile = DIR_DEFAULT . $uuid . '.png';
    if (!file_exists($targetFile)) {
        try {
            $randomColor = str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
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