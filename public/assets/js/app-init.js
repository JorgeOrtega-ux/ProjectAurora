/* public/assets/js/app-init.js */

import { AuditLogController } from './modules/admin/audit-log-controller.js';
import { BackupConfigController } from './modules/admin/backup-config-controller.js';
import { BackupsController } from './modules/admin/backups-controller.js';
import { DashboardController } from './modules/admin/dashboard-controller.js';
import { DeleteAccountController } from './modules/settings/delete-account-controller.js';
import { DevicesController } from './modules/settings/devices-controller.js';
import { DialogManager } from './core/components/dialog-manager.js';
import { FileViewerController } from './modules/admin/file-viewer-controller.js';
import { LogFilesController } from './modules/admin/log-files-controller.js';
import { ProfileController } from './modules/settings/profile-controller.js';
import { RedisManagerController } from './modules/admin/redis-manager-controller.js';
import { SecurityController } from './modules/settings/security-controller.js';
import { ServerConfigController } from './modules/admin/server-config-controller.js';
import { SettingsController } from './modules/settings/settings-controller.js';
import { SocketClient } from './core/services/socket-client.js';
import { SystemAlertsController } from './modules/admin/system-alerts-controller.js';
import { ToastManager } from './core/components/toast-manager.js';
import { TooltipManager } from './core/components/tooltip-manager.js';
import { TwoFactorController } from './modules/settings/2fa-controller.js';
import { UiManager } from './core/components/ui-manager.js';
import { UserDetailsController } from './modules/admin/users/user-details-controller.js';
import { UserRoleController } from './modules/admin/users/user-role-controller.js';
import { UserStatusController } from './modules/admin/users/user-status-controller.js';
import { UsersController } from './modules/admin/users/users-controller.js';
import { initAuthController } from './auth-controller.js';
import { initMainController } from './main-controller.js';
import { initUrlManager } from './core/utils/url-manager.js';
import { UploadController } from './modules/studio/upload-controller.js';
import { ContentController } from './modules/studio/content-controller.js';
import { HomeController } from './modules/app/home-controller.js';
import { WatchController } from './modules/app/watch-controller.js';
// [NUEVO] Importamos el controlador del perfil
import { ChannelProfileController } from './modules/app/channel-profile-controller.js';

const App = {
    init: () => {
        if (!window.IS_LOGGED_IN) {
            try {
                const localPrefs = JSON.parse(localStorage.getItem('guest_prefs') || '{}');
                window.USER_PREFS = { ...window.USER_PREFS, ...localPrefs };
            } catch (e) {}
        }

        if (window.USER_PREFS && window.USER_PREFS.theme) {
            SettingsController.applyTheme(window.USER_PREFS.theme);
        }
        
        ToastManager.init();
        TooltipManager.init();
        DialogManager.init();
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

        // --- INICIO CORRECCIÓN ---
        // Obtenemos la ruta relativa limpia
        let path = window.location.pathname.replace(window.BASE_PATH, '').replace(/^\/+|\/+$/g, '');
        
        // Si la ruta empieza con 's/' (ej: s/channel/...), lo quitamos para que coincida con los casos del switch
        if (path.startsWith('s/')) {
            path = path.substring(2); 
        }
        // --- FIN CORRECCIÓN ---

        routeDispatcher(path || 'main');

        document.addEventListener('spa:view_loaded', (e) => {
            // Aplicamos la misma limpieza por si acaso el evento trae la ruta cruda
            let section = e.detail.section;
            if (section.startsWith('s/')) {
                section = section.substring(2);
            }
            routeDispatcher(section);
        });
    }
};

function initGlobalSocketListeners() {
    document.addEventListener('socket:force_logout', (e) => {
        if (window.isManualLogout) return;
        if (window.location.pathname.includes('/login')) return;
        
        ToastManager.show('Tu sesión ha cambiado de estado.', 'warning', 5000);
        setTimeout(() => { window.location.reload(); }, 1500);
    });

    document.addEventListener('socket:maintenance_start', (e) => {
        const profileBtn = document.querySelector('.header-button.profile-button');
        const currentRole = profileBtn ? profileBtn.dataset.role : 'guest';
        const staffRoles = ['founder', 'administrator', 'moderator'];

        if (staffRoles.includes(currentRole)) {
            ToastManager.show('El sistema ha entrado en modo mantenimiento (Acceso Staff activo).', 'info', 5000);
            return;
        }
        setTimeout(() => { window.location.reload(); }, 1000); 
    });

    document.addEventListener('socket:notification', (e) => {
        const msgData = e.detail.message; 
        if (msgData && msgData.text) {
            ToastManager.show(msgData.text, msgData.type || 'info');
        }
    });
}

function routeDispatcher(section) {
    updateSidebarState(section);
    
    // Rutas de Studio: Upload
    if (section.startsWith('channel/upload')) {
        UploadController.init();
        return;
    }
    
    // Rutas de Studio: Contenido
    if (section.startsWith('channel/my-content')) {
        ContentController.init();
        return;
    }

    // Ruta de Watch
    if (section === 'watch') {
        WatchController.init();
        return;
    }

    // Detector de Rutas de Canal (empiezan con c/...) o router interno channel-profile
    if (section.startsWith('c/') || section === 'channel-profile') {
        ChannelProfileController.init();
        return;
    }

    switch (section) {
        case 'main':
            HomeController.init(); 
            break;

        case 'settings/your-profile': 
            ProfileController.init(); 
            SettingsController.init(); 
            SettingsController.sync(); 
            break;
            
        case 'settings/accessibility': 
        case 'settings/preferences': 
            SettingsController.init(); 
            SettingsController.sync(); 
            break;
            
        case 'settings/login-security': SecurityController.init(); break;
        case 'settings/devices': DevicesController.init(); break;
        case 'settings/delete-account': DeleteAccountController.init(); break;
        case 'settings/2fa-setup': TwoFactorController.init(); break;
        
        case 'admin/dashboard': DashboardController.init(); break;
        case 'admin/alerts': SystemAlertsController.init(); break;
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

    // Para canales, a veces queremos mantener activo "Home" o nada
    // Si NO es ruta de canal, intentamos marcar el link activo
    if (!section.startsWith('c/')) {
        const activeLink = sidebar.querySelector(`.menu-link[data-nav="${section}"]`);
        if (activeLink) activeLink.classList.add('active');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});