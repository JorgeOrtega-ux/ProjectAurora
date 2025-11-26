// assets/js/app-init.js

// [CORE]
import { initUrlManager } from './core/url-manager.js';
import { initI18n, translateDocument } from './core/i18n-manager.js';
import { initThemeManager } from './core/theme-manager.js'; 

// [MODULES]
import { initAuthManager } from './modules/auth-manager.js';
import { initNotificationsManager } from './modules/social/notifications-manager.js';
import { initFriendsManager } from './modules/social/friends-manager.js';
import { initSettingsManager } from './modules/settings-manager.js';

// [UI]
import { initMainController } from './ui/main-controller.js';
import { initAlertManager } from './ui/alert-manager.js';
import { initTooltipManager } from './ui/tooltip-manager.js';
import { initDragController } from './ui/drag-controller.js';

// [SERVICES]
import { initSocketService } from './services/socket-service.js';

// [ADMIN MODULES - IMPORTACIÓN ESTÁTICA]
import { initAdminDashboard } from './modules/admin/admin-dashboard.js';
import { initAdminUsers } from './modules/admin/admin-users.js';
import { initAdminUserDetails } from './modules/admin/admin-user-details.js';
import { initAdminServer } from './modules/admin/admin-server.js';
import { initAdminBackups } from './modules/admin/admin-backups.js';

/**
 * Manejador de módulos por sección.
 * Se llama cada vez que url-manager cambia el contenido.
 */
export async function handleModuleLoading() {
    // Detectar qué sección está activa en el DOM
    const adminDashboard = document.querySelector('[data-section="admin/dashboard"]');
    const adminUsers = document.querySelector('[data-section="admin/users"]');
    // Detecta cualquier sub-página de usuario (status, manage, history, role)
    const adminUserDetails = document.querySelector('[data-section^="admin/user-"]'); 
    const adminServer = document.querySelector('[data-section="admin/server"]');
    const adminBackups = document.querySelector('[data-section="admin/backups"]');

    if (adminDashboard) initAdminDashboard();
    if (adminUsers) initAdminUsers();
    if (adminUserDetails) initAdminUserDetails();
    if (adminServer) initAdminServer();
    if (adminBackups) initAdminBackups();
}

document.addEventListener('DOMContentLoaded', async () => {
    try {
        await initI18n();
        initThemeManager(); 
        initUrlManager();
        initAuthManager();

        // Wrapper para settings que incluye traducción
        window.initSettingsManager = () => {
            initSettingsManager();
            translateDocument();
        };
        window.initSettingsManager();

        initMainController();
        initTooltipManager();
        initAlertManager();
        initSocketService();
        initNotificationsManager();
        initFriendsManager();
        initDragController();

        // Exponer la función de carga para url-manager.js
        window.loadDynamicModules = handleModuleLoading;
        
        // Ejecutar carga inicial
        await handleModuleLoading();

    } catch (error) {
        console.error('Error crítico al inicializar la aplicación:', error);
    }
});