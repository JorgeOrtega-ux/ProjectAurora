<?php
// includes/core/routing-logic.php

// [IMPORTANTE] Iniciamos el buffer para poder borrar el header si es una petición AJAX
ob_start();

use Aurora\Core\Gatekeeper;
use Aurora\Libs\Utils;
use Aurora\Libs\I18n;

// Variables globales
global $pdo, $currentSection, $basePath, $i18n;

// Cargar seguridad
$securityRules = require __DIR__ . '/../../config/security.php';
$adminRoles = $securityRules['roles']['admin_access'] ?? ['founder', 'administrator'];

// [SESIÓN Y USUARIO]
$isLoggedIn = isset($_SESSION['user_id']);

if ($isLoggedIn) {
    try {
        if (isset($_SESSION['current_token_id'])) {
            $stmtToken = $pdo->prepare("SELECT id FROM user_auth_tokens WHERE id = ?");
            $stmtToken->execute([$_SESSION['current_token_id']]);
            if (!$stmtToken->fetch()) {
                session_unset(); session_destroy();
                setcookie('auth_persistence_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
                header("Location: " . $basePath . "login"); exit;
            }
        }

        $stmt = $pdo->prepare("SELECT uuid, role, avatar_path, username, email, two_factor_enabled, account_status, suspension_ends_at, status_reason FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $freshUser = $stmt->fetch();

        if ($freshUser) {
            // Lógica de Bloqueo
            $isRestricted = false;
            if ($freshUser['account_status'] === 'deleted') $isRestricted = true;
            elseif ($freshUser['account_status'] === 'suspended') {
                $isPermanent = empty($freshUser['suspension_ends_at']);
                if ($isPermanent || (strtotime($freshUser['suspension_ends_at']) > time())) $isRestricted = true;
            }

            if ($isRestricted) {
                $_SESSION['account_status_data'] = [
                    'status' => $freshUser['account_status'],
                    'reason' => $freshUser['status_reason'],
                    'suspension_ends_at' => $freshUser['suspension_ends_at']
                ];
                header("Location: " . $basePath . "account-status"); exit;
            }

            $_SESSION['uuid'] = $freshUser['uuid'];
            $_SESSION['role'] = $freshUser['role'];
            $_SESSION['avatar'] = $freshUser['avatar_path'];
            $_SESSION['username'] = $freshUser['username'];
            $_SESSION['email'] = $freshUser['email'];
            $_SESSION['two_factor_enabled'] = $freshUser['two_factor_enabled'];

            $stmtPrefs = $pdo->prepare("SELECT language, open_links_new_tab, theme, extended_toast FROM user_preferences WHERE user_id = ?");
            $stmtPrefs->execute([$_SESSION['user_id']]);
            $freshPrefs = $stmtPrefs->fetch(PDO::FETCH_ASSOC);

            if ($freshPrefs) {
                $_SESSION['preferences'] = [
                    'language' => $freshPrefs['language'],
                    'open_links_new_tab' => (bool)$freshPrefs['open_links_new_tab'],
                    'theme' => $freshPrefs['theme'],
                    'extended_toast' => (bool)$freshPrefs['extended_toast']
                ];
                if (($freshPrefs['language'] ?? '') !== ($_SESSION['preferences']['language'] ?? '')) {
                    $i18n = new I18n($freshPrefs['language']);
                }
            }
        } else {
            session_unset(); session_destroy();
            header("Location: " . $basePath . "login"); exit;
        }
    } catch (Exception $e) { error_log("Sesión Error: " . $e->getMessage()); }
}

$userRole = $_SESSION['role'] ?? 'guest';
$isAdmin = in_array($userRole, $adminRoles);

// [RUTAS DINÁMICAS CANAL]
$viewingChannelUUID = null;
$viewingChannelTab = 'home';

// Regex: /c/videos/UUID o /c/UUID
if (preg_match('/^[\/]?c\/(?:(videos|shorts)\/)?([a-zA-Z0-9-]+)$/', $currentSection, $matches)) {
    if (!empty($matches[1])) $viewingChannelTab = $matches[1];
    $viewingChannelUUID = $matches[2];
    $currentSection = 'channel-profile'; 
}

// [GATEKEEPER]
$decision = Gatekeeper::check($currentSection, $pdo);

$showInterface = true;
$fileToLoad = '';

switch ($decision['action']) {
    case Gatekeeper::SHOW_MAINTENANCE:
        $fileToLoad = __DIR__ . '/../../includes/sections/system/status-screen.php';
        $showInterface = false;
        break;
    case Gatekeeper::REDIRECT:
        header("Location: " . $basePath . $decision['target']); exit;
    case Gatekeeper::SHOW_LOCK:
        $fileToLoad = __DIR__ . '/../../includes/sections/system/security-lock.php';
        $showInterface = true; 
        break;
    case Gatekeeper::SHOW_404:
        $routesMap = require __DIR__ . '/../../config/routes.php';
        $fileToLoad = $routesMap['404'];
        break;
    case Gatekeeper::ALLOW:
        $routesMap = require __DIR__ . '/../../config/routes.php';
        $fileToLoad = $routesMap[$currentSection] ?? $routesMap['404'];
        if (!file_exists($fileToLoad)) $fileToLoad = $routesMap['404'];
        
        $noInterfaceRoutes = array_merge($securityRules['auth_routes'], ['account-status']);
        $showInterface = !in_array($currentSection, $noInterfaceRoutes);
        break;
}

// Variables JS y Assets
$jsUserPrefs = json_encode($_SESSION['preferences'] ?? new stdClass());
$jsTranslations = json_encode($i18n->getAll());
$globalAvatarSrc = Utils::getGlobalAvatarSrc();
$turnstileSiteKey = $_ENV['TURNSTILE_SITE_KEY'] ?? '';
$userLang = $_SESSION['preferences']['language'] ?? 'es-latam';
?>