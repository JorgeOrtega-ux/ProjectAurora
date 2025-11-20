// assets/js/app-init.js

import { initUrlManager } from './url-manager.js';
import { initAuthManager } from './auth-manager.js';
import { initMainController } from './main-controller.js';
import { AlertManager } from './alert-manager.js';
import { initTooltipManager } from './tooltip-manager.js';
import { initDragController } from './drag-controller.js'; // <--- [1] IMPORTAR
import { SocketService } from './socket-service.js';
import { NotificationsManager } from './notifications-manager.js';
import { FriendsManager } from './friends-manager.js';

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

        // --- [2] INICIAR EL DRAG CONTROLLER ---
        initDragController();
        // --------------------------------------

        console.log('Project Aurora: Módulos cargados correctamente.');
    } catch (error) {
        console.error('Error crítico al inicializar la aplicación:', error);
    }
});