/**
 * api-services.js
 * Capa de servicio para manejar todas las peticiones HTTP al backend.
 */

const BASE_PATH = window.BASE_PATH || '/ProjectAurora/';

/**
 * Función genérica para enviar datos POST (Auth, Forms)
 */
async function postRequest(endpoint, data) {
    try {
        const formData = new FormData();
        
        // 1. Inyectar Datos
        for (const [key, value] of Object.entries(data)) {
            formData.append(key, value);
        }

        // 2. Inyectar Token CSRF
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            formData.append('csrf_token', csrfMeta.getAttribute('content'));
        }

        const response = await fetch(BASE_PATH + endpoint, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error('API Error (POST):', error);
        return { status: 'error', message: 'Error de conexión.' };
    }
}

async function getRequest(endpoint, params = {}) {
    try {
        const url = new URL(BASE_PATH + endpoint, window.location.origin);
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));

        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);
        if (endpoint.includes('.php')) return await response.text();
        return await response.json();
    } catch (error) {
        console.error('API Error (GET):', error);
        throw error;
    }
}

export const AuthService = {
    login: (email, password) => postRequest('api/auth_handler.php', { action: 'login', email, password }),
    registerStep1: (email, password) => postRequest('api/auth_handler.php', { action: 'register_step_1', email, password }),
    registerStep2: (username) => postRequest('api/auth_handler.php', { action: 'register_step_2', username }),
    verifyCode: (code) => postRequest('api/auth_handler.php', { action: 'verify_code', code }),
    resendVerificationCode: () => postRequest('api/auth_handler.php', { action: 'resend_verification_code' }),
    requestPasswordReset: (email) => postRequest('api/auth_handler.php', { action: 'request_password_reset', email }),
    resetPassword: (token, password) => postRequest('api/auth_handler.php', { action: 'reset_password', token, password }),
    
    updateProfile: (username, email) => postRequest('api/auth_handler.php', { action: 'update_profile', username, email }),
    
    // [NUEVO] Actualizar Preferencias
    updatePreferences: (data) => postRequest('api/auth_handler.php', { 
        action: 'update_preferences', 
        ...data 
    }),
    
    // --- MÉTODOS DE IMAGEN ---
    uploadProfilePicture: (file) => {
        return postRequest('api/auth_handler.php', {
            action: 'upload_profile_picture',
            image: file
        });
    },

    deleteProfilePicture: () => {
        return postRequest('api/auth_handler.php', {
            action: 'delete_profile_picture'
        });
    },

    logout: async () => {
        const result = await postRequest('api/auth_handler.php', { action: 'logout' });
        if (result.status === 'success') window.location.href = result.redirect;
    }
};

export const ContentService = {
    fetchSection: (sectionName) => getRequest('loader.php', { section: sectionName })
};