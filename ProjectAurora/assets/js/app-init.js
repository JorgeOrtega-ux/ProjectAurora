// assets/js/app-init.js

import { initUrlManager } from './url-manager.js';
import { initAuthManager } from './auth-manager.js';
import { initMainController } from './main-controller.js';
import { AlertManager } from './alert-manager.js'; // <-- AÑADIDO

document.addEventListener('DOMContentLoaded', () => {
    try {
        console.log('Project Aurora: Iniciando módulos...');
        
        initUrlManager();
        initAuthManager();
        initMainController();
        
        // --- INICIALIZACIÓN DEL SISTEMA DE ALERTAS ---
        // Lo adjuntamos a 'window' para acceso global fácil desde otros scripts
        // (como lo describiste en tu prompt)
        window.alertManager = new AlertManager();
        console.log('Project Aurora: Alert Manager inicializado.');
        // --- FIN DE LA INICIALIZACIÓN ---

        console.log('Project Aurora: Módulos cargados correctamente.');
    } catch (error) {
        console.error('Error crítico al inicializar la aplicación:', error);
    }
});