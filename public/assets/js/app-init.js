import { initMainController } from './main-controller.js';
import { initUrlManager } from './url-manager.js'; 
import { initAuthController } from './auth-controller.js';
import { initProfileController } from './profile-controller.js'; // Importar nuevo controlador

const App = {
    init: () => {
        // Inicializar UI (Menús, buscador)
        initMainController();
        
        // Inicializar Lógica de Autenticación (Login, Registro)
        initAuthController();
        
        // Inicializar Lógica de Perfil (Edición de datos)
        initProfileController();
        
        // Inicializar SPA Router
        initUrlManager();
        
        console.log('App: Inicializada completamente.');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});