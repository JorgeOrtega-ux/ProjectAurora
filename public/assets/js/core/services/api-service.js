/**
 * public/assets/js/core/services/api-service.js
 * Servicio de Comunicación Avanzado
 * Características: Cancelación, Reintentos, Interceptors y Normalización de Errores.
 */

import { ApiRoutes } from './api-routes.js';
import { ToastManager } from '../components/toast-manager.js';
import { I18nManager } from '../utils/i18n-manager.js';

// Configuración predeterminada
const CONFIG = {
    MAX_RETRIES: 2,
    RETRY_DELAY_MS: 1000,
    TIMEOUT_MS: 15000 // 15 segundos timeout por defecto
};

export const ApiService = {
    
    Routes: ApiRoutes,

    /**
     * Obtiene el token CSRF del meta tag
     */
    getCsrfToken: () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    },

    /**
     * Interceptores de Solicitud (Request)
     * Permiten modificar la configuración antes de enviar.
     */
    requestInterceptors: [
        (config) => {
            // Inyección automática de CSRF en FormData
            if (config.body instanceof FormData) {
                if (!config.body.has('csrf_token')) {
                    config.body.append('csrf_token', ApiService.getCsrfToken());
                }
            }
            return config;
        }
    ],

    /**
     * Interceptores de Respuesta (Response)
     * Manejo centralizado de códigos de estado HTTP.
     */
    responseInterceptors: [
        async (response) => {
            // Manejo de Sesión Expirada (401/403)
            if (response.status === 401 || response.status === 403) {
                console.warn('🔒 Sesión expirada o acceso denegado.');
                
                // Evitar bucles de redirección si ya estamos en login
                if (!window.location.pathname.includes('/login')) {
                    ToastManager.show(I18nManager.t('api.session_expired') || 'Sesión expirada', 'warning');
                    setTimeout(() => {
                        window.location.href = (window.BASE_PATH || '/') + 'login';
                    }, 1500);
                }
                // Retornamos un error controlado para detener el flujo
                return Promise.reject({ 
                    isHandled: true, 
                    message: 'Sesión expirada', 
                    status: response.status 
                });
            }

            // Manejo de Error de Servidor (500)
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
     * Método principal POST
     * @param {Object} routeConfig - { route: 'alias.clave' }
     * @param {FormData} formData - Datos a enviar
     * @param {Object} options - { signal: AbortSignal, retry: boolean }
     */
    post: async (routeConfig, formData = new FormData(), options = {}) => {
        // 1. Preparación de la URL y Payload
        const basePath = window.BASE_PATH || '/ProjectAurora/';
        const url = `${basePath}api/`;

        // Inyectar ruta del router PHP
        if (formData instanceof FormData) {
            formData.append('route', routeConfig.route);
        }

        // 2. Configuración inicial del Fetch
        let fetchConfig = {
            method: 'POST',
            body: formData,
            signal: options.signal || null, // Soporte para cancelación
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        // 3. Ejecutar Interceptores de Request
        try {
            for (const interceptor of ApiService.requestInterceptors) {
                fetchConfig = await interceptor(fetchConfig);
            }
        } catch (e) {
            return ApiService._formatError(e);
        }

        // 4. Ejecución con Lógica de Reintento
        return ApiService._fetchWithRetry(url, fetchConfig, options.retry !== false ? CONFIG.MAX_RETRIES : 0);
    },

    /**
     * Lógica interna de fetch con reintentos recursivos
     */
    _fetchWithRetry: async (url, config, retriesLeft) => {
        try {
            const response = await fetch(url, config);

            // Ejecutar Interceptores de Respuesta
            for (const interceptor of ApiService.responseInterceptors) {
                await interceptor(response.clone()); // Clonamos para no consumir el body
            }

            if (!response.ok) {
                throw new Error(`HTTP Error ${response.status}`);
            }

            const data = await response.json();
            return ApiService._normalizeResponse(data);

        } catch (error) {
            // Si el error fue "abortado" (cancelación intencional), no reintentamos ni mostramos error.
            if (error.name === 'AbortError') {
                console.log('⏹️ Petición cancelada por el usuario.');
                return { success: false, isAborted: true, message: 'Operación cancelada' };
            }

            // Si el error ya fue manejado por un interceptor (ej. 401), retornamos limpio
            if (error.isHandled) {
                return { success: false, message: error.message, code: 'AUTH_ERROR' };
            }

            // Lógica de Reintento (Solo para errores de red o 5xx)
            if (retriesLeft > 0) {
                console.warn(`⚠️ Error de red. Reintentando... (${retriesLeft} restantes)`);
                await new Promise(r => setTimeout(r, CONFIG.RETRY_DELAY_MS));
                return ApiService._fetchWithRetry(url, config, retriesLeft - 1);
            }

            // Fallo definitivo
            console.error(`❌ API Error Final [${url}]:`, error);
            return ApiService._formatError(error);
        }
    },

    /**
     * Estandariza la respuesta exitosa o fallida del backend
     */
    _normalizeResponse: (data) => {
        if (!data) return { success: false, message: 'Respuesta vacía del servidor' };
        
        // Aseguramos estructura consistente
        return {
            success: data.success === true,
            message: data.message || (data.success ? 'Operación exitosa' : 'Error desconocido'),
            data: data.data || data, // Algunos endpoints devuelven datos en raíz o en .data
            ...data // Mantener otras props como 'pagination', 'stats', etc.
        };
    },

    /**
     * Formatea errores de excepción en una estructura segura para la UI
     */
    _formatError: (error) => {
        const isNetworkError = error.message === 'Failed to fetch' || error.message.includes('Network');
        return {
            success: false,
            message: isNetworkError ? (I18nManager.t('js.core.connection_error') || 'Error de conexión') : (error.message || 'Error inesperado'),
            code: isNetworkError ? 'NETWORK_ERROR' : 'UNKNOWN_ERROR',
            debug: error
        };
    },
    
    // Alias para compatibilidad
    postRaw: (route, data, opts) => ApiService.post(route, data, opts)
};