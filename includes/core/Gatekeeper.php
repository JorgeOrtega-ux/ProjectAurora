<?php
// includes/core/Gatekeeper.php
namespace Aurora\Core;

use Aurora\Libs\Utils;
use PDO;
class Gatekeeper {
    // Definimos los resultados posibles de una verificación
    const ALLOW = 'ALLOW';
    const REDIRECT = 'REDIRECT';
    const SHOW_LOCK = 'SHOW_LOCK';      // Para forzar configuración de 2FA si fuera necesario
    const SHOW_MAINTENANCE = 'SHOW_MAINTENANCE';
    const SHOW_404 = 'SHOW_404';

    /**
     * Verifica si el usuario actual puede acceder a la sección solicitada.
     * @param string $section La sección solicitada (ej. 'admin/dashboard')
     * @param PDO $pdo Conexión a base de datos para configs
     * @return array ['action' => CONSTANTE, 'target' => string (opcional)]
     */
    public static function check($section, $pdo) {
        // 1. Cargar Configuración y Reglas
        $securityRules = require __DIR__ . '/../../config/security.php';
        
        $authRoutes = $securityRules['auth_routes'];
        $protectedRoutes = $securityRules['protected_routes'];
        $rolesConfig = $securityRules['roles']; // Asegúrate de que config/security.php tenga esto definido
        
        // Datos del Entorno
        $maintenanceMode = Utils::getServerConfig($pdo, 'maintenance_mode', '0');
        $userRole = $_SESSION['role'] ?? 'guest';
        $userId = $_SESSION['user_id'] ?? null;
        $isLoggedIn = !empty($userId);
        
        $isAdminRoute = strpos($section, 'admin/') === 0;

        // 2. Lógica de Mantenimiento
        // Si está activo, no es rol de sistema (bypass), no es ruta de auth y no es estado de cuenta -> MANTENIMIENTO
        if ($maintenanceMode === '1' && 
            !in_array($userRole, $rolesConfig['maintenance_bypass']) && 
            !in_array($section, $authRoutes) && 
            $section !== 'account-status') {
            return ['action' => self::SHOW_MAINTENANCE];
        }

        // 3. Gestión de Redirecciones (Login / Logout)
        
        // CASO A: Usuario NO logueado intenta entrar a zona protegida o admin -> LOGIN
        if (($isAdminRoute || in_array($section, $protectedRoutes)) && !$isLoggedIn) {
            return ['action' => self::REDIRECT, 'target' => 'login'];
        }
        
        // CASO B: Usuario SI logueado intenta entrar a login/registro -> HOME
        // EXCEPCIÓN CRÍTICA: Permitir acceso a la pantalla de introducir código 2FA si aún no está verificado
        $isVerificationRoute = ($section === 'login/verification-aditional');
        $needsVerification = $isLoggedIn && empty($_SESSION['is_2fa_verified']) && !empty($_SESSION['two_factor_enabled']);

        if ($isLoggedIn && in_array($section, $authRoutes)) {
            // Si intenta entrar a login/register normal -> HOME
            // Pero si es la ruta de verificación y la necesita -> PERMITIR (no redirigir a home)
            if (!$isVerificationRoute || ($isVerificationRoute && !$needsVerification)) {
                 return ['action' => self::REDIRECT, 'target' => ''];
            }
        }

        // 4. Protección de Admin (Roles y Seguridad) - UNIFICADA
        if ($isAdminRoute) {
            // Obtenemos los roles que tienen acceso al panel desde config/security.php
            $allowedRoles = $rolesConfig['admin_access'];
            
            // Usamos el sistema centralizado de Utils
            // Exigimos 2FA (true) para cualquier acceso admin
            $privCheck = Utils::checkUserPrivileges($pdo, $userId, $allowedRoles, true);

            if (!$privCheck['allowed']) {
                // Decidimos la acción basada en la razón del fallo
                switch ($privCheck['reason']) {
                    case 'role_mismatch':
                    case 'user_not_found':
                    case 'no_session':
                        // Si no tiene el rol adecuado, simulamos que la página no existe (seguridad por oscuridad)
                        return ['action' => self::SHOW_404];
                    
                    case '2fa_not_enabled':
                        // Si es admin pero no tiene 2FA configurado, mostramos bloqueo
                        return ['action' => self::SHOW_LOCK];
                    
                    case '2fa_not_verified':
                        // Si tiene 2FA pero no ha verificado la sesión, redirigimos al input de código
                        return ['action' => self::REDIRECT, 'target' => 'login/verification-aditional'];
                        
                    default:
                        return ['action' => self::SHOW_404];
                }
            }
        }

        // 5. Si pasa todo -> PERMITIR
        return ['action' => self::ALLOW];
    }
}
?>