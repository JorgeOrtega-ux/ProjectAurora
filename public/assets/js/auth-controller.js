// public/assets/js/auth-controller.js
import { ApiService } from './api-services.js';
import { API_ROUTES } from './api-routes.js';

export class AuthController {
    constructor(router) {
        this.router = router;
        this.init();
        
        // Ejecutar la revisión de etapa inicial en caso de cargar directo del navegador
        this.checkRegisterStage(window.location.pathname);
        this.checkResetPasswordStage(window.location.pathname);
    }

    init() {
        // Envíos de los formularios
        document.body.addEventListener('submit', (e) => {
            if (e.target.id === 'form-login') { e.preventDefault(); this.handleLogin(); }
            else if (e.target.id === 'form-register-1') { e.preventDefault(); this.handleRegisterStage1(); }
            else if (e.target.id === 'form-register-2') { e.preventDefault(); this.handleRegisterStage2(); }
            else if (e.target.id === 'form-register-3') { e.preventDefault(); this.handleRegisterFinal(); }
            else if (e.target.id === 'form-forgot-password') { e.preventDefault(); this.handleForgotPassword(); }
            else if (e.target.id === 'form-reset-password') { e.preventDefault(); this.handleResetPassword(); }
        });

        document.body.addEventListener('click', (e) => {
            // Cerrar Sesión
            const logoutBtn = e.target.closest('[data-action="logout"]');
            if (logoutBtn) { e.preventDefault(); this.handleLogout(logoutBtn); return; }

            // Visibilidad de contraseñas
            const toggleBtn = e.target.closest('.component-input-action');
            if (toggleBtn) {
                const wrapper = toggleBtn.parentElement;
                const input = wrapper.querySelector('input');
                const icon = toggleBtn.querySelector('span');
                if (input && input.type === 'password') { input.type = 'text'; icon.textContent = 'visibility_off'; }
                else if (input && input.type === 'text') { input.type = 'password'; icon.textContent = 'visibility'; }
            }

            // Botones de retroceso en registro usando el Router
            if (e.target.closest('#btn-back-1')) {
                this.router.navigate('/ProjectAurora/register');
            } else if (e.target.closest('#btn-back-2')) {
                this.router.navigate('/ProjectAurora/register/aditional-data');
            }
        });

        // Escuchar cuando el router termina de cargar una vista para mostrar la etapa correcta
        window.addEventListener('viewLoaded', (e) => {
            this.checkRegisterStage(e.detail.url);
            this.checkResetPasswordStage(e.detail.url);
        });
    }

    // --- FUNCIÓN PARA MOSTRAR ERROR JSON ---
    showFatalJsonError(containerId, codeId, httpCode, message, type, codeStr, formsToHide = []) {
        const container = document.getElementById(containerId);
        const codeBox = document.getElementById(codeId);
        
        if (!container || !codeBox) return;

        // Ocultar formularios y títulos normales
        formsToHide.forEach(form => {
            if (form) form.style.display = 'none';
        });
        const header = document.querySelector('.component-header-centered');
        if (header) header.style.display = 'none';

        // Formatear JSON como en la imagen
        const jsonContent = `Route Error (${httpCode} ): {\n  "error": {\n    "message": "${message}",\n    "type": "${type}",\n    "param": null,\n    "code": "${codeStr}"\n  }\n}`;
        codeBox.textContent = jsonContent;
        container.style.display = 'flex';
    }

