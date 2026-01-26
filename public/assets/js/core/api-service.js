/**
 * public/assets/js/core/api-service.js
 * Servicio Inteligente: Envía todas las peticiones al Router central.
 */

import { ApiRoutes } from './api-routes.js';

export const ApiService = {
    
    Routes: ApiRoutes,

    getCsrfToken: () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    },

    /**
     * Realiza una petición POST al enrutador central.
     * @param {Object} routeConfig - Objeto { route: 'alias.clave' } desde ApiRoutes
     * @param {FormData} formData - Los datos del formulario
     */
    post: async (routeConfig, formData = new FormData()) => {
        
        // Validación de desarrollo
        if (!routeConfig || !routeConfig.route) {
            console.error("ApiService: Ruta inválida.", routeConfig);
            return { success: false, message: "Error de configuración interna." };
        }

        const basePath = window.BASE_PATH || '/ProjectAurora/';
        
        // AHORA SIEMPRE APUNTAMOS AL ÍNDICE DE LA API (ROUTER)
        const url = `${basePath}api/`; 

        if (formData instanceof FormData) {
            // 1. Inyección Automática de CSRF
            if (!formData.has('csrf_token')) {
                formData.append('csrf_token', ApiService.getCsrfToken());
            }
            
            // 2. Inyección de la RUTA (Alias)
            // Esto le dice al PHP qué archivo y acción ejecutar
            formData.append('route', routeConfig.route);
            
            // Nota: Si usas postRaw con una 'action' manual en el formData,
            // el router PHP la sobrescribirá con lo definido en route-map.php.
            // Esto es deseado para seguridad.
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });

            // Manejo básico de errores HTTP (ej: 404, 500) del Router
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            return data;

        } catch (error) {
            console.error(`API Error [${routeConfig.route}]:`, error);
            return {
                success: false,
                message: 'Error de conexión con el servidor.'
            };
        }
    },

    // Mantenemos el alias para compatibilidad, pero ahora funciona igual que post
    postRaw: async (routeConfig, formData) => {
        return ApiService.post(routeConfig, formData);
    }
};