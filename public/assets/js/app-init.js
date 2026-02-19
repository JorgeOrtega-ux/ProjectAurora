import { MainController } from './main-controller.js';
import { SpaRouter } from './spa-router.js';
import { AuthController } from './auth-controller.js';

document.addEventListener('DOMContentLoaded', () => {
    const app = new MainController();
    
    const router = new SpaRouter({
        outlet: '#app-router-outlet'
    });

    // Inyectamos el router al controlador de autenticación
    const auth = new AuthController(router); 
});