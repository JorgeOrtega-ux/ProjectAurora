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
 * Carga dinámica de módulos.
 */
export async function handleDynamicImports() {
    const adminDashboardSection = document.querySelector('[data-section="admin/dashboard"]');
    const adminUsersSection = document.querySelector('[data-section="admin/users"]');
    const adminDetailsSection = document.querySelector('[data-section^="admin/user-"]'); 
    const adminServerSection = document.querySelector('[data-section="admin/server"]');
    const adminBackupsSection = document.querySelector('[data-section="admin/backups"]');

    // [NUEVO: Módulo Dashboard]
    if (adminDashboardSection) {
        const { initAdminDashboard } = await import('./modules/admin-dashboard.js');
        initAdminDashboard();
    }

    if (adminUsersSection) {
        const { initAdminUsers } = await import('./modules/admin-users.js');
        initAdminUsers();
    }

    if (adminDetailsSection) {
        const { initAdminUserDetails } = await import('./modules/admin-user-details.js');
        initAdminUserDetails();
    }

    if (adminServerSection) {
        const { initAdminServer } = await import('./modules/admin-server.js');
        initAdminServer();
    }

    if (adminBackupsSection) {
        const { initAdminBackups } = await import('./modules/admin-backups.js');
        initAdminBackups();
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    try {
        await initI18n();
        initThemeManager(); 
        initUrlManager();
        initAuthManager();

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

        window.loadDynamicModules = handleDynamicImports;
        await handleDynamicImports();

    } catch (error) {
        console.error('Error crítico al inicializar la aplicación:', error);
    }
});