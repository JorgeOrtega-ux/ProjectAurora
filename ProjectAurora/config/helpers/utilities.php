<?php
// config/helpers/utilities.php

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

function generate_ws_auth_token($pdo, $userId, $sessionId) {
    $stmt = $pdo->prepare("DELETE FROM ws_auth_tokens WHERE user_id = ?");
    $stmt->execute([$userId]);

    $token = bin2hex(random_bytes(32)); 
    
    $stmt = $pdo->prepare("INSERT INTO ws_auth_tokens (user_id, session_id, token, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))");
    $stmt->execute([$userId, $sessionId, $token]);

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
        'target_id' => (string)$targetUserId, 
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

function detect_browser_language() {
    $availableLanguages = ['es-latam', 'es-mx', 'en-us', 'en-gb'];
    $familyFallbacks = ['es' => 'es-latam', 'en' => 'en-us'];
    $default = 'en-us';

    if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) return $default;

    $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    foreach ($langs as $lang) {
        $lang = trim(explode(';', $lang)[0]); 
        $lang = strtolower($lang); 

        if (in_array($lang, $availableLanguages)) return $lang;

        $langPrefix = substr($lang, 0, 2);
        if (array_key_exists($langPrefix, $familyFallbacks)) return $familyFallbacks[$langPrefix];
    }
    return $default;
}

function getServerConfig($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM server_config WHERE id = 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$config) {
            return ['maintenance_mode' => 0, 'allow_registrations' => 1];
        }
        return $config;
    } catch (Exception $e) {
        return ['maintenance_mode' => 0, 'allow_registrations' => 1];
    }
}

function countActiveSessions($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM user_sessions");
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}
?>