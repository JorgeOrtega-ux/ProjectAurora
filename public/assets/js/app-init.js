import { initMainController } from './main-controller.js';
import { initUrlManager } from './core/url-manager.js';
import { initAuthController } from './auth-controller.js'; // Importar nuevo controller

const App = {
    init: () => {
        console.log('App: Inicializando...');
        
        // 1. UI General (Menús, etc)
        initMainController();
        
        // 2. Auth Controller (Login/Registro/Logout)
        initAuthController();
        
        // 3. Sistema de Rutas SPA (Solo si estamos logueados y no en paginas de auth)
        // Comprobamos si existe el contenedor SPA para evitar errores en login page
        if (document.querySelector('.general-content-scrolleable')) {
             initUrlManager();
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});