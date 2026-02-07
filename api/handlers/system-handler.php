<?php
// api/handlers/system-handler.php

// 1. SEGURIDAD DE RED (FIREWALL A NIVEL DE APLICACIÓN)
// Este archivo solo debe ser ejecutado por CRON o scripts locales (Python).
// Bloqueamos cualquier petición que no venga del propio servidor.
$allowedIps = ['127.0.0.1', '::1'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIps)) {
    http_response_code(403);
    die('System Access Denied: Origin not allowed.');
}

// 2. BOOTSTRAP: Subimos dos niveles (../../)
$services = require_once __DIR__ . '/../../includes/bootstrap.php';
// [REFACTORIZADO] Asignación explícita de servicios
$pdo = $services['pdo'];
$i18n = $services['i18n'];
$redis = $services['redis'];

use Aurora\Services\BackupService;
use Aurora\Libs\Utils;

// 3. Seguridad: Verificar API Key del Sistema
$systemKey = getenv('SYSTEM_API_KEY');
$requestKey = $_SERVER['HTTP_X_SYSTEM_KEY'] ?? '';

if (empty($systemKey) || $requestKey !== $systemKey) {
    http_response_code(403);
    Utils::jsonResponse(['success' => false, 'message' => 'Unauthorized System Access']);
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