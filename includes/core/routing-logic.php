<?php
// includes/core/routing-logic.php

// Variables globales requeridas: $pdo, $currentSection, $basePath, $i18n

// Carga de reglas de seguridad
$securityRules = require __DIR__ . '/../../config/security.php';
$authRoutes = $securityRules['auth_routes'];
$protectedRoutes = $securityRules['protected_routes'];

// Configuración de mantenimiento
$maintenanceMode = Utils::getServerConfig($pdo, 'maintenance_mode', '0');
$userRole = $_SESSION['role'] ?? 'guest';
$allowedSystemRoles = ['founder', 'administrator', 'moderator'];

$isAdminRoute = strpos($currentSection, 'admin/') === 0;
$isLoggedIn = isset($_SESSION['user_id']);

$showMaintenanceScreen = (
    $maintenanceMode === '1' &&
    !in_array($userRole, $allowedSystemRoles) &&
    !in_array($currentSection, $authRoutes) &&
    $currentSection !== 'account-status'
);

// REFRESCAR DATOS DE SESIÓN Y VERIFICAR ESTADO DE CUENTA
if ($isLoggedIn && !$showMaintenanceScreen) {
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

        // Obtener datos frescos
        $stmt = $pdo->prepare("SELECT role, avatar_path, username, email, two_factor_enabled, account_status, suspension_ends_at, status_reason FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $freshUser = $stmt->fetch();

        if ($freshUser) {
            // Lógica de Bloqueo (Deleted / Suspended)
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

            // Actualizar sesión
            $_SESSION['role'] = $freshUser['role'];
            $_SESSION['avatar'] = $freshUser['avatar_path'];
            $_SESSION['username'] = $freshUser['username'];
            $_SESSION['email'] = $freshUser['email'];
            $_SESSION['two_factor_enabled'] = $freshUser['two_factor_enabled'];

            $userRole = $freshUser['role'];

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
                    // Nota: $i18n debe ser pasado por referencia o estar en scope global si se reasigna
                    // Como estamos haciendo include en index.php, la variable $i18n se sobrescribirá.
                    $i18n = new I18n($freshPrefs['language']);
                }
            }
        } else {
            session_unset();
            session_destroy();
            header("Location: " . $basePath . "login");
            exit;
        }
    } catch (Exception $e) {
        error_log("Error sesión: " . $e->getMessage());
    }
}

// GESTIÓN DE REDIRECCIONES (Protección de rutas)
if (!$showMaintenanceScreen) {
    if (($isAdminRoute || in_array($currentSection, $protectedRoutes)) && !$isLoggedIn) {
        header("Location: " . $basePath . "login");
        exit;
    }
    if ($isLoggedIn && in_array($currentSection, $authRoutes)) {
        header("Location: " . $basePath);
        exit;
    }
}

// DETERMINAR ARCHIVO A CARGAR ($fileToLoad)
if ($showMaintenanceScreen) {
    $fileToLoad = __DIR__ . '/../../includes/sections/system/status-screen.php';
    $isMaintenanceContext = true;
    $showInterface = false;
} else {
    $routesMap = require __DIR__ . '/../../config/routes.php';

    // Protección extra para Admin
    $allowedAdminRoles = ['founder', 'administrator'];
    if (strpos($currentSection, 'admin/') === 0 && !in_array($userRole, $allowedAdminRoles)) {
        $fileToLoad = $routesMap['404'];
    } else {
        $fileToLoad = $routesMap[$currentSection] ?? $routesMap['404'];
    }

    $noInterfaceRoutes = array_merge($authRoutes, ['account-status']);
    $showInterface = !in_array($currentSection, $noInterfaceRoutes);
}

// Variables JS globales
$jsUserPrefs = json_encode($_SESSION['preferences'] ?? new stdClass());
$jsTranslations = json_encode($i18n->getAll());
$globalAvatarSrc = Utils::getGlobalAvatarSrc();
$turnstileSiteKey = $_ENV['TURNSTILE_SITE_KEY'] ?? '';
$userLang = $_SESSION['preferences']['language'] ?? 'es-latam';