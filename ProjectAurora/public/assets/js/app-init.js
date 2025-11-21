// assets/js/app-init.js

// [CORE]
import { initUrlManager } from './core/url-manager.js';

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

document.addEventListener('DOMContentLoaded', () => {
    try {

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
        // Nota: Como la sección de configuración se carga dinámicamente vía AJAX (UrlManager),
        // initSettingsManager debe ser capaz de ejecutarse cuando el contenido cambie.
        // UrlManager.js debería llamar a window.initSettingsManager() si existe después de cargar contenido.
        window.initSettingsManager = initSettingsManager;

        // Ejecutarlo por primera vez por si cargamos directo en settings (F5 en página de ajustes)
        initSettingsManager();

    } catch (error) {
        console.error('Error crítico al inicializar la aplicación:', error);
    }
});