    // --- LÓGICA DE CONTROL DE ETAPAS BASADA EN URL ---
    checkRegisterStage(url) {
        if (!url.includes('/ProjectAurora/register')) return;

        const stage1 = document.getElementById('form-register-1');
        const stage2 = document.getElementById('form-register-2');
        const stage3 = document.getElementById('form-register-3');
        const header = document.querySelector('.component-header-centered');
        const fatalContainer = document.getElementById('register-fatal-error');

        if (!stage1 || !stage2 || !stage3) return;

        // Reset visibility (limpiar errores si el usuario regresó a la url original)
        if (header) header.style.display = 'block';
        if (fatalContainer) fatalContainer.style.display = 'none';

        // 1. Restaurar datos desde sessionStorage
        const email = sessionStorage.getItem('reg_email') || '';
        const pass = sessionStorage.getItem('reg_password') || '';
        const user = sessionStorage.getItem('reg_username') || '';
        const devCode = sessionStorage.getItem('reg_dev_code') || '';

        if (document.getElementById('reg-email')) document.getElementById('reg-email').value = email;
        if (document.getElementById('reg-password')) document.getElementById('reg-password').value = pass;
        if (document.getElementById('reg-username')) document.getElementById('reg-username').value = user;

        // 2. Determinar qué etapa mostrar ocultando todas primero
        stage1.style.display = 'none';
        stage2.style.display = 'none';
        stage3.style.display = 'none';

        const title = document.getElementById('auth-title');
        const subtitle = document.getElementById('auth-subtitle');

        if (url.endsWith('/register/aditional-data')) {
            if (!email) { 
                this.showFatalJsonError('register-fatal-error', 'register-fatal-error-code', 409, 'Invalid client. Please start over.', 'invalid_request_error', 'invalid_state', [stage1, stage2, stage3]);
                return; 
            }
            stage2.style.display = 'flex';
            if (title) { title.textContent = 'Casi listo'; subtitle.textContent = '¿Cómo deberíamos llamarte?'; }
            setTimeout(() => document.getElementById('reg-username').focus(), 50);

        } else if (url.endsWith('/register/verification-account')) {
            if (!email || !user) { 
                this.showFatalJsonError('register-fatal-error', 'register-fatal-error-code', 409, 'Invalid client. Please start over.', 'invalid_request_error', 'invalid_state', [stage1, stage2, stage3]);
                return; 
            }
            stage3.style.display = 'flex';
            if (title) { title.textContent = 'Verificar cuenta'; subtitle.textContent = 'Confirma tu identidad'; }
            const display = document.getElementById('simulated-code-display');
            if (display && devCode) display.textContent = devCode;
            setTimeout(() => document.getElementById('reg-code').focus(), 50);

        } else {
            // Base /register
            stage1.style.display = 'flex';
            if (title) { title.textContent = 'Crear Cuenta'; subtitle.textContent = 'Regístrate para comenzar'; }
        }
    }

    // --- LÓGICA DE CONTROL PARA RESET PASSWORD URL ---
    checkResetPasswordStage(url) {
        if (!url.includes('/ProjectAurora/reset-password')) return;
        
        const token = new URLSearchParams(window.location.search).get('token');
        const form = document.getElementById('form-reset-password');
        const header = document.querySelector('.component-header-centered');
        const fatalContainer = document.getElementById('reset-fatal-error');
        
        // Reset visibility
        if (fatalContainer) fatalContainer.style.display = 'none';
        if (header) header.style.display = 'block';
        if (form) form.style.display = 'flex';

        // Validar si entró directamente sin proporcionar token en la URL
        if (!token) {
            this.showFatalJsonError('reset-fatal-error', 'reset-fatal-error-code', 400, 'Invalid or missing token. Please start over.', 'invalid_request_error', 'invalid_state', [form]);
        }
    }

