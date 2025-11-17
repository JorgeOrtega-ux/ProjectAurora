<?php
// config/utilities.php

// 1. CONFIGURACIÓN DE ZONA HORARIA Y LÍMITES
date_default_timezone_set('America/Matamoros');

// Configuración de seguridad
define('MAX_LOGIN_ATTEMPTS', 5);      // Intentos permitidos antes del bloqueo
define('LOCKOUT_TIME_MINUTES', 5);    // Tiempo de espera en minutos

/**
 * Obtiene la IP real del cliente (incluso si está detrás de un proxy)
 */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

/**
 * Verifica si el usuario o la IP están bloqueados actualmente.
 * MODIFICADO: Ahora acepta un parámetro opcional $specificAction.
 * Si se envía (ej: 'login_fail'), solo cuenta fallos de ese tipo.
 */
function checkLockStatus($pdo, $identifier, $specificAction = null) {
    $ip = get_client_ip();
    $limit = MAX_LOGIN_ATTEMPTS;
    $minutes = LOCKOUT_TIME_MINUTES;

    // Consulta base: Contar fallos recientes por usuario O IP
    $sql = "SELECT COUNT(*) as total 
            FROM security_logs 
            WHERE (user_identifier = ? OR ip_address = ?) 
            AND created_at > (NOW() - INTERVAL $minutes MINUTE)";
    
    $params = [$identifier, $ip];

    // [NUEVO] Si nos pasaron un tipo de acción, filtramos por ella
    if ($specificAction !== null) {
        $sql .= " AND action_type = ?";
        $params[] = $specificAction;
    }
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return ($result['total'] >= $limit);
}

/**
 * Registra un intento fallido en la base de datos.
 */
function logFailedAttempt($pdo, $identifier, $actionType) {
    $ip = get_client_ip();
    
    $sql = "INSERT INTO security_logs (user_identifier, action_type, ip_address, created_at) 
            VALUES (?, ?, ?, NOW())";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$identifier, $actionType, $ip]);
}

/**
 * Limpia el historial de fallos tras un inicio de sesión exitoso.
 */
function clearFailedAttempts($pdo, $identifier) {
    // Borramos logs de este usuario para liberar el bloqueo inmediatamente al acertar
    $sql = "DELETE FROM security_logs WHERE user_identifier = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$identifier]);
}

// ==========================================
// [NUEVO] FUNCIONES CSRF
// ==========================================

/**
 * Genera un token CSRF si no existe y lo devuelve.
 */
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica que el token recibido coincida con el de la sesión.
 */
function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
?>