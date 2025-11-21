// assets/js/app-init.js

// [CORE]
import { initUrlManager } from './core/url-manager.js';
import { initI18n, translateDocument } from './core/i18n-manager.js'; // <--- NUEVO

// [MODULES]
import { initAuthManager } from './modules/auth-manager.js';
import { NotificationsManager } from './modules/notifications-manager.js';
import { FriendsManager } from './modules/friends-manager.js';
import { initSettingsManager } from './modules/settings-manager.js';

// [UI]
import { initMainController } from './ui/main-controller.js';
import { AlertManager } from './ui/alert-manager.js';
import { initTooltipManager } from './ui/tooltip-manager.js';
import { initDragController } from './ui/drag-controller.js';

// [SERVICES]
import { SocketService } from './services/socket-service.js';

document.addEventListener('DOMContentLoaded', async () => { // Async para esperar i18n
    try {
        // 1. Inicializar i18n PRIMERO
        await initI18n();

        // Inicializar Core y Auth
        initUrlManager();
        initAuthManager();

        // Inicializar UI Base
        initMainController();
        initTooltipManager();

        // Inicializar Gestor de Alertas Global
        window.alertManager = new AlertManager();

        // Inicializar Servicios y Managers de Datos
        window.socketService = new SocketService();
        window.notificationsManager = new NotificationsManager();
        window.friendsManager = new FriendsManager();

        // Inicializar Controladores UI avanzados
        initDragController();

        // Inicializar Gestor de Configuración
        window.initSettingsManager = () => {
            initSettingsManager();
            translateDocument(); // Traducir cuando se carga settings dinámicamente
        };

        // Ejecutarlo por primera vez
        window.initSettingsManager();

    } catch (error) {
        console.error('Error crítico al inicializar la aplicación:', error);
    }
});