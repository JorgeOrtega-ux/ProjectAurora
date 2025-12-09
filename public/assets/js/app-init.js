import { initMainController } from './main-controller.js';
import { initUrlManager } from './url-manager.js'; // Importar router

const App = {
    init: () => {
        // Inicializar UI (Menús, buscador)
        initMainController();
        
        // Inicializar SPA Router
        initUrlManager();
        
        console.log('App: Inicializada completamente.');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});