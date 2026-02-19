// public/assets/js/api-routes.js
const BASE_PATH = '/ProjectAurora';

export const API_ROUTES = {
    AUTH: {
        CHECK_EMAIL: `${BASE_PATH}/api/auth.check_email`,
        SEND_CODE: `${BASE_PATH}/api/auth.send_code`,
        REGISTER: `${BASE_PATH}/api/auth.register`,
        LOGIN: `${BASE_PATH}/api/auth.login`,
        LOGOUT: `${BASE_PATH}/api/auth.logout`,
        CHECK_SESSION: `${BASE_PATH}/api/auth.check_session`,
        FORGOT_PASSWORD: `${BASE_PATH}/api/auth.forgot_password`, // NUEVA
        RESET_PASSWORD: `${BASE_PATH}/api/auth.reset_password`    // NUEVA
    }
};