import { SpaRouter } from './spa-router.js';

export class AuthController {
    constructor() {
        this.apiPath = '/ProjectAurora/api/handler/auth-handler.php';
        this.init();
    }

    init() {
        // Enrutamiento de eventos form submit
        document.body.addEventListener('submit', (e) => {
            if (e.target.id === 'form-login') {
                e.preventDefault();
                this.handleLogin();
            } else if (e.target.id === 'form-register') {
                e.preventDefault();
                this.handleRegisterFinal(); // Ahora se dispara en la etapa 3
            }
        });

        // Interceptores de clics para transiciones y visibilidad de contraseña
        document.body.addEventListener('click', (e) => {
            const logoutBtn = e.target.closest('[data-action="logout"]');
            if (logoutBtn) {
                e.preventDefault();
                this.handleLogout();
                return;
            }

            // Manejo del botón de ver contraseña
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

            // Flujo de Registro (Etapas)
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

    // --- FUNCIONES DE TRANSICIÓN Y MANEJO DE ETAPAS --- //

    switchStage(fromStage, toStage) {
        document.getElementById(`reg-stage-${fromStage}`).style.display = 'none';
        document.getElementById(`reg-stage-${toStage}`).style.display = 'block';
        this.hideError(document.getElementById('register-error'));
        
        // Foco automático para mejorar UX
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

        // Validación básica de email en JS antes de ir a BD
        if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value)){
             this.showError(errorDiv, 'Ingresa un correo válido.');
             return;
        }

        this.setLoading(btn, true);
        this.hideError(errorDiv);

        try {
            // Verificar si el correo ya existe
            const res = await this.post({ 
                action: 'check_email', 
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
            // Solicitar código a la API (guardará el payload en BD temporalmente)
            const res = await this.post({ 
                action: 'send_code', 
                email, 
                password, 
                username, 
                csrf_token: csrfToken 
            });

            if (res.success) {
                // Simulación: Imprimir código en pantalla para que el usuario pueda copiarlo
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
            // Enviar verificación final (crea el usuario)
            const res = await this.post({ 
                action: 'register', 
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

    // --- FLUJOS NORMALES --- //

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
            const res = await this.post({ action: 'login', email, password, csrf_token: csrfToken });
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
            await this.post({ action: 'logout' });
            window.location.href = '/ProjectAurora/login';
        } catch (error) {
            console.error(error);
        }
    }

    async post(data) {
        const response = await fetch(this.apiPath, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return await response.json();
    }

    // --- UI UX --- //
    
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