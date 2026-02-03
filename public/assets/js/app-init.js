/**
 * public/assets/js/app-init.js
 */

import { initMainController } from './main-controller.js';
import { initUrlManager } from './core/url-manager.js';
import { initAuthController } from './auth-controller.js'; 
import { Toast } from './core/toast-manager.js'; 
import { TooltipManager } from './core/tooltip-manager.js';
import { Dialog } from './core/dialog-manager.js';
import { UiManager } from './core/ui-manager.js';
import { SocketClient } from './core/socket-client.js'; 

// Módulos
import { SettingsController } from './modules/settings/settings-controller.js'; 
import { ProfileController } from './modules/settings/profile-controller.js'; 
import { DevicesController } from './modules/settings/devices-controller.js';
import { DeleteAccountController } from './modules/settings/delete-account-controller.js';
import { TwoFactorController } from './modules/settings/2fa-controller.js';
import { SecurityController } from './modules/settings/security-controller.js';
import { UsersController } from './modules/admin/users/users-controller.js';
import { UserDetailsController } from './modules/admin/users/user-details-controller.js';
import { UserRoleController } from './modules/admin/users/user-role-controller.js';
import { UserStatusController } from './modules/admin/users/user-status-controller.js';
import { ServerConfigController } from './modules/admin/server-config-controller.js';
import { BackupsController } from './modules/admin/backups-controller.js';
import { BackupConfigController } from './modules/admin/backup-config-controller.js';
import { AuditLogController } from './modules/admin/audit-log-controller.js';
import { LogFilesController } from './modules/admin/log-files-controller.js';
import { FileViewerController } from './modules/admin/file-viewer-controller.js';
import { RedisManagerController } from './modules/admin/redis-manager-controller.js';
import { DashboardController } from './modules/admin/dashboard-controller.js';

// [NUEVO] Importar el nuevo controlador de alertas del sistema
import { SystemAlertsController } from './modules/admin/system-alerts-controller.js';

const App = {
    init: () => {
        console.log('App: Inicializando...');
        
        if (!window.IS_LOGGED_IN) {
            try {
                const localPrefs = JSON.parse(localStorage.getItem('guest_prefs') || '{}');
                window.USER_PREFS = { ...window.USER_PREFS, ...localPrefs };
            } catch (e) {
                console.error("Error leyendo localStorage", e);
            }
        }

        if (window.USER_PREFS && window.USER_PREFS.theme) {
            SettingsController.applyTheme(window.USER_PREFS.theme);
        }
        
        Toast.init();
        TooltipManager.init();
        Dialog.init();
        UiManager.init();
        
        initMainController();
        initAuthController();
        SettingsController.init();
        
        if (!window.location.pathname.includes('/login')) {
             SocketClient.init();
             initGlobalSocketListeners();
        }
        
        if (document.querySelector('.general-content-scrolleable')) {
             initUrlManager();
        }

        const path = window.location.pathname.replace(window.BASE_PATH, '').replace(/^\/+|\/+$/g, '');
        routeDispatcher(path || 'main');

        document.addEventListener('spa:view_loaded', (e) => {
            routeDispatcher(e.detail.section);
        });
    }
};

function initGlobalSocketListeners() {
    // 1. KICK (Expulsión forzosa - Suspended/Deleted/Kick Manual)
    document.addEventListener('socket:force_logout', (e) => {
        if (window.isManualLogout) return;
        if (window.location.pathname.includes('/login')) return;
        
        Toast.show('Tu sesión ha cambiado de estado.', 'warning', 5000);
        
        // Recargar para que PHP detecte el cambio de estado en BD
        setTimeout(() => { 
            window.location.reload(); 
        }, 1500);
    });

    // 2. MANTENIMIENTO (La corrección está aquí)
    document.addEventListener('socket:maintenance_start', (e) => {
        // [NUEVO] Verificar rol antes de recargar
        const profileBtn = document.querySelector('.header-button.profile-button');
        const currentRole = profileBtn ? profileBtn.dataset.role : 'guest';
        
        // Roles que tienen permiso de "Bypass" al mantenimiento
        const staffRoles = ['founder', 'administrator', 'moderator'];

        if (staffRoles.includes(currentRole)) {
            // Si es Staff, NO recargamos. Solo avisamos.
            Toast.show('El sistema ha entrado en modo mantenimiento (Acceso Staff activo).', 'info', 5000);
            console.log('Maintenance signal received. Ignored due to staff privileges.');
            return;
        }

        // Si es usuario normal o invitado, recargamos con delay para asegurar consistencia
        setTimeout(() => {
            window.location.reload();
        }, 1000); 
    });

    // 3. NOTIFICACIONES GENERALES
    document.addEventListener('socket:notification', (e) => {
        const msgData = e.detail.message; 
        if (msgData && msgData.text) {
            Toast.show(msgData.text, msgData.type || 'info');
        }
    });
}
function routeDispatcher(section) {
    updateSidebarState(section);
    switch (section) {
        // === SETTINGS ===
        case 'settings/your-profile': 
            ProfileController.init(); 
            SettingsController.init(); 
            SettingsController.sync(); 
            break;
            
        case 'settings/accessibility': 
            SettingsController.init(); 
            SettingsController.sync(); 
            break;
            
        case 'settings/preferences': 
            SettingsController.init(); 
            SettingsController.sync(); 
            break;
            
        case 'settings/login-security': SecurityController.init(); break;
        case 'settings/devices': DevicesController.init(); break;
        case 'settings/delete-account': DeleteAccountController.init(); break;
        case 'settings/2fa-setup': TwoFactorController.init(); break;
        
        // === ADMIN MODULES ===
        case 'admin/dashboard': 
            DashboardController.init(); 
            // AlertController ya no se inicializa aquí porque ya no es un modal
            break;

        case 'admin/alerts': 
            SystemAlertsController.init(); // [NUEVO] Inicialización de la nueva sección
            break;

        case 'admin/users': UsersController.init(); break;
        case 'admin/user-details': UserDetailsController.init(); break;
        case 'admin/user-role': UserRoleController.init(); break;
        case 'admin/user-status': UserStatusController.init(); break;
        case 'admin/server': ServerConfigController.init(); break;
        case 'admin/backups': BackupsController.init(); break;
        case 'admin/backups/config': BackupConfigController.init(); break;
        
        case 'admin/audit-log': AuditLogController.init(); break;
        case 'admin/log-files': LogFilesController.init(); break;
        case 'admin/file-viewer': FileViewerController.init(); break;
        case 'admin/redis': RedisManagerController.init(); break;
        
        default: break;
    }
}

function updateSidebarState(section) {
    const sidebar = document.querySelector('.module-surface');
    if (!sidebar) return;
    sidebar.querySelectorAll('.menu-link').forEach(link => link.classList.remove('active'));

    const menus = {
        main: document.getElementById('surface-main'),
        settings: document.getElementById('surface-settings'),
        help: document.getElementById('surface-help'),
        admin: document.getElementById('surface-admin')
    };
    
    Object.values(menus).forEach(el => { if(el) el.style.display = 'none'; });

    if (section.startsWith('settings/')) menus.settings.style.display = 'flex';
    else if (section.startsWith('admin/')) menus.admin.style.display = 'flex';
    else if (section.startsWith('site-policy')) menus.help.style.display = 'flex';
    else if (menus.main) menus.main.style.display = 'flex';

    const activeLink = sidebar.querySelector(`.menu-link[data-nav="${section}"]`);
    if (activeLink) activeLink.classList.add('active');
}

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});