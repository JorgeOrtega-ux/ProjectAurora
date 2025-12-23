import { initMainController } from './main-controller.js';
import { initUrlManager } from './core/url-manager.js';
import { initAuthController } from './auth-controller.js'; 
import { Toast } from './core/toast-manager.js'; // Importar Toast

const App = {
    init: () => {
        console.log('App: Inicializando...');
        
        // 0. Inicializar Toasts (Crear contenedor)
        Toast.init();

        // 1. UI General (Menús, etc)
        initMainController();
        
        // 2. Auth Controller (Login/Registro/Logout)
        initAuthController();
        
        // 3. Sistema de Rutas SPA
        if (document.querySelector('.general-content-scrolleable')) {
             initUrlManager();
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});