    // --- MANEJO DE REGISTRO ---
    async handleRegisterStage1() {
        const emailInput = document.getElementById('reg-email');
        const passwordInput = document.getElementById('reg-password');
        const errorDiv = document.getElementById('register-error-1');
        const btn = document.getElementById('btn-next-1');
        const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';

        if (!emailInput.value || !passwordInput.value) { this.showError(errorDiv, 'Por favor, ingresa tu correo y contraseña.'); return; }
        if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value)){ this.showError(errorDiv, 'Ingresa un correo válido.'); return; }

        this.setLoading(btn, true);
        this.hideError(errorDiv);

        try {
            const res = await ApiService.post(API_ROUTES.AUTH.CHECK_EMAIL, { email: emailInput.value, csrf_token: csrfToken });
            if (res.success) {
                sessionStorage.setItem('reg_email', emailInput.value);
                sessionStorage.setItem('reg_password', passwordInput.value);
                this.router.navigate('/ProjectAurora/register/aditional-data'); // Cambia URL
            } else {
                this.showError(errorDiv, res.message);
            }
        } catch (error) { this.showError(errorDiv, 'Error de conexión.'); } 
        finally { this.setLoading(btn, false); }
    }

    async handleRegisterStage2() {
        const usernameInput = document.getElementById('reg-username');
        const errorDiv = document.getElementById('register-error-2');
        const btn = document.getElementById('btn-next-2');
        const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';

        const email = sessionStorage.getItem('reg_email');
        const password = sessionStorage.getItem('reg_password');

        if (!usernameInput.value) { this.showError(errorDiv, 'El nombre de usuario es obligatorio.'); return; }

        this.setLoading(btn, true);
        this.hideError(errorDiv);

        try {
            const res = await ApiService.post(API_ROUTES.AUTH.SEND_CODE, { email, password, username: usernameInput.value, csrf_token: csrfToken });
            if (res.success) {
                sessionStorage.setItem('reg_username', usernameInput.value);
                sessionStorage.setItem('reg_dev_code', res.dev_code);
                this.router.navigate('/ProjectAurora/register/verification-account'); // Cambia URL
            } else { this.showError(errorDiv, res.message); }
        } catch (error) { this.showError(errorDiv, 'Error al generar el código.'); } 
        finally { this.setLoading(btn, false); }
    }

    async handleRegisterFinal() {
        const code = document.getElementById('reg-code').value;
        const errorDiv = document.getElementById('register-error-3');
        const btn = document.getElementById('btn-register-final');
        const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';
        const email = sessionStorage.getItem('reg_email');

        if (!code || code.length !== 6) { this.showError(errorDiv, 'Ingresa el código de 6 dígitos enviado.'); return; }

        this.setLoading(btn, true);
        this.hideError(errorDiv);

        try {
            const res = await ApiService.post(API_ROUTES.AUTH.REGISTER, { email, code, csrf_token: csrfToken });
            if (res.success) {
                sessionStorage.clear(); // Limpiar datos temporales
                window.location.href = '/ProjectAurora/';
            } else { this.showError(errorDiv, res.message); this.setLoading(btn, false); }
        } catch (error) { this.showError(errorDiv, 'Error de conexión.'); this.setLoading(btn, false); }
    }

    // --- MÉTODOS EXISTENTES INTACTOS (Login, Forgot, Reset) ---
    async handleLogin() {
        const form = document.getElementById('form-login');
        const btn = form.querySelector('button[type="submit"]'); 
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;
        const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';
        const errorDiv = document.getElementById('login-error');

        this.setLoading(btn, true);
        this.hideError(errorDiv);

        try {
            const res = await ApiService.post(API_ROUTES.AUTH.LOGIN, { email, password, csrf_token: csrfToken });
            if (res.success) window.location.href = '/ProjectAurora/'; 
            else { this.showError(errorDiv, res.message); this.setLoading(btn, false); }
        } catch (error) { this.showError(errorDiv, 'Error de conexión.'); this.setLoading(btn, false); }
    }

    // --- MANEJO DE CERRAR SESIÓN CON SPINNER ---
    async handleLogout(btn) {
        // Prevenir múltiples peticiones
        if (btn.classList.contains('is-loading')) return;
        btn.classList.add('is-loading');

        // Generamos el nuevo div dinámicamente y lo adjuntamos al menú link
        const spinnerContainer = document.createElement('div');
        spinnerContainer.className = 'component-menu-link-icon';
        spinnerContainer.innerHTML = '<div class="component-spinner-button dark-spinner"></div>';
        
        btn.appendChild(spinnerContainer);

        try { 
            await ApiService.post(API_ROUTES.AUTH.LOGOUT); 
            // Añadimos un pequeño timeout de 400ms para que la animación alcance a ser apreciable
            setTimeout(() => {
                window.location.href = '/ProjectAurora/login'; 
            }, 400);
        } catch (error) { 
            console.error(error); 
            // Si falla, removemos el spinner para permitir reintentar
            btn.classList.remove('is-loading');
            spinnerContainer.remove();
        }
    }

    async handleForgotPassword() {
        const email = document.getElementById('forgot-email').value;
        const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';
        const btn = document.getElementById('btn-forgot-password');
        const errorDiv = document.getElementById('forgot-error');
        const linkContainer = document.getElementById('simulated-link-container');
        const linkDisplay = document.getElementById('simulated-link-display');

        this.setLoading(btn, true);
        this.hideError(errorDiv);
        linkContainer.style.display = 'none';

        try {
            const res = await ApiService.post(API_ROUTES.AUTH.FORGOT_PASSWORD, { email, csrf_token: csrfToken });
            if (res.success) {
                linkDisplay.href = res.dev_link; linkDisplay.textContent = window.location.origin + res.dev_link;
                linkDisplay.dataset.nav = res.dev_link; linkContainer.style.display = 'block';
            } else { this.showError(errorDiv, res.message); }
        } catch (error) { this.showError(errorDiv, 'Error al procesar.'); } finally { this.setLoading(btn, false); }
    }

    async handleResetPassword() {
        const token = new URLSearchParams(window.location.search).get('token');
        const pass1 = document.getElementById('reset-password-1').value;
        const pass2 = document.getElementById('reset-password-2').value;
        const btn = document.getElementById('btn-reset-password');
        const errorDiv = document.getElementById('reset-error');
        const successDiv = document.getElementById('reset-success');
        const form = document.getElementById('form-reset-password');

        this.hideError(errorDiv);
        
        // Bloqueo de seguridad si de alguna manera pasaron el chequeo de carga
        if (!token) { 
            this.showFatalJsonError('reset-fatal-error', 'reset-fatal-error-code', 400, 'Invalid client. Please start over.', 'invalid_request_error', 'invalid_state', [form]);
            return; 
        }

        if (pass1 !== pass2) { this.showError(errorDiv, 'Las contraseñas no coinciden.'); return; }

        this.setLoading(btn, true);
        try {
            const res = await ApiService.post(API_ROUTES.AUTH.RESET_PASSWORD, { token, password: pass1, csrf_token: document.getElementById('csrf_token').value });
            if (res.success) {
                successDiv.style.display = 'block'; btn.style.display = 'none';
                setTimeout(() => window.location.href = '/ProjectAurora/login', 2000);
            } else { 
                // AQUÍ interceptamos si el token es viejo o ya se usó (la API regresó success: false)
                this.showFatalJsonError('reset-fatal-error', 'reset-fatal-error-code', 409, res.message, 'invalid_request_error', 'token_expired_or_used', [form]);
                this.setLoading(btn, false); 
            }
        } catch (error) { this.showError(errorDiv, 'Error al actualizar.'); this.setLoading(btn, false); }
    }

    // --- UTILIDADES ---
    setLoading(btn, isLoading) {
        if(!btn) return;
        if (isLoading) {
            if (!btn.dataset.originalText) btn.dataset.originalText = btn.textContent.trim();
            btn.disabled = true; btn.innerHTML = '<div class="component-spinner-button"></div>';
        } else {
            btn.disabled = false; btn.textContent = btn.dataset.originalText || 'Continuar';
        }
    }
    showError(element, message) { if (element) { element.textContent = message; element.style.display = 'block'; } }
    hideError(element) { if (element) { element.style.display = 'none'; element.textContent = ''; } }
}