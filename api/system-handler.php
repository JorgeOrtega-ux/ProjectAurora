<?php
// api/system-handler.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database/db.php';
require_once __DIR__ . '/../includes/libs/Utils.php';
require_once __DIR__ . '/services/BackupService.php';

// 1. Cargar Entorno
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(sprintf('%s=%s', trim($name), trim($value)));
    }
}

// 2. Seguridad: Verificar API Key del Sistema
$systemKey = getenv('SYSTEM_API_KEY');
$requestKey = $_SERVER['HTTP_X_SYSTEM_KEY'] ?? '';

if (empty($systemKey) || $requestKey !== $systemKey) {
    http_response_code(403);
    Utils::jsonResponse(['success' => false, 'message' => 'Unauthorized System Access']);
}

// 3. Inicializar Servicios (UserId 0 = System)
$i18n = new I18n('es-latam'); // Idioma default para logs
$backupService = new BackupService($pdo, $i18n, 0);

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