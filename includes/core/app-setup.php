<?php
// includes/core/app-setup.php

// 1. BOOTSTRAP
$services = require_once __DIR__ . '/../bootstrap.php';
// [REFACTORIZADO] Asignación explícita para que index.php tenga acceso
$pdo = $services['pdo'];
$i18n = $services['i18n'];
$redis = $services['redis'];

// 2. SEGURIDAD HTTP
$cspNonce = Utils::applySecurityHeaders();

// 3. AUTO-LOGIN
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../../api/services/AuthService.php';
    $authService = new AuthService($pdo, $i18n, $redis);
    $authService->attemptAutoLogin();
}

// 4. CSRF TOKEN INIT
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}