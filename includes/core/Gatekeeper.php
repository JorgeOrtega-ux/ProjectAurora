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

        // 4. Protección de Admin (Roles y Seguridad)
        if ($isAdminRoute) {
            // A. Verificar Rol
            // Si no está en 'admin_access' (ej. moderadores), mostramos 404 para ocultar el panel
            if (!in_array($userRole, $rolesConfig['admin_access'])) {
                return ['action' => self::SHOW_404];
            }
            
            // B. Verificar Seguridad 2FA (CRÍTICO)
            
            // Paso 1: ¿Tiene 2FA activado en su cuenta?
            if (empty($_SESSION['two_factor_enabled'])) {
                // Si es admin y no tiene 2FA, lo bloqueamos (o mostramos LOCK)
                return ['action' => self::SHOW_LOCK];
            }

            // Paso 2: ¿Ya validó el código en esta sesión?
            // Si tiene 2FA pero no ha verificado la sesión -> REDIRIGIR A VERIFICACIÓN
            if (empty($_SESSION['is_2fa_verified'])) {
                return ['action' => self::REDIRECT, 'target' => 'login/verification-aditional'];
            }
        }

        // 5. Si pasa todo -> PERMITIR
        return ['action' => self::ALLOW];
    }
}
?>