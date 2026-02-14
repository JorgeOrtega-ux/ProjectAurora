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
     * Método POST Principal (MODO ESTRICTO)
     * @param {Object} routeObject - DEBE ser un objeto de ApiRoutes (ej. ApiRoutes.Auth.Login)
     * @param {Object} data - Datos a enviar
     * @param {Object} options - Opciones extra
     */
    post: async (routeObject, data = {}, options = {}) => {
        const basePath = window.BASE_PATH || '/ProjectAurora/';
        const url = `${basePath}api/`;

        // === 1. VALIDACIÓN ESTRICTA DE ARQUITECTURA ===
        // Eliminamos la normalización. Ahora exigimos el estándar.
        if (!routeObject || typeof routeObject !== 'object' || !routeObject.route) {
            console.error('🛑 ERROR DE ARQUITECTURA: ApiService.post() recibió un formato inválido.');
            console.error('   ❌ Recibido:', routeObject);
            console.error('   ✅ Se esperaba un objeto de ApiRoutes (ej: ApiRoutes.Interaction.Like)');
            
            return { 
                success: false, 
                message: 'Error Interno: Implementación de ruta inválida (Ver consola)' 
            };
        }

        const routeValue = routeObject.route;
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

        // Agregar la ruta validada
        formData.append('route', routeValue);
        
        // Inyectar 'action' automáticamente si no viene en los datos
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

            for (const interceptor of ApiService.responseInterceptors) {
                await interceptor(response.clone()); 
            }

            if (!response.ok) {
                throw new Error(`HTTP Error ${response.status}`);
            }

            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('❌ Error JSON del servidor:', text);
                return { success: false, message: 'Invalid JSON response from server' };
            }

            if (!data.success && data.message === 'Invalid API Route') {
                console.error('🔥 ERROR CRÍTICO DE BACKEND: Ruta no registrada en route-map.php');
                console.error('   Ruta enviada:', config.body instanceof FormData ? config.body.get('route') : 'Unknown');
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
            ...data 
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