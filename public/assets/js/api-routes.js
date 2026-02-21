// public/assets/js/api-routes.js
const BASE_PATH = '/ProjectAurora';

export const API_ROUTES = {
    AUTH: {
        CHECK_EMAIL: `${BASE_PATH}/api/auth.check_email`,
        SEND_CODE: `${BASE_PATH}/api/auth.send_code`,
        REGISTER: `${BASE_PATH}/api/auth.register`,
        LOGIN: `${BASE_PATH}/api/auth.login`,
        VERIFY_2FA: `${BASE_PATH}/api/auth.verify_2fa`,
        LOGOUT: `${BASE_PATH}/api/auth.logout`,
        CHECK_SESSION: `${BASE_PATH}/api/auth.check_session`,
        FORGOT_PASSWORD: `${BASE_PATH}/api/auth.forgot_password`,
        RESET_PASSWORD: `${BASE_PATH}/api/auth.reset_password`
    },
    SETTINGS: {
        UPLOAD_AVATAR: `${BASE_PATH}/api/settings.upload_avatar`,
        DELETE_AVATAR: `${BASE_PATH}/api/settings.delete_avatar`,
        UPDATE_FIELD: `${BASE_PATH}/api/settings.update_field`,
        REQUEST_EMAIL_CHANGE: `${BASE_PATH}/api/settings.request_email_change`,
        CONFIRM_EMAIL_CHANGE: `${BASE_PATH}/api/settings.confirm_email_change`,
        
        GET_PREFERENCES: `${BASE_PATH}/api/settings.get_preferences`,
        UPDATE_PREFERENCE: `${BASE_PATH}/api/settings.update_preference`,

        VERIFY_PASSWORD: `${BASE_PATH}/api/settings.verify_password`,
        UPDATE_PASSWORD: `${BASE_PATH}/api/settings.update_password`,

        // RUTAS 2FA
        INIT_2FA: `${BASE_PATH}/api/settings.2fa_init`,
        ENABLE_2FA: `${BASE_PATH}/api/settings.2fa_enable`,
        DISABLE_2FA: `${BASE_PATH}/api/settings.2fa_disable`,
        REGEN_2FA: `${BASE_PATH}/api/settings.2fa_regen`,

        // RUTAS DE GESTIÓN DE DISPOSITIVOS (SESIONES)
        GET_DEVICES: `${BASE_PATH}/api/settings.get_devices`,
        REVOKE_DEVICE: `${BASE_PATH}/api/settings.revoke_device`,
        REVOKE_ALL_DEVICES: `${BASE_PATH}/api/settings.revoke_all_devices`
    }
};