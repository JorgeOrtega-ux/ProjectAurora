<?php
// includes/core/app-setup.php

use Aurora\Libs\Utils;
use Aurora\Services\AuthService;

// 1. BOOTSTRAP (Aquí se inicia sesión y se conecta Redis/DB)
$services = require_once __DIR__ . '/../bootstrap.php';
$pdo = $services['pdo'];
$i18n = $services['i18n'];
$redis = $services['redis'];

// ============================================================================
// 1.1 [NUEVO] MODO PÁNICO (FIREWALL ESTRICTO)
// Bloquea todo el tráfico no autenticado para salvar la base de datos.
// Se ejecuta antes que el Rate Limiter normal.
// ============================================================================
if ($redis) {
    try {
        $isPanicMode = $redis->get('firewall:strict');
        
        // Si hay pánico y el usuario NO tiene sesión (es invitado)
        if ($isPanicMode === 'true' && !isset($_SESSION['user_id'])) {
            http_response_code(503); // Service Unavailable
            header('Retry-After: 300'); // Sugerir reintento en 5 min
            
            // Renderizamos pantalla de bloqueo de emergencia (Ultra ligera)
            ?>
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Sistema en Protección</title>
                <style>
                    body { background: #111; color: #fff; font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
                    .container { max-width: 400px; padding: 20px; }
                    .icon { font-size: 48px; margin-bottom: 20px; display: block; }
                    h1 { font-size: 24px; margin-bottom: 10px; color: #ff4444; }
                    p { color: #aaa; line-height: 1.5; }
                </style>
            </head>
            <body>
                <div class="container">
                    <span class="icon">🛡️</span>
                    <h1>Acceso Restringido Temporalmente</h1>
                    <p>
                        El sistema está experimentando una carga inusual y ha activado sus protocolos de seguridad.
                        <br><br>
                        El acceso para usuarios no registrados está pausado momentáneamente.
                        Si ya tienes cuenta, por favor intenta acceder más tarde.
                    </p>
                </div>
            </body>
            </html>
            <?php
            exit; // DETENER EJECUCIÓN TOTAL
        }
    } catch (Exception $e) {
        // Si falla Redis, fallar abierto (permitir tráfico) o loguear error silencioso
        error_log("Error verificando Panic Mode: " . $e->getMessage());
    }
}

// ============================================================================
// 1.5 FIREWALL DE APLICACIÓN (RATE LIMITER) - VERSIÓN VISUAL
// Configuración: 35 peticiones cada 60 segundos.
// ============================================================================
if (Utils::checkFirewallFlood($redis, 35, 60)) {
    http_response_code(429); // Too Many Requests
    header('Retry-After: 60');
    
    // Renderizamos una pantalla de error visual idéntica a status-screen.php
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
    // [MODIFICADO] Ya no hacemos require manual, usamos el namespace importado arriba
    $authService = new AuthService($pdo, $i18n, $redis);
    $authService->attemptAutoLogin();
}

// 4. CSRF TOKEN INIT
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>