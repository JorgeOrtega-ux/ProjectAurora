import { MainController } from './main-controller.js';
import { SpaRouter } from './spa-router.js';
import { AuthController } from './auth-controller.js'; // Importar

document.addEventListener('DOMContentLoaded', () => {
    const app = new MainController();
    const auth = new AuthController(); // Inicializar
    
    const router = new SpaRouter({
        outlet: '#app-router-outlet'
    });
});