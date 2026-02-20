// public/assets/js/app-init.js
import { MainController } from './main-controller.js';
import { SpaRouter } from './spa-router.js';
import { AuthController } from './auth-controller.js';
import { ProfileController } from './profile-controller.js'; // <-- 1. Importar

document.addEventListener('DOMContentLoaded', () => {
    const app = new MainController();
    
    const router = new SpaRouter({
        outlet: '#app-router-outlet'
    });

    const auth = new AuthController(router); 
    
    // 2. Inicializar el controlador del perfil
    const profile = new ProfileController(); 
});