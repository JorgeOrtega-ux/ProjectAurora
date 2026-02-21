// public/assets/js/app-init.js
import { MainController } from './main-controller.js';
import { SpaRouter } from './spa-router.js';
import { AuthController } from './auth-controller.js';
import { ProfileController } from './profile-controller.js';
import { PreferencesController } from './preferences-controller.js'; 
import { DialogController } from './dialog-controller.js';
import { TooltipController } from './tooltip-controller.js';
import { TwoFactorController } from './2fa-controller.js'; 
import { DevicesController } from './devices-controller.js'; // <-- Importado

document.addEventListener('DOMContentLoaded', () => {
    const app = new MainController();
    
    const router = new SpaRouter({
        outlet: '#app-router-outlet'
    });

    const auth = new AuthController(router); 
    const profile = new ProfileController(); 
    
    // Controladores globales
    window.preferencesController = new PreferencesController(); 
    window.dialogController = new DialogController();
    window.tooltipController = new TooltipController(); 
    window.twoFactorController = new TwoFactorController(); 
    window.devicesController = new DevicesController(); // <-- Inicializado
});