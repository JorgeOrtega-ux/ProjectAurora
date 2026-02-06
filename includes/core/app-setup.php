<?php
// includes/core/app-setup.php

// 1. BOOTSTRAP
$services = require_once __DIR__ . '/../bootstrap.php';
$pdo = $services['pdo'];
$i18n = $services['i18n'];
$redis = $services['redis'];

// ============================================================================
// 1.5 FIREWALL DE APLICACIÓN (RATE LIMITER) - VERSIÓN VISUAL
// Configuración: 35 peticiones cada 60 segundos.
// ============================================================================
if (Utils::checkFirewallFlood($redis, 35, 60)) {
    http_response_code(429); // Too Many Requests
    header('Retry-After: 60');
    
    // Renderizamos una pantalla de error visual idéntica a status-screen.php
    // Usamos estilos inline para que sea ultra-ligero y rápido.
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Límite de tráfico excedido</title>
        <style>
            :root { --text-primary: #111827; --text-secondary: #6b7280; --bg-hover-light: #f3f4f6; --border-light: #e5e7eb; --action-primary: #000; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; background: #f9fafb; min-height: 100vh; display: flex; flex-direction: column; }
            .component-layout-centered { flex: 1; display: flex; align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
            .component-card { background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 32px; max-width: 400px; width: 100%; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
            .component-page-title { margin: 0 0 10px; font-size: 24px; font-weight: 700; color: var(--text-primary); }
            .component-page-description { color: var(--text-secondary); margin-bottom: 24px; line-height: 1.5; font-size: 15px; }
            .component-button { display: inline-flex; justify-content: center; align-items: center; width: 100%; padding: 12px; background: #000; color: white; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 14px; transition: opacity 0.2s; }
            .component-button:hover { opacity: 0.9; }
            .icon-container { background: #fee2e2; color: #dc2626; width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; }
            svg { width: 24px; height: 24px; }
        </style>
    </head>
    <body>
        <div class="component-layout-centered">
            <div class="component-card">
                <div class="icon-container">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="component-page-title">Tráfico inusual detectado</h1>
                <p class="component-page-description">
                    Hemos detectado muchas solicitudes desde tu red en muy poco tiempo. 
                    <br><br>
                    Por seguridad, tu acceso se ha pausado momentáneamente. Por favor, espera <strong>1 minuto</strong> antes de intentar acceder de nuevo.
                </p>
                <a href="javascript:location.reload()" class="component-button">
                    Intentar nuevamente
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit; // Detenemos la ejecución aquí
}

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
?>