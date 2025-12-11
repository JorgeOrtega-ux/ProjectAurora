import { initMainController } from './main-controller.js';
import { initUrlManager } from './url-manager.js'; 
import { initAuthController } from './auth-controller.js';
import { initProfileController } from './profile-controller.js'; 
import { initSecurityController } from './security-controller.js'; 
import { initTooltipService } from './tooltip-service.js'; // Importar servicio de tooltips

const App = {
    init: () => {
        // Inicializar UI (Menús, buscador)
        initMainController();
        
        // Inicializar Lógica de Autenticación (Login, Registro)
        initAuthController();
        
        // Inicializar Lógica de Perfil (Edición de datos)
        initProfileController();
        
        // Inicializar Lógica de Seguridad (Contraseña)
        initSecurityController();
        
        // Inicializar SPA Router
        initUrlManager();
        
        // Inicializar Tooltips
        initTooltipService();
        
        console.log('App: Inicializada completamente.');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});