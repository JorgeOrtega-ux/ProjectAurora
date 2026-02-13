import { ApiRoutes } from './api-routes.js';
import { I18nManager } from '../utils/i18n-manager.js';
import { ToastManager } from '../components/toast-manager.js';

const CONFIG = {
    MAX_RETRIES: 2,
    RETRY_DELAY_MS: 1000,
    TIMEOUT_MS: 15000 
};

const ApiService = {
    
    Routes: ApiRoutes,

    getCsrfToken: () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    },

    requestInterceptors: [
        (config) => {
            if (config.body instanceof FormData) {
                if (!config.body.has('csrf_token')) {
                    const token = ApiService.getCsrfToken();
                    if(token) config.body.append('csrf_token', token);
                }
            }
            return config;
        }
    ],

    responseInterceptors: [
        async (response) => {
            if (response.status === 401 || response.status === 403) {
                if (!window.location.pathname.includes('/login')) {
                    ToastManager.show(I18nManager.t('api.session_expired') || 'Sesión expirada', 'warning');
                    setTimeout(() => {
                        window.location.href = (window.BASE_PATH || '/') + 'login';
                    }, 1500);
                }
                return Promise.reject({ 
                    isHandled: true, 
                    message: 'Sesión expirada', 
                    status: response.status 
                });
            }

            if (response.status >= 500) {
                return Promise.reject({
                    isHandled: false,
                    message: 'Error interno del servidor',
                    status: response.status
                });
            }

            return response;
        }
    ],

    /**
     * Método POST Principal
     * @param {Object|String} routeConfig - Puede ser { route: 'auth.login' } o 'auth.login'
     * @param {Object} data - Datos a enviar (se convertirán a FormData)
     * @param {Object} options - Opciones extra (signal, retry, etc)
     */
    post: async (routeConfig, data = {}, options = {}) => {
        const basePath = window.BASE_PATH || '/ProjectAurora/';
        const url = `${basePath}api/`;

        // === 1. DEBUG Y NORMALIZACIÓN DE RUTA ===
        let routeValue = '';

        if (typeof routeConfig === 'object' && routeConfig !== null && routeConfig.route) {
            routeValue = routeConfig.route;
        } else if (typeof routeConfig === 'string') {
            routeValue = routeConfig;
        } else {
            console.error('❌ ApiService: Formato de ruta inválido:', routeConfig);
            return { success: false, message: 'Invalid Route Format in Client' };
        }

        console.log(`📡 Enviando API: [${routeValue}]`, data);

        // === 2. PREPARACIÓN DEL FORMDATA ===
        let formData;
        if (data instanceof FormData) {
            formData = data;
        } else {
            formData = new FormData();
            for (const key in data) {
                if (Object.prototype.hasOwnProperty.call(data, key)) {
                    const value = data[key];
                    if (typeof value === 'object' && !(value instanceof File) && value !== null) {
                        formData.append(key, JSON.stringify(value));
                    } else if (value !== null && value !== undefined) {
                        formData.append(key, value);
                    }
                }
            }
        }

        // Agregar la ruta normalizada
        // IMPORTANTE: Enviamos tanto 'route' (para el Router) como 'action' (por si acaso el backend lo usa directo)
        formData.append('route', routeValue);
        
        // Si el handler espera 'action' y el router no lo inyecta, esto lo soluciona:
        // Extraemos la acción del string (ej: 'interaction.toggle_like' -> 'toggle_like')
        if (!formData.has('action')) {
            const parts = routeValue.split('.');
            if (parts.length > 1) {
                formData.append('action', parts[1]); 
            } else {
                formData.append('action', routeValue);
            }
        }

        let fetchConfig = {
            method: 'POST',
            body: formData,
            signal: options.signal || null, 
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        // === 3. INTERCEPTORES ===
        try {
            for (const interceptor of ApiService.requestInterceptors) {
                fetchConfig = await interceptor(fetchConfig);
            }
        } catch (e) {
            return ApiService._formatError(e);
        }

        // === 4. ENVÍO ===
        return ApiService._fetchWithRetry(url, fetchConfig, options.retry !== false ? CONFIG.MAX_RETRIES : 0);
    },

    _fetchWithRetry: async (url, config, retriesLeft) => {
        try {
            const response = await fetch(url, config);

            // Interceptores de respuesta (clonamos para no consumir el stream si el interceptor lo lee)
            for (const interceptor of ApiService.responseInterceptors) {
                await interceptor(response.clone()); 
            }

            if (!response.ok) {
                throw new Error(`HTTP Error ${response.status}`);
            }

            // Parsear JSON
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('❌ Error JSON del servidor:', text);
                return { success: false, message: 'Invalid JSON response from server' };
            }

            // Verificar error específico de ruta
            if (!data.success && data.message === 'Invalid API Route') {
                console.error('🔥 ERROR CRÍTICO: El Backend no reconoce la ruta enviada.');
                console.error('   1. Revisa que api/route-map.php tenga la clave exacta que enviaste.');
                console.error('   2. Ruta enviada:', config.body instanceof FormData ? config.body.get('route') : 'Unknown');
            }

            return ApiService._normalizeResponse(data);

        } catch (error) {
            if (error.name === 'AbortError') {
                return { success: false, isAborted: true, message: 'Operación cancelada' };
            }

            if (error.isHandled) {
                return { success: false, message: error.message, code: 'AUTH_ERROR' };
            }

            if (retriesLeft > 0) {
                console.warn(`Reintentando petición (${retriesLeft} restantes)...`);
                await new Promise(r => setTimeout(r, CONFIG.RETRY_DELAY_MS));
                return ApiService._fetchWithRetry(url, config, retriesLeft - 1);
            }

            return ApiService._formatError(error);
        }
    },

    _normalizeResponse: (data) => {
        if (!data) return { success: false, message: 'Respuesta vacía del servidor' };
        
        return {
            success: data.success === true,
            message: data.message || (data.success ? 'Operación exitosa' : 'Error desconocido'),
            data: data.data || data, 
            ...data // Esparcir el resto por si hay campos custom como 'likes', 'views'
        };
    },

    _formatError: (error) => {
        const isNetworkError = error.message === 'Failed to fetch' || error.message.includes('Network');
        console.error('❌ ApiService Error:', error);
        return {
            success: false,
            message: isNetworkError ? (I18nManager.t('js.core.connection_error') || 'Error de conexión') : (error.message || 'Error inesperado'),
            code: isNetworkError ? 'NETWORK_ERROR' : 'UNKNOWN_ERROR',
            debug: error
        };
    },
    
    postRaw: (route, data, opts) => ApiService.post(route, data, opts)
};

export { ApiService };