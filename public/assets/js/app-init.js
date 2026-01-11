/* public/assets/js/app-init.js */

import { prefsManager } from './core/preferences-manager.js';
import { initMainController } from './main-controller.js';
import { initUrlManager } from './core/url-manager.js';

// NUEVO: Importar controlador de Auth
import { initAuthController } from './auth-controller.js';
import { ProfileController } from './modules/settings/profile-controller.js';

// Si detectamos que estamos en la página de perfil (puedes usar tu URLManager o chequear un ID en el DOM)
if (document.querySelector('[data-section="settings/your-profile"]')) {
    ProfileController.init();
}
document.addEventListener('DOMContentLoaded', function() {
    console.log('App: Inicializando...');

    initMainController(prefsManager);
    initUrlManager();
    
    // Iniciar Auth Controller
    initAuthController();
});