<?php
// config/utilities.php

date_default_timezone_set('America/Matamoros');

define('MAX_LOGIN_ATTEMPTS', 5);      
define('LOCKOUT_TIME_MINUTES', 5);    

function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

function checkLockStatus($pdo, $identifier, $specificAction = null) {
    $ip = get_client_ip();
    $limit = MAX_LOGIN_ATTEMPTS;
    $minutes = LOCKOUT_TIME_MINUTES;

    $sql = "SELECT COUNT(*) as total 
            FROM security_logs 
            WHERE (user_identifier = ? OR ip_address = ?) 
            AND created_at > (NOW() - INTERVAL $minutes MINUTE)";
    $params = [$identifier, $ip];

    if ($specificAction !== null) {
        $sql .= " AND action_type = ?";
        $params[] = $specificAction;
    }
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return ($result['total'] >= $limit);
}

function logFailedAttempt($pdo, $identifier, $actionType) {
    $ip = get_client_ip();
    $sql = "INSERT INTO security_logs (user_identifier, action_type, ip_address, created_at) 
            VALUES (?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$identifier, $actionType, $ip]);
}

function clearFailedAttempts($pdo, $identifier) {
    $sql = "DELETE FROM security_logs WHERE user_identifier = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$identifier]);
}

// --- NUEVAS FUNCIONES DE SEGURIDAD ---

function checkActionRateLimit($pdo, $identifier, $actionType, $limit, $minutes) {
    $sql = "SELECT COUNT(*) as total 
            FROM security_logs 
            WHERE user_identifier = ? 
            AND action_type = ? 
            AND created_at > (NOW() - INTERVAL $minutes MINUTE)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(string)$identifier, $actionType]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return ($result['total'] >= $limit);
}

function logSecurityAction($pdo, $identifier, $actionType) {
    $ip = get_client_ip();
    $sql = "INSERT INTO security_logs (user_identifier, action_type, ip_address, created_at) 
            VALUES (?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(string)$identifier, $actionType, $ip]);
}

function generate_ws_auth_token($pdo, $userId) {
    $stmt = $pdo->prepare("DELETE FROM ws_auth_tokens WHERE user_id = ?");
    $stmt->execute([$userId]);

    $token = bin2hex(random_bytes(32)); 
    
    $stmt = $pdo->prepare("INSERT INTO ws_auth_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))");
    $stmt->execute([$userId, $token]);

    return $token;
}

function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function send_live_notification($targetUserId, $type, $data = []) {
    $host = '127.0.0.1';
    $port = 8081; 

    $payload = json_encode([
        'target_id' => $targetUserId,
        'type' => $type, 
        'payload' => $data
    ]);

    $fp = @fsockopen($host, $port, $errno, $errstr, 1); 
    if ($fp) {
        fwrite($fp, $payload);
        fclose($fp);
        return true;
    }
    return false; 
}

/**
 * Detecta el idioma del navegador y asigna el más cercano disponible.
 * Idiomas soportados en ProjectAurora: es-latam, es-mx, en-us, en-gb
 */
function detect_browser_language() {
    // Idiomas disponibles en el sistema
    $availableLanguages = ['es-latam', 'es-mx', 'en-us', 'en-gb'];
    
    // Fallbacks genéricos si no hay coincidencia exacta
    // Si viene cualquier español (es-AR, es-ES, es-CO) -> es-latam
    // Si viene cualquier inglés (en-AU, en-CA) -> en-us
    $familyFallbacks = [
        'es' => 'es-latam',
        'en' => 'en-us'
    ];
    
    // Por defecto
    $default = 'en-us';

    if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        return $default;
    }

    // Parsear header: es-MX,es;q=0.9,en-US;q=0.8,en;q=0.7
    $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    
    foreach ($langs as $lang) {
        $lang = trim(explode(';', $lang)[0]); // Quitamos el q=0.9
        $lang = strtolower($lang); // Convertir a minúsculas (es-mx)

        // 1. Coincidencia Exacta
        if (in_array($lang, $availableLanguages)) {
            return $lang;
        }

        // 2. Coincidencia por Familia (los dos primeros caracteres)
        $langPrefix = substr($lang, 0, 2);
        if (array_key_exists($langPrefix, $familyFallbacks)) {
            return $familyFallbacks[$langPrefix];
        }
    }

    return $default;
}
?>