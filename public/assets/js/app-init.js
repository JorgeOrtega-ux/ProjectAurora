import { initMainController } from './main-controller.js';
import { initUrlManager } from './core/url-manager.js';
import { initAuthController } from './auth-controller.js'; 
import { Toast } from './core/toast-manager.js'; 

// --- 1. IMPORTAR TU NUEVO CONTROLADOR ---
// Ajusta la ruta './modules/settings/...' según donde hayas guardado el archivo
import { SettingsController } from './modules/settings/settings-controller.js'; 

const App = {
    init: () => {
        console.log('App: Inicializando...');
        
        // 0. Inicializar Toasts
        Toast.init();

        // 1. UI General (Menús, etc)
        initMainController();
        
        // 2. Auth Controller (Login/Registro/Logout)
        initAuthController();
        
        // --- 3. INICIALIZAR SETTINGS CONTROLLER ---
        // Esto activa los listeners globales (clics en dropdowns, cerrar al hacer clic fuera)
        SettingsController.init();

        // Intentar inicializar la carga de avatar si los elementos existen en el HTML inicial
        SettingsController.initAvatarUploader(
            'upload-avatar', 
            'preview-avatar', 
            'profile-picture-actions-default', 
            'profile-picture-actions-preview'
        );
        
        // 4. Sistema de Rutas SPA
        if (document.querySelector('.general-content-scrolleable')) {
             initUrlManager();
        }

        // --- 5. SOPORTE SPA (Opcional) ---
        // Si tu UrlManager emite un evento cuando cambia el contenido (ej. al entrar a Perfil),
        // deberías llamar a initAvatarUploader de nuevo. Ejemplo:
        /*
        document.addEventListener('aurora:content-loaded', (e) => {
            // Reinicializar lógica de avatar por si acabamos de cargar la vista de perfil
            SettingsController.initAvatarUploader(
                'upload-avatar', 'preview-avatar', 
                'profile-picture-actions-default', 'profile-picture-actions-preview'
            );
        });
        */
    }
};

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});