import { initMainController } from '../modules/layout/main-controller.js';
import { initUrlManager } from './url-manager.js'; 
import { initAuthController } from '../modules/auth/auth-controller.js';
import { initProfileController } from '../modules/settings/profile-controller.js'; 
import { initSecurityController } from '../modules/settings/security-controller.js'; 
import { initTooltipService } from './ui/tooltip-service.js'; // Importar servicio de tooltips

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