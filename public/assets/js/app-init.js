import { initMainController } from './main-controller.js';
import { initUrlManager } from './core/url-manager.js';
import { initAuthController } from './auth-controller.js'; 
import { Toast } from './core/toast-manager.js'; 

// Importar controladores de Settings
import { SettingsController } from './modules/settings/settings-controller.js'; 
import { ProfileController } from './modules/settings/profile-controller.js'; 
import { DevicesController } from './modules/settings/devices-controller.js'; // <--- NUEVO

const App = {
    init: () => {
        console.log('App: Inicializando...');
        
        Toast.init();
        initMainController();
        initAuthController();
        
        // Inicializar lógica general de Settings (Dropdowns, Toggles, Edición de texto)
        SettingsController.init();
        
        // Inicializadores específicos (para primera carga si entramos directo por URL)
        ProfileController.init();
        DevicesController.init(); // <--- NUEVO

        // Sistema de Rutas SPA
        if (document.querySelector('.general-content-scrolleable')) {
             initUrlManager();
        }

        // Observer para SPA: Detectar cambios de sección y re-inicializar controladores específicos
        const observer = new MutationObserver(() => {
           // Si aparece el input de avatar, reinicializar controller perfil
           if(document.getElementById('upload-avatar')) {
               ProfileController.init();
           }
           // Si aparece el contenedor de dispositivos, inicializar controller devices <--- NUEVO
           if(document.getElementById('devices-list-container')) {
               DevicesController.init();
           }
        });

        const scrollContainer = document.querySelector('.general-content-scrolleable');
        if(scrollContainer) {
            observer.observe(scrollContainer, { childList: true, subtree: true });
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});