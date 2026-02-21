// public/assets/js/core/app-init.js
import { MainController } from './main-controller.js';
import { SpaRouter } from './spa-router.js';
import { AuthController } from '../controllers/auth-controller.js';
import { ProfileController } from '../controllers/profile-controller.js';
import { PreferencesController } from '../controllers/preferences-controller.js'; 
import { DialogController } from '../components/dialog-controller.js';
import { TooltipController } from '../components/tooltip-controller.js';
import { TwoFactorController } from '../controllers/2fa-controller.js'; 
import { DevicesController } from '../controllers/devices-controller.js';

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
    window.devicesController = new DevicesController();
});