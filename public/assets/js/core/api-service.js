/**
 * public/assets/js/core/api-service.js
 * Servicio centralizado para peticiones API
 */

export const ApiService = {
    /**
     * Obtiene el token CSRF del meta tag
     */
    getCsrfToken: () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    },

    /**
     * Realiza una petición POST al endpoint especificado
     * @param {string} endpoint - El archivo PHP dentro de /api/ (ej: 'auth-handler.php')
     * @param {FormData} formData - Los datos a enviar
     */
    post: async (endpoint, formData) => {
        // Asegurar que window.BASE_PATH existe
        const basePath = window.BASE_PATH || '/ProjectAurora/';
        
        // Construir la URL completa
        const url = `${basePath}api/${endpoint}`;

        // Inyectar CSRF Token automáticamente si es FormData
        if (formData instanceof FormData) {
            if (!formData.has('csrf_token')) {
                formData.append('csrf_token', ApiService.getCsrfToken());
            }
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });

            // Intentar parsear JSON
            const data = await response.json();
            return data;

        } catch (error) {
            console.error(`API Error (${endpoint}):`, error);
            // Retornar una estructura de error consistente
            return {
                success: false,
                message: 'Error de conexión con el servidor.'
            };
        }
    }
};