/* public/assets/js/app-init.js */

import { prefsManager } from './core/preferences-manager.js';
import { initMainController } from './main-controller.js';
import { initUrlManager } from './core/url-manager.js';

// NUEVO: Importar controlador de Auth
import { initAuthController } from './auth-controller.js';

document.addEventListener('DOMContentLoaded', function() {
    console.log('App: Inicializando...');

    initMainController(prefsManager);
    initUrlManager();
    
    // Iniciar Auth Controller
    initAuthController();
});