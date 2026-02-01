<?php
// includes/core/app-setup.php

// 1. BOOTSTRAP
$services = require_once __DIR__ . '/../bootstrap.php';
// Extraemos $pdo, $i18n, $redis para que estén disponibles en el ámbito global de index.php
extract($services); 

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