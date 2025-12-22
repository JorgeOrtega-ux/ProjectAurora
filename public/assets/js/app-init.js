import { initMainController } from './main-controller.js';
import { initUrlManager } from './core/url-manager.js'; // Importamos el router

const App = {
    init: () => {
        console.log('App: Inicializando...');
        
        // 1. UI General (Menús, etc)
        initMainController();
        
        // 2. Sistema de Rutas SPA
        initUrlManager();
    }
};

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});