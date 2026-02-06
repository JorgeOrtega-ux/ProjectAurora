<?php
// includes/core/routing-logic.php

// Variables globales requeridas: $pdo, $currentSection, $basePath, $i18n
require_once __DIR__ . '/Gatekeeper.php';

// [BLOQUE DE REFRESCAR SESIÓN - SE MANTIENE CRÍTICO]
// Necesitamos datos frescos antes de preguntar al Gatekeeper
$isLoggedIn = isset($_SESSION['user_id']);

if ($isLoggedIn) {
    try {
        // Verificar token de persistencia
        if (isset($_SESSION['current_token_id'])) {
            $stmtToken = $pdo->prepare("SELECT id FROM user_auth_tokens WHERE id = ?");
            $stmtToken->execute([$_SESSION['current_token_id']]);
            if (!$stmtToken->fetch()) {
                session_unset();
                session_destroy();
                setcookie('auth_persistence_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
                header("Location: " . $basePath . "login");
                exit;
            }
        }

        // Obtener datos frescos del usuario
        $stmt = $pdo->prepare("SELECT role, avatar_path, username, email, two_factor_enabled, account_status, suspension_ends_at, status_reason FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $freshUser = $stmt->fetch();

        if ($freshUser) {
            // Lógica de Bloqueo de Cuenta (Deleted / Suspended)
            $isRestricted = false;
            if ($freshUser['account_status'] === 'deleted') {
                $isRestricted = true;
            } elseif ($freshUser['account_status'] === 'suspended') {
                $isPermanent = empty($freshUser['suspension_ends_at']);
                $isActiveSuspension = $isPermanent || (strtotime($freshUser['suspension_ends_at']) > time());
                if ($isActiveSuspension) $isRestricted = true;
            }

            if ($isRestricted) {
                $restrictionData = [
                    'status' => $freshUser['account_status'],
                    'reason' => $freshUser['status_reason'],
                    'suspension_ends_at' => $freshUser['suspension_ends_at']
                ];
                session_unset();
                session_destroy();
                session_start();
                $_SESSION['account_status_data'] = $restrictionData;
                header("Location: " . $basePath . "account-status");
                exit;
            }

            // Actualizar sesión con datos frescos (IMPORTANTE PARA EL GATEKEEPER)
            $_SESSION['role'] = $freshUser['role'];
            $_SESSION['avatar'] = $freshUser['avatar_path'];
            $_SESSION['username'] = $freshUser['username'];
            $_SESSION['email'] = $freshUser['email'];
            $_SESSION['two_factor_enabled'] = $freshUser['two_factor_enabled'];

            // Recargar preferencias
            $stmtPrefs = $pdo->prepare("SELECT language, open_links_new_tab, theme, extended_toast FROM user_preferences WHERE user_id = ?");
            $stmtPrefs->execute([$_SESSION['user_id']]);
            $freshPrefs = $stmtPrefs->fetch(PDO::FETCH_ASSOC);

            if ($freshPrefs) {
                $previousLang = $_SESSION['preferences']['language'] ?? 'es-latam';
                $_SESSION['preferences'] = [
                    'language' => $freshPrefs['language'],
                    'open_links_new_tab' => (bool)$freshPrefs['open_links_new_tab'],
                    'theme' => $freshPrefs['theme'],
                    'extended_toast' => (bool)$freshPrefs['extended_toast']
                ];

                // Si cambió el idioma, reinstanciar i18n
                if ($freshPrefs['language'] !== $previousLang) {
                    $i18n = new I18n($freshPrefs['language']);
                }
            }
        } else {
            // Usuario no encontrado en DB
            session_unset();
            session_destroy();
            header("Location: " . $basePath . "login");
            exit;
        }
    } catch (Exception $e) {
        error_log("Error sesión: " . $e->getMessage());
    }
}

// === PREGUNTAMOS AL PORTERO ===
// $currentSection viene definida desde public/index.php
$decision = Gatekeeper::check($currentSection, $pdo);

$showInterface = true;
$fileToLoad = '';

switch ($decision['action']) {
    case Gatekeeper::SHOW_MAINTENANCE:
        $fileToLoad = __DIR__ . '/../../includes/sections/system/status-screen.php';
        $isMaintenanceContext = true;
        $showInterface = false;
        break;

    case Gatekeeper::REDIRECT:
        header("Location: " . $basePath . $decision['target']);
        exit;

    case Gatekeeper::SHOW_LOCK:
        $fileToLoad = __DIR__ . '/../../includes/sections/system/security-lock.php';
        // Mantenemos interface para que pueda ver el menú lateral e ir a configuración
        $showInterface = true; 
        break;

    case Gatekeeper::SHOW_404:
        $routesMap = require __DIR__ . '/../../config/routes.php';
        $fileToLoad = $routesMap['404'];
        break;

    case Gatekeeper::ALLOW:
        $routesMap = require __DIR__ . '/../../config/routes.php';
        $fileToLoad = $routesMap[$currentSection] ?? $routesMap['404'];
        
        // Verificación física del archivo
        if (!file_exists($fileToLoad)) {
            $fileToLoad = $routesMap['404'];
        }
        
        // Decidir si mostrar interfaz (header/sidebar)
        $securityRules = require __DIR__ . '/../../config/security.php';
        $noInterfaceRoutes = array_merge($securityRules['auth_routes'], ['account-status']);
        $showInterface = !in_array($currentSection, $noInterfaceRoutes);
        break;
}

// Variables JS globales
$jsUserPrefs = json_encode($_SESSION['preferences'] ?? new stdClass());
$jsTranslations = json_encode($i18n->getAll());
$globalAvatarSrc = Utils::getGlobalAvatarSrc();
$turnstileSiteKey = $_ENV['TURNSTILE_SITE_KEY'] ?? '';
$userLang = $_SESSION['preferences']['language'] ?? 'es-latam';
?>