import { initMainController } from './main-controller.js';

const App = {
    init: () => {
        // Inicializar controladores
        initMainController();
        
        console.log('App: Inicializada completamente.');
    }
};

// Ejecutar cuando el DOM esté cargado
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});