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

// 1. Rate Limiting (Protección anti-spam)
function checkActionRateLimit($pdo, $identifier, $actionType, $limit, $minutes) {
    $sql = "SELECT COUNT(*) as total 
            FROM security_logs 
            WHERE user_identifier = ? 
            AND action_type = ? 
            AND created_at > (NOW() - INTERVAL $minutes MINUTE)";
    
    $stmt = $pdo->prepare($sql);
    // Convertimos a string para asegurar compatibilidad
    $stmt->execute([(string)$identifier, $actionType]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return ($result['total'] >= $limit);
}

// 2. Registrar acción de seguridad
function logSecurityAction($pdo, $identifier, $actionType) {
    $ip = get_client_ip();
    $sql = "INSERT INTO security_logs (user_identifier, action_type, ip_address, created_at) 
            VALUES (?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(string)$identifier, $actionType, $ip]);
}

// 3. GENERAR TOKEN PARA WEBSOCKET (Aquí estaba el error, esta función faltaba)
function generate_ws_auth_token($pdo, $userId) {
    // Limpiar tokens viejos
    $stmt = $pdo->prepare("DELETE FROM ws_auth_tokens WHERE user_id = ?");
    $stmt->execute([$userId]);

    // Crear nuevo token
    $token = bin2hex(random_bytes(32)); 
    
    // Guardar en BD (expira en 2 minutos)
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

// Función puente PHP -> Python Socket
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
?>