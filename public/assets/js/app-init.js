/**
 * app-init.js
 * Punto de entrada de la aplicación.
 */

import { initMainController } from './main-controller.js';
import { initUrlManager } from './core/url-manager.js';
import { initAuthController } from './auth-controller.js'; 
import { Toast } from './core/toast-manager.js'; 
import { TooltipManager } from './core/tooltip-manager.js'; // Importar Tooltips

import { SettingsController } from './modules/settings/settings-controller.js'; 
import { ProfileController } from './modules/settings/profile-controller.js'; 
import { DevicesController } from './modules/settings/devices-controller.js';
import { DeleteAccountController } from './modules/settings/delete-account-controller.js'; 

const App = {
    init: () => {
        console.log('App: Inicializando...');
        
        // APLICAR TEMA INICIAL
        if (window.USER_PREFS && window.USER_PREFS.theme) {
            SettingsController.applyTheme(window.USER_PREFS.theme);
        }
        
        Toast.init();
        TooltipManager.init(); // Inicializar Tooltips
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
            break;
            
        case 'settings/devices':
            DevicesController.init();
            break;
            
        case 'settings/delete-account': 
            DeleteAccountController.init();
            break;
            
        // El caso para accessibility se maneja globalmente en settings-controller 
        // porque los eventos de dropdown están delegados en el documento.
            
        default:
            break;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});