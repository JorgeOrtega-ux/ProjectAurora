// assets/js/app-init.js

import { initUrlManager } from './url-manager.js';
import { initAuthManager } from './auth-manager.js';
import { initMainController } from './main-controller.js';
import { AlertManager } from './alert-manager.js';
import { initTooltipManager } from './tooltip-manager.js'; // <--- 1. IMPORTAR

document.addEventListener('DOMContentLoaded', () => {
    try {
        console.log('Project Aurora: Iniciando módulos...');
        
        initUrlManager();
        initAuthManager();
        initMainController();
        
        // --- INICIALIZACIÓN DE TOOLTIPS ---
        initTooltipManager(); // <--- 2. INICIALIZAR
        // ----------------------------------

        // --- INICIALIZACIÓN DEL SISTEMA DE ALERTAS ---
        window.alertManager = new AlertManager();
        console.log('Project Aurora: Alert Manager inicializado.');

        console.log('Project Aurora: Módulos cargados correctamente.');
    } catch (error) {
        console.error('Error crítico al inicializar la aplicación:', error);
    }
});