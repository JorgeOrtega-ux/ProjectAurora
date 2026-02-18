import { SpaRouter } from './spa-router.js';

export class AuthController {
    constructor() {
        this.apiPath = '/ProjectAurora/api/handler/auth-handler.php';
        this.init();
    }

    init() {
        document.body.addEventListener('submit', (e) => {
            if (e.target.id === 'form-login') {
                e.preventDefault();
                this.handleLogin();
            } else if (e.target.id === 'form-register') {
                e.preventDefault();
                this.handleRegister();
            }
        });

        // Toggle Password Visibility Logic
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
                } else if (input) {
                    input.type = 'password';
                    icon.textContent = 'visibility';
                }
            }
        });
    }

    async handleLogin() {
        const form = document.getElementById('form-login');
        // --- CORRECCIÓN: Seleccionar explícitamente el botón de submit ---
        const btn = form.querySelector('button[type="submit"]'); 
        
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;
        const errorDiv = document.getElementById('login-error');

        this.setLoading(btn, true);
        this.hideError(errorDiv);

        try {
            const res = await this.post({ action: 'login', email, password });
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

    async handleRegister() {
        const form = document.getElementById('form-register');
        // --- CORRECCIÓN: Seleccionar explícitamente el botón de submit ---
        const btn = form.querySelector('button[type="submit"]');

        const username = document.getElementById('reg-username').value;
        const email = document.getElementById('reg-email').value;
        const password = document.getElementById('reg-password').value;
        const errorDiv = document.getElementById('register-error');

        this.setLoading(btn, true);
        this.hideError(errorDiv);

        try {
            const res = await this.post({ action: 'register', username, email, password });
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

    // --- LÓGICA DE SPINNER ---
    setLoading(btn, isLoading) {
        if(!btn) return;

        if (isLoading) {
            // Guardamos el texto original en un atributo data
            if (!btn.dataset.originalText) {
                btn.dataset.originalText = btn.textContent.trim();
            }
            // Deshabilitamos
            btn.disabled = true;
            // Reemplazamos contenido por el spinner HTML
            btn.innerHTML = '<div class="component-spinner-button"></div>';
        } else {
            // Restauramos
            btn.disabled = false;
            // Recuperamos el texto original
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