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
import { AdminUsersController } from '../controllers/admin-users-controller.js';
import { AdminManageUserController } from '../controllers/admin-manage-user-controller.js';
import { AdminManageStatusController } from '../controllers/admin-manage-status-controller.js';
import { AdminServerController } from '../controllers/admin-server-controller.js';
import { AdminBackupsController } from '../controllers/admin-backups-controller.js'; // <-- IMPORTACIÓN
import { CalendarController } from '../components/calendar-controller.js';

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
    window.calendarController = new CalendarController();
    
    // Inicializar los controladores de Admin
    window.adminUsersController = new AdminUsersController(); 
    window.adminManageUserController = new AdminManageUserController();
    window.adminManageStatusController = new AdminManageStatusController(); 
    window.adminServerController = new AdminServerController(); 
    window.adminBackupsController = new AdminBackupsController(); // <-- INSTANCIACIÓN
});