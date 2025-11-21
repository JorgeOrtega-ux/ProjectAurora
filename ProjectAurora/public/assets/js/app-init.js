// assets/js/app-init.js

import { initUrlManager } from './url-manager.js';
import { initAuthManager } from './auth-manager.js';
import { initMainController } from './main-controller.js';
import { AlertManager } from './alert-manager.js';
import { initTooltipManager } from './tooltip-manager.js';
import { initDragController } from './drag-controller.js';
import { SocketService } from './socket-service.js';
import { NotificationsManager } from './notifications-manager.js';
import { FriendsManager } from './friends-manager.js';
// [1] IMPORTAR EL NUEVO MANAGER
import { initSettingsManager } from './settings-manager.js'; 

document.addEventListener('DOMContentLoaded', () => {
    try {
        console.log('Project Aurora: Iniciando módulos...');

        initUrlManager();
        initAuthManager();
        initMainController();
        initTooltipManager();

        window.alertManager = new AlertManager();

        window.socketService = new SocketService();
        window.notificationsManager = new NotificationsManager();
        window.friendsManager = new FriendsManager();

        initDragController();
        
        // [2] INICIAR EL GESTOR DE CONFIGURACIÓN
        // Nota: Como la sección de configuración se carga dinámicamente vía AJAX (UrlManager),
        // initSettingsManager debe ser capaz de ejecutarse cuando el contenido cambie.
        // UrlManager.js debería llamar a window.initSettingsManager() si existe después de cargar contenido.
        // O podemos exponerlo globalmente:
        window.initSettingsManager = initSettingsManager; 
        
        // Ejecutarlo por primera vez por si cargamos directo en settings
        initSettingsManager(); 

        console.log('Project Aurora: Módulos cargados correctamente.');
    } catch (error) {
        console.error('Error crítico al inicializar la aplicación:', error);
    }
});