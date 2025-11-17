import { initUrlManager } from './url-manager.js';
import { initAuthManager } from './auth-manager.js';
import { initMainController } from './main-controller.js';

document.addEventListener('DOMContentLoaded', () => {
    try {
        console.log('Project Aurora: Iniciando módulos...');
        
        initUrlManager();
        initAuthManager();
        initMainController();
        
        console.log('Project Aurora: Módulos cargados correctamente.');
    } catch (error) {
        console.error('Error crítico al inicializar la aplicación:', error);
    }
});