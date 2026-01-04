/**
 * app-init.js
 * Punto de entrada de la aplicación.
 */

import { initMainController } from './main-controller.js';
import { initUrlManager } from './core/url-manager.js';
import { initAuthController } from './auth-controller.js'; 
import { Toast } from './core/toast-manager.js'; 
import { TooltipManager } from './core/tooltip-manager.js';
import { Dialog } from './core/dialog-manager.js';
// [NUEVO] Importar el gestor de WebSockets
import { WebSocketManager } from './core/websocket-manager.js';

// Módulos de configuración (Granulares)
import { SettingsController } from './modules/settings/settings-controller.js'; 
import { ProfileController } from './modules/settings/profile-controller.js'; 
import { DevicesController } from './modules/settings/devices-controller.js';
import { DeleteAccountController } from './modules/settings/delete-account-controller.js';
import { TwoFactorController } from './modules/settings/2fa-controller.js';
import { SecurityController } from './modules/settings/security-controller.js';

// Módulo Whiteboard (Pizarra)
import { WhiteboardController } from './modules/app/whiteboard-controller.js';

const App = {
    init: () => {
        console.log('App: Inicializando...');
        
        // Exponer WhiteboardController globalmente para acceso en SPA si fuera necesario
        window.WhiteboardController = WhiteboardController;

        // APLICAR TEMA INICIAL
        if (window.USER_PREFS && window.USER_PREFS.theme) {
            SettingsController.applyTheme(window.USER_PREFS.theme);
        }
        
        Toast.init();
        TooltipManager.init();
        Dialog.init();
        
        // [NUEVO] Inicializar conexión WebSocket
        WebSocketManager.init();
        
        initMainController();
        initAuthController();
        SettingsController.init();
        
        if (document.querySelector('.general-content-scrolleable')) {
             initUrlManager();
        }

        const path = window.location.pathname.replace(window.BASE_PATH, '').replace(/^\/+|\/+$/g, '');
        const initialSection = path || 'main';
        
        // Manejar carga inicial
        setTimeout(() => {
            routeDispatcher(initialSection);
        }, 50);

        // Escuchar cambios de navegación SPA
        document.addEventListener('spa:view_loaded', (e) => {
            const section = e.detail.section;
            routeDispatcher(section);
        });
    }
};

function routeDispatcher(section) {
    console.log(`Router Dispatch: Inicializando controladores para [${section}]`);

    // --- MEJORA PARA RUTAS DINÁMICAS ---
    // Si la ruta empieza con 'whiteboard/', inicializamos el controlador del pizarrón
    if (section.startsWith('whiteboard/')) {
        WhiteboardController.init();
        return; // Importante para que no entre al switch
    }

    switch (section) {
        
        // --- SECCIÓN SETTINGS ---
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