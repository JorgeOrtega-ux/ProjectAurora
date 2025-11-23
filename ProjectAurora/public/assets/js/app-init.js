// assets/js/app-init.js

// [CORE]
import { initUrlManager } from './core/url-manager.js';
import { initI18n, translateDocument } from './core/i18n-manager.js';
import { initThemeManager } from './core/theme-manager.js'; 

// [MODULES]
import { initAuthManager } from './modules/auth-manager.js';
import { NotificationsManager } from './modules/notifications-manager.js';
import { FriendsManager } from './modules/friends-manager.js';
import { initSettingsManager } from './modules/settings-manager.js';
// [REMOVIDO] import { initTwoFactorManager } ... ya no es necesario

// [UI]
import { initMainController } from './ui/main-controller.js';
import { AlertManager } from './ui/alert-manager.js';
import { initTooltipManager } from './ui/tooltip-manager.js';
import { initDragController } from './ui/drag-controller.js';

// [SERVICES]
import { SocketService } from './services/socket-service.js';

document.addEventListener('DOMContentLoaded', async () => {
    try {
        // 1. Inicializar i18n y TEMA PRIMERO (Visuales)
        await initI18n();
        initThemeManager(); // Aplica el tema guardado o del sistema

        // Inicializar Core y Auth
        initUrlManager();
        initAuthManager();

        // ----------------------------------------------------
        // [MODIFICADO]
        // Inicializar Gestor de Configuración ANTES que la UI.
        // Ahora incluye 2FA internamente.
        // ----------------------------------------------------
        window.initSettingsManager = () => {
            initSettingsManager();
            // [REMOVIDO] initTwoFactorManager(); ya está integrado
            translateDocument();
        };
        // Ejecutarlo por primera vez
        window.initSettingsManager();

        // Inicializar UI Base DESPUÉS
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

    } catch (error) {
        console.error('Error crítico al inicializar la aplicación:', error);
    }
});