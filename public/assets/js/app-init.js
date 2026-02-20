// public/assets/js/app-init.js
import { MainController } from './main-controller.js';
import { SpaRouter } from './spa-router.js';
import { AuthController } from './auth-controller.js';
import { ProfileController } from './profile-controller.js';
import { PreferencesController } from './preferences-controller.js'; // <-- Importamos

document.addEventListener('DOMContentLoaded', () => {
    const app = new MainController();
    
    const router = new SpaRouter({
        outlet: '#app-router-outlet'
    });

    const auth = new AuthController(router); 
    const profile = new ProfileController(); 
    
    // Inicializar el controlador de preferencias y hacerlo global
    window.preferencesController = new PreferencesController(); 
});