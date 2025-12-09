/**
 * api-services.js
 * Capa de servicio para manejar todas las peticiones HTTP al backend.
 */

const BASE_PATH = window.BASE_PATH || '/ProjectAurora/';

/**
 * Función genérica para enviar datos POST (Auth, Forms)
 * Utiliza FormData para máxima compatibilidad con el backend PHP actual.
 */
async function postRequest(endpoint, data) {
    try {
        const formData = new FormData();
        
        // 1. Inyectar Datos Originales
        for (const [key, value] of Object.entries(data)) {
            formData.append(key, value);
        }

        // 2. Inyectar Token CSRF Automáticamente
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            formData.append('csrf_token', csrfMeta.getAttribute('content'));
        } else {
            console.warn('Advertencia: No se encontró meta tag CSRF.');
        }

        const response = await fetch(BASE_PATH + endpoint, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        console.error('API Error (POST):', error);
        return { status: 'error', message: 'Error de conexión con el servidor.' };
    }
}

/**
 * Función genérica para obtener datos GET (HTML, JSON)
 */
async function getRequest(endpoint, params = {}) {
    try {
        const url = new URL(BASE_PATH + endpoint, window.location.origin);
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));

        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status}`);
        }

        // Detectar si esperamos JSON o Texto (HTML) basado en el endpoint
        if (endpoint.includes('.php')) {
            // Asumimos texto/html para el loader, o json para api si fuera GET
            return await response.text();
        }
        
        return await response.json();
    } catch (error) {
        console.error('API Error (GET):', error);
        throw error;
    }
}

export const AuthService = {
    login: (email, password) => {
        return postRequest('api/auth_handler.php', { 
            action: 'login', 
            email, 
            password 
        });
    },

    registerStep1: (email, password) => {
        return postRequest('api/auth_handler.php', { 
            action: 'register_step_1', 
            email, 
            password 
        });
    },

    registerStep2: (username) => {
        return postRequest('api/auth_handler.php', { 
            action: 'register_step_2', 
            username 
        });
    },

    verifyCode: (code) => {
        return postRequest('api/auth_handler.php', { 
            action: 'verify_code', 
            code 
        });
    },
    
    // NUEVO: Reenviar código de verificación
    resendVerificationCode: () => {
        return postRequest('api/auth_handler.php', {
            action: 'resend_verification_code'
        });
    },

    // NUEVO: Solicitar reset (usado también para reenviar enlace)
    requestPasswordReset: (email) => {
        return postRequest('api/auth_handler.php', { 
            action: 'request_password_reset', 
            email 
        });
    },

    // NUEVO: Ejecutar cambio
    resetPassword: (token, password) => {
        return postRequest('api/auth_handler.php', { 
            action: 'reset_password', 
            token,
            password 
        });
    },
    
    // MODIFICADO: Logout ahora es async y usa postRequest
    logout: async () => {
        const result = await postRequest('api/auth_handler.php', { 
            action: 'logout' 
        });
        
        if (result.status === 'success') {
            window.location.href = result.redirect;
        } else {
            console.error("Error al cerrar sesión:", result.message);
        }
    }
};

export const ContentService = {
    fetchSection: (sectionName) => {
        return getRequest('loader.php', { section: sectionName });
    }
};