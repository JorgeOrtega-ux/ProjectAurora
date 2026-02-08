<?php
// api/handlers/system-handler.php

// 1. BOOTSTRAP: Subimos dos niveles (../../)
$services = require_once __DIR__ . '/../../includes/bootstrap.php';
// [REFACTORIZADO] Asignación explícita de servicios
$pdo = $services['pdo'];
$i18n = $services['i18n'];
$redis = $services['redis'];

use Aurora\Services\BackupService;
use Aurora\Libs\Utils;

// 2. SEGURIDAD AVANZADA (HMAC + Token Rotation)
// Recuperamos las cabeceras de seguridad
$systemKey = getenv('SYSTEM_API_KEY');
$timestamp = $_SERVER['HTTP_X_SYSTEM_TIMESTAMP'] ?? 0;
$signature = $_SERVER['HTTP_X_SYSTEM_SIGNATURE'] ?? '';

// Verificar que la llave exista en el servidor
if (empty($systemKey)) {
    http_response_code(500);
    die('System Configuration Error: Key not found.');
}

// A. Protección Anti-Replay (Ventana de 5 minutos)
// Si la petición es muy vieja o viene del futuro, se rechaza.
if (abs(time() - $timestamp) > 300) {
    http_response_code(403);
    Utils::jsonResponse(['success' => false, 'message' => 'Security Error: Request timestamp expired.']);
}

// B. Verificación de Firma Criptográfica (HMAC-SHA256)
// Recreamos la firma usando la llave secreta local y el timestamp recibido.
$expectedSignature = hash_hmac('sha256', $timestamp, $systemKey);

// Comparamos las firmas usando hash_equals para prevenir ataques de tiempo.
if (!hash_equals($expectedSignature, $signature)) {
    // Logueamos el intento fallido con la IP real (si disponible) para auditoría
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'];
    error_log("Security Breach: Invalid HMAC signature from IP " . $ip);
    
    http_response_code(403);
    Utils::jsonResponse(['success' => false, 'message' => 'Security Error: Invalid Signature.']);
}

// 4. Inicializar Servicios (UserId 0 = System)
// [MODIFICADO] Inyectamos Redis
$backupService = new BackupService($pdo, $i18n, 0, $redis);

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create_backup_auto':
        // Flag true indica que es sistema (salta rate limit)
        Utils::jsonResponse($backupService->createBackup(true));
        break;
        
    default:
        Utils::jsonResponse(['success' => false, 'message' => 'Unknown system action']);
}
?>