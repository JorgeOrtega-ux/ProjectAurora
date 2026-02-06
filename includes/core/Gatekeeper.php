<?php
// includes/core/Gatekeeper.php

class Gatekeeper {
    // Definimos los resultados posibles de una verificación
    const ALLOW = 'ALLOW';
    const REDIRECT = 'REDIRECT';
    const SHOW_LOCK = 'SHOW_LOCK';
    const SHOW_MAINTENANCE = 'SHOW_MAINTENANCE';
    const SHOW_404 = 'SHOW_404';

    /**
     * Verifica si el usuario actual puede acceder a la sección solicitada.
     * * @param string $section La sección solicitada (ej. 'admin/dashboard')
     * @param PDO $pdo Conexión a base de datos para configs
     * @return array ['action' => CONSTANTE, 'target' => string (opcional)]
     */
    public static function check($section, $pdo) {
        // 1. Cargar Configuración y Reglas de Rutas
        // Ajustamos la ruta relativa asumiendo que este archivo está en includes/core/
        $securityRules = require __DIR__ . '/../../config/security.php';
        $authRoutes = $securityRules['auth_routes'];
        $protectedRoutes = $securityRules['protected_routes'];
        
        // Datos del Entorno
        $maintenanceMode = Utils::getServerConfig($pdo, 'maintenance_mode', '0');
        $userRole = $_SESSION['role'] ?? 'guest';
        $userId = $_SESSION['user_id'] ?? null;
        $isLoggedIn = !empty($userId);
        
        $isAdminRoute = strpos($section, 'admin/') === 0;
        $allowedSystemRoles = ['founder', 'administrator', 'moderator'];
        $allowedAdminRoles = ['founder', 'administrator'];

        // 2. Lógica de Mantenimiento
        // Si está activo, no es admin, no es ruta de auth y no es estado de cuenta -> MANTENIMIENTO
        if ($maintenanceMode === '1' && !in_array($userRole, $allowedSystemRoles) && !in_array($section, $authRoutes) && $section !== 'account-status') {
            return ['action' => self::SHOW_MAINTENANCE];
        }

        // 3. Gestión de Redirecciones (Login / Logout)
        
        // CASO A: Usuario NO logueado intenta entrar a zona protegida o admin -> LOGIN
        if (($isAdminRoute || in_array($section, $protectedRoutes)) && !$isLoggedIn) {
            return ['action' => self::REDIRECT, 'target' => 'login'];
        }
        
        // CASO B: Usuario SI logueado intenta entrar a login/registro -> HOME
        if ($isLoggedIn && in_array($section, $authRoutes)) {
            return ['action' => self::REDIRECT, 'target' => ''];
        }

        // 4. Protección de Admin (Roles y Seguridad)
        if ($isAdminRoute) {
            // Verificar Rol
            if (!in_array($userRole, $allowedAdminRoles)) {
                return ['action' => self::SHOW_404];
            }
            
            // Verificar 2FA (Seguridad Crítica)
            if (empty($_SESSION['two_factor_enabled'])) {
                return ['action' => self::SHOW_LOCK];
            }
        }

        // 5. Si pasa todo -> PERMITIR
        return ['action' => self::ALLOW];
    }
}
?>