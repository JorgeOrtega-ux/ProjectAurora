// public/assets/js/auth-controller.js
import { SpaRouter } from './spa-router.js';
import { ApiService } from './api-services.js';
import { API_ROUTES } from './api-routes.js';

export class AuthController {
    constructor() {
        this.init();
    }

    init() {
        document.body.addEventListener('submit', (e) => {
            if (e.target.id === 'form-login') {
                e.preventDefault();
                this.handleLogin();
            } else if (e.target.id === 'form-register') {
                e.preventDefault();
                this.handleRegisterFinal(); 
            }
        });

        document.body.addEventListener('click', (e) => {
            const logoutBtn = e.target.closest('[data-action="logout"]');
            if (logoutBtn) {
                e.preventDefault();
                this.handleLogout();
                return;
            }

            const toggleBtn = e.target.closest('.component-input-action');
            if (toggleBtn) {
                const wrapper = toggleBtn.parentElement;
                const input = wrapper.querySelector('input');
                const icon = toggleBtn.querySelector('span');
                
                if (input && input.type === 'password') {
                    input.type = 'text';
                    icon.textContent = 'visibility_off';
                } else if (input && input.type === 'text') {
                    input.type = 'password';
                    icon.textContent = 'visibility';
                }
            }

            if (e.target.closest('#btn-next-1')) {
                this.handleRegisterStage1();
            } else if (e.target.closest('#btn-next-2')) {
                this.handleRegisterStage2();
            } else if (e.target.closest('#btn-back-1')) {
                this.switchStage(2, 1);
            } else if (e.target.closest('#btn-back-2')) {
                this.switchStage(3, 2);
            }
        });
    }

    switchStage(fromStage, toStage) {
        document.getElementById(`reg-stage-${fromStage}`).style.display = 'none';
        document.getElementById(`reg-stage-${toStage}`).style.display = 'block';
        this.hideError(document.getElementById('register-error'));
        
        if (toStage === 2) {
            setTimeout(() => document.getElementById('reg-username').focus(), 50);
        } else if (toStage === 3) {
            setTimeout(() => document.getElementById('reg-code').focus(), 50);
        }
    }

    async handleRegisterStage1() {
        const emailInput = document.getElementById('reg-email');
        const passwordInput = document.getElementById('reg-password');
        const errorDiv = document.getElementById('register-error');
        const btn = document.getElementById('btn-next-1');
        const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';

        if (!emailInput.value || !passwordInput.value) {
            this.showError(errorDiv, 'Por favor, ingresa tu correo y contraseña.');
            return;
        }

        if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value)){
             this.showError(errorDiv, 'Ingresa un correo válido.');
             return;
        }

        this.setLoading(btn, true);
        this.hideError(errorDiv);

        try {
            // Fíjate que ya no enviamos "action" aquí, la URL lo determina
            const res = await ApiService.post(API_ROUTES.AUTH.CHECK_EMAIL, { 
                email: emailInput.value,
                csrf_token: csrfToken 
            });

            if (res.success) {
                this.switchStage(1, 2);
            } else {
                this.showError(errorDiv, res.message);
            }
        } catch (error) {
            console.error(error);
            this.showError(errorDiv, 'Error de conexión. Inténtalo de nuevo.');
        } finally {
            this.setLoading(btn, false);
        }
    }

    async handleRegisterStage2() {
        const email = document.getElementById('reg-email').value;
        const password = document.getElementById('reg-password').value;
        const username = document.getElementById('reg-username').value;
        const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';
        const errorDiv = document.getElementById('register-error');
        const btn = document.getElementById('btn-next-2');

        if (!username) {
            this.showError(errorDiv, 'El nombre de usuario es obligatorio.');
            return;
        }

        this.setLoading(btn, true);
        this.hideError(errorDiv);

        try {
            const res = await ApiService.post(API_ROUTES.AUTH.SEND_CODE, { 
                email, 
                password, 
                username, 
                csrf_token: csrfToken 
            });

            if (res.success) {
                const codeDisplay = document.getElementById('simulated-code-display');
                if (codeDisplay) {
                    codeDisplay.textContent = res.dev_code;
                }
                this.switchStage(2, 3);
            } else {
                this.showError(errorDiv, res.message);
            }
        } catch (error) {
            console.error(error);
            this.showError(errorDiv, 'Error al generar el código. Inténtalo de nuevo.');
        } finally {
            this.setLoading(btn, false);
        }
    }

    async handleRegisterFinal() {
        const email = document.getElementById('reg-email').value;
        const code = document.getElementById('reg-code').value;
        const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';
        const errorDiv = document.getElementById('register-error');
        const btn = document.getElementById('btn-register-final');

        if (!code || code.length !== 6) {
            this.showError(errorDiv, 'Ingresa el código de 6 dígitos enviado.');
            return;
        }

        this.setLoading(btn, true);
        this.hideError(errorDiv);

        try {
            const res = await ApiService.post(API_ROUTES.AUTH.REGISTER, { 
                email, 
                code, 
                csrf_token: csrfToken 
            });

            if (res.success) {
                window.location.href = '/ProjectAurora/';
            } else {
                this.showError(errorDiv, res.message);
                this.setLoading(btn, false);
            }
        } catch (error) {
            console.error(error);
            this.showError(errorDiv, 'Error de conexión. Inténtalo de nuevo.');
            this.setLoading(btn, false);
        }
    }

    async handleLogin() {
        const form = document.getElementById('form-login');
        const btn = form.querySelector('button[type="submit"]'); 
        
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;
        const csrfTokenInput = document.getElementById('csrf_token');
        const csrfToken = csrfTokenInput ? csrfTokenInput.value : '';

        const errorDiv = document.getElementById('login-error');

        this.setLoading(btn, true);
        this.hideError(errorDiv);

        try {
            const res = await ApiService.post(API_ROUTES.AUTH.LOGIN, { 
                email, 
                password, 
                csrf_token: csrfToken 
            });
            if (res.success) {
                window.location.href = '/ProjectAurora/'; 
            } else {
                this.showError(errorDiv, res.message);
                this.setLoading(btn, false);
            }
        } catch (error) {
            console.error(error);
            this.showError(errorDiv, 'Error de conexión. Inténtalo de nuevo.');
            this.setLoading(btn, false);
        }
    }

    async handleLogout() {
        try {
            await ApiService.post(API_ROUTES.AUTH.LOGOUT);
            window.location.href = '/ProjectAurora/login';
        } catch (error) {
            console.error(error);
        }
    }

    setLoading(btn, isLoading) {
        if(!btn) return;

        if (isLoading) {
            if (!btn.dataset.originalText) {
                btn.dataset.originalText = btn.textContent.trim();
            }
            btn.disabled = true;
            btn.innerHTML = '<div class="component-spinner-button"></div>';
        } else {
            btn.disabled = false;
            const originalText = btn.dataset.originalText || 'Continuar';
            btn.textContent = originalText;
        }
    }

    showError(element, message) {
        if (element) {
            element.textContent = message;
            element.style.display = 'block';
        }
    }

    hideError(element) {
        if (element) {
            element.style.display = 'none';
            element.textContent = '';
        }
    }
}