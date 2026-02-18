import { MainController } from './main-controller.js';
import { SpaRouter } from './spa-router.js';

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar lógica de UI existente
    const app = new MainController();
    
    // Inicializar Sistema SPA
    const router = new SpaRouter({
        outlet: '#app-router-outlet'
    });
});