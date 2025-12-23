import { initMainController } from './main-controller.js';
import { initUrlManager } from './core/url-manager.js';
import { initAuthController } from './auth-controller.js'; 
import { Toast } from './core/toast-manager.js'; 

// Importar controladores de Settings
import { SettingsController } from './modules/settings/settings-controller.js'; 
import { ProfileController } from './modules/settings/profile-controller.js'; // <--- NUEVO

const App = {
    init: () => {
        console.log('App: Inicializando...');
        
        Toast.init();
        initMainController();
        initAuthController();
        
        // Inicializar lógica general de Settings (Dropdowns, Toggles, Edición de texto)
        SettingsController.init();
        
        // Inicializar lógica específica de Foto de Perfil
        // Lo ponemos dentro de un try/check o el propio controller verifica si existen los elementos
        // Dado que es SPA, quizás queramos llamarlo cada vez que carga contenido, 
        // pero por ahora el controller usa delegación o busca elementos al inicio si la página refrescó en /profile
        ProfileController.init();

        // Sistema de Rutas SPA
        if (document.querySelector('.general-content-scrolleable')) {
             initUrlManager();
        }

        // Evento SPA: Cuando se carga nuevo contenido (ej: navegar a settings/your-profile)
        // necesitamos reinicializar el ProfileController para que encuentre los nuevos elementos del DOM
        /* NOTA: Necesitas agregar un disparador de eventos en tu url-manager.js 
           después de 'container.innerHTML = html'.
           Ejemplo: document.dispatchEvent(new Event('aurora:content-loaded'));
        */
       
       // Observer simple para SPA (Alternativa rápida si no modificas url-manager):
       const observer = new MutationObserver(() => {
           // Si aparece el input de avatar, reinicializar controller
           if(document.getElementById('upload-avatar')) {
               ProfileController.init();
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