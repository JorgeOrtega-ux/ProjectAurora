// assets/js/app-init.js

// [CORE]
import { initUrlManager } from './core/url-manager.js';
import { initI18n, translateDocument } from './core/i18n-manager.js';
import { initThemeManager } from './core/theme-manager.js'; 

// [MODULES]
import { initAuthManager } from './modules/auth-manager.js';
import { initNotificationsManager } from './modules/notifications-manager.js';
import { initFriendsManager } from './modules/friends-manager.js';
import { initSettingsManager } from './modules/settings-manager.js';

// [UI]
import { initMainController } from './ui/main-controller.js';
import { initAlertManager } from './ui/alert-manager.js';
import { initTooltipManager } from './ui/tooltip-manager.js';
import { initDragController } from './ui/drag-controller.js';

// [SERVICES]
import { initSocketService } from './services/socket-service.js';

/**
 * Carga dinámica de módulos basada en la sección actual del DOM.
 * Esto evita cargar JS administrativo para usuarios normales.
 */
export async function handleDynamicImports() {
    const adminUsersSection = document.querySelector('[data-section="admin/users"]');
    const adminDetailsSection = document.querySelector('[data-section^="admin/user-"]'); // user-status, user-manage, etc.

    if (adminUsersSection) {
        const { initAdminUsers } = await import('./modules/admin-users.js');
        initAdminUsers();
    }

    if (adminDetailsSection) {
        const { initAdminUserDetails } = await import('./modules/admin-user-details.js');
        initAdminUserDetails();
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    try {
        // 1. Inicializar i18n y TEMA PRIMERO (Visuales)
        await initI18n();
        initThemeManager(); 

        // Inicializar Core y Auth
        initUrlManager();
        initAuthManager();

        // Inicializar Gestor de Configuración
        window.initSettingsManager = () => {
            initSettingsManager();
            translateDocument();
        };
        window.initSettingsManager();

        // Inicializar UI Base
        initMainController();
        initTooltipManager();

        // Inicializar Gestor de Alertas Global
        initAlertManager();

        // Inicializar Servicios
        initSocketService();
        initNotificationsManager();
        initFriendsManager();

        // Inicializar Controladores UI avanzados
        initDragController();

        // [NUEVO] Carga perezosa de módulos administrativos
        // Exponemos la función para que url-manager.js pueda llamarla al navegar
        window.loadDynamicModules = handleDynamicImports;
        await handleDynamicImports();

    } catch (error) {
        console.error('Error crítico al inicializar la aplicación:', error);
    }
});