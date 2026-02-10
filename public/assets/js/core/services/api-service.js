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
                    config.body.append('csrf_token', ApiService.getCsrfToken());
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

    post: async (routeConfig, formData = new FormData(), options = {}) => {
        const basePath = window.BASE_PATH || '/ProjectAurora/';
        const url = `${basePath}api/`;

        if (formData instanceof FormData) {
            formData.append('route', routeConfig.route);
        }

        let fetchConfig = {
            method: 'POST',
            body: formData,
            signal: options.signal || null, 
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        try {
            for (const interceptor of ApiService.requestInterceptors) {
                fetchConfig = await interceptor(fetchConfig);
            }
        } catch (e) {
            return ApiService._formatError(e);
        }

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

            const data = await response.json();
            return ApiService._normalizeResponse(data);

        } catch (error) {
            if (error.name === 'AbortError') {
                return { success: false, isAborted: true, message: 'Operación cancelada' };
            }

            if (error.isHandled) {
                return { success: false, message: error.message, code: 'AUTH_ERROR' };
            }

            if (retriesLeft > 0) {
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