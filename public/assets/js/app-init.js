/* public/assets/js/app-init.js */

import { prefsManager } from './core/preferences-manager.js';
import { initMainController } from './main-controller.js';
import { initUrlManager } from './core/url-manager.js';
import { initAuthController } from './auth-controller.js';

// Módulos de Settings
import { ProfileController } from './modules/settings/profile-controller.js';
import { SecurityController } from './modules/settings/security-controller.js'; // --- IMPORTAR ---

document.addEventListener('DOMContentLoaded', function() {
    console.log('App: Inicializando...');

    initMainController(prefsManager);
    initUrlManager();
    initAuthController();
    
    // --- CHECK INICIAL DE SECCIÓN ---
    // Chequear si cargamos directamente en una sección específica
    checkCurrentSectionAndInit();
});

// Listener para detectar cambios de página (SPA) y re-inicializar controladores si hace falta
// (Esto se integra con tu UrlManager si emite eventos, o usando MutationObserver en MainController)
// Por simplicidad, agregamos un Observer básico aquí o confiamos en que tu router maneja scripts.
// Pero dado tu código actual, lo ideal es verificar al cargar:

const observer = new MutationObserver(() => {
    checkCurrentSectionAndInit();
});
const appContent = document.getElementById('app-content');
if (appContent) {
    observer.observe(appContent, { childList: true, subtree: false });
}

function checkCurrentSectionAndInit() {
    // Perfil
    if (document.querySelector('[data-section="settings/your-profile"]')) {
        ProfileController.init();
    }
    // Seguridad (NUEVO)
    if (document.querySelector('[data-section="settings/security"]')) {
        SecurityController.init();
    }
}