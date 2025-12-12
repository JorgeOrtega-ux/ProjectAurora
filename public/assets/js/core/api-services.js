/**
 * api-services.js
 * Capa de servicio para manejar todas las peticiones HTTP al backend.
 */

const BASE_PATH = window.BASE_PATH || '/ProjectAurora/';

/**
 * Función genérica para enviar datos POST
 */
async function postRequest(endpoint, data) {
    try {
        const formData = new FormData();
        
        // 1. Inyectar Datos
        if (data && typeof data === 'object') {
            for (const [key, value] of Object.entries(data)) {
                // Manejo especial para archivos si es necesario, o valores simples
                formData.append(key, value);
            }
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

// -------------------------------------------------------------------
// 1. AUTH SERVICE: Solo autenticación (Login, Registro, Recuperación)
// -------------------------------------------------------------------
export const AuthService = {
    login: (email, password) => postRequest('api/auth_handler.php', { action: 'login', email, password }),
    registerStep1: (email, password) => postRequest('api/auth_handler.php', { action: 'register_step_1', email, password }),
    registerStep2: (username) => postRequest('api/auth_handler.php', { action: 'register_step_2', username }),
    verifyCode: (code) => postRequest('api/auth_handler.php', { action: 'verify_code', code }),
    resendVerificationCode: () => postRequest('api/auth_handler.php', { action: 'resend_verification_code' }),
    requestPasswordReset: (email) => postRequest('api/auth_handler.php', { action: 'request_password_reset', email }),
    resetPassword: (token, password) => postRequest('api/auth_handler.php', { action: 'reset_password', token, password }),
    logout: async () => {
        const result = await postRequest('api/auth_handler.php', { action: 'logout' });
        if (result.status === 'success') window.location.href = result.redirect;
    }
};

// -------------------------------------------------------------------
// 2. SETTINGS SERVICE: Gestión de usuario y preferencias
// -------------------------------------------------------------------
export const SettingsService = {
    updateProfile: (username, email) => postRequest('api/settings_handler.php', { action: 'update_profile', username, email }),
    
    updatePreferences: (data) => postRequest('api/settings_handler.php', { 
        action: 'update_preferences', 
        ...data 
    }),
    
    uploadProfilePicture: (file) => {
        return postRequest('api/settings_handler.php', {
            action: 'upload_profile_picture',
            image: file
        });
    },

    deleteProfilePicture: () => {
        return postRequest('api/settings_handler.php', {
            action: 'delete_profile_picture'
        });
    },

    // NUEVO: Método para reparar avatar automáticamente
    repairAvatar: () => {
        return postRequest('api/settings_handler.php', {
            action: 'repair_avatar'
        });
    },

    // Verificar contraseña sin cambiarla
    verifyPassword: (password) => {
        return postRequest('api/settings_handler.php', {
            action: 'verify_current_password',
            password: password
        });
    },

    updatePassword: (currentPassword, newPassword) => {
        return postRequest('api/settings_handler.php', {
            action: 'update_password',
            current_password: currentPassword,
            new_password: newPassword
        });
    },

    // --- MÉTODOS PARA DISPOSITIVOS ---
    getActiveSessions: () => {
        return postRequest('api/settings_handler.php', {
            action: 'get_active_sessions'
        });
    },
    
    revokeSession: (sessionDbId) => {
        return postRequest('api/settings_handler.php', {
            action: 'revoke_session',
            session_db_id: sessionDbId
        });
    },

    revokeAllSessions: (password) => {
        return postRequest('api/settings_handler.php', {
            action: 'revoke_all_sessions',
            password: password
        });
    },

    // --- ELIMINAR CUENTA ---
    deleteAccount: (password) => {
        return postRequest('api/settings_handler.php', {
            action: 'delete_account',
            password: password
        });
    }
};

export const ContentService = {
    fetchSection: (sectionName) => getRequest('loader.php', { section: sectionName })
};