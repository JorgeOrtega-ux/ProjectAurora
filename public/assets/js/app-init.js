/**
 * app-init.js
 * Punto de entrada de la aplicación.
 */

import { initMainController } from './main-controller.js';
import { initUrlManager } from './core/url-manager.js';
import { initAuthController } from './auth-controller.js'; 
import { Toast } from './core/toast-manager.js'; 
import { TooltipManager } from './core/tooltip-manager.js';
import { Dialog } from './core/dialog-manager.js'; // <--- IMPORTADO

// Módulos de configuración (Granulares)
import { SettingsController } from './modules/settings/settings-controller.js'; 
import { ProfileController } from './modules/settings/profile-controller.js'; 
import { DevicesController } from './modules/settings/devices-controller.js';
import { DeleteAccountController } from './modules/settings/delete-account-controller.js';
import { TwoFactorController } from './modules/settings/2fa-controller.js';
import { SecurityController } from './modules/settings/security-controller.js';

const App = {
    init: () => {
        console.log('App: Inicializando...');
        
        // APLICAR TEMA INICIAL
        if (window.USER_PREFS && window.USER_PREFS.theme) {
            SettingsController.applyTheme(window.USER_PREFS.theme);
        }
        
        Toast.init();
        TooltipManager.init();
        Dialog.init(); // <--- INICIALIZADO
        
        initMainController();
        initAuthController();
        SettingsController.init();
        
        if (document.querySelector('.general-content-scrolleable')) {
             initUrlManager();
        }

        const path = window.location.pathname.replace(window.BASE_PATH, '').replace(/^\/+|\/+$/g, '');
        const initialSection = path || 'main';
        routeDispatcher(initialSection);

        document.addEventListener('spa:view_loaded', (e) => {
            const section = e.detail.section;
            routeDispatcher(section);
        });
    }
};

function routeDispatcher(section) {
    console.log(`Router Dispatch: Inicializando controladores para [${section}]`);

    switch (section) {
            
        case 'settings/your-profile':
            ProfileController.init();
            SettingsController.sync();
            break;

        case 'settings/accessibility':
            SettingsController.sync();
            break;
            
        case 'settings/login-security':
            SecurityController.init();
            break;

        case 'settings/devices':
            DevicesController.init();
            break;
            
        case 'settings/delete-account': 
            DeleteAccountController.init();
            break;

        case 'settings/2fa-setup': 
            TwoFactorController.init();
            break;
            
        default:
            break;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});