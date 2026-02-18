// public/assets/js/auth-controller.js
import { SpaRouter } from './spa-router.js'; // Para redireccionar

export class AuthController {
    constructor() {
        this.apiPath = '/ProjectAurora/api/handler/auth-handler.php';
        this.init();
    }

    init() {
        // Escuchar eventos globales (porque las vistas login/register se cargan dinámicamente)
        document.body.addEventListener('submit', (e) => {
            if (e.target.id === 'form-login') {
                e.preventDefault();
                this.handleLogin();
            } else if (e.target.id === 'form-register') {
                e.preventDefault();
                this.handleRegister();
            }
        });

        // Escuchar click en logout
        document.body.addEventListener('click', (e) => {
            const logoutBtn = e.target.closest('[data-action="logout"]');
            if (logoutBtn) {
                e.preventDefault();
                this.handleLogout();
            }
        });

        // Chequear sesión al iniciar
        this.checkSession();
    }

    async handleLogin() {
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;
        const btn = document.querySelector('#form-login button');
        
        this.setLoading(btn, true);

        try {
            const res = await this.post({ action: 'login', email, password });
            if (res.success) {
                this.updateUI(res.user);
                // Redirigir al home forzando la navegación del router
                window.history.pushState(null, '', '/ProjectAurora/');
                // Pequeño hack para disparar el evento popstate o recargar la vista
                const event = new PopStateEvent('popstate');
                window.dispatchEvent(event);
            } else {
                alert(res.message);
            }
        } catch (error) {
            console.error(error);
        } finally {
            this.setLoading(btn, false);
        }
    }

    async handleRegister() {
        const username = document.getElementById('reg-username').value;
        const email = document.getElementById('reg-email').value;
        const password = document.getElementById('reg-password').value;
        const btn = document.querySelector('#form-register button');

        this.setLoading(btn, true);

        try {
            const res = await this.post({ action: 'register', username, email, password });
            if (res.success) {
                this.updateUI(res.user);
                window.history.pushState(null, '', '/ProjectAurora/');
                const event = new PopStateEvent('popstate');
                window.dispatchEvent(event);
            } else {
                alert(res.message);
            }
        } catch (error) {
            console.error(error);
        } finally {
            this.setLoading(btn, false);
        }
    }

    async handleLogout() {
        try {
            await this.post({ action: 'logout' });
            this.resetUI();
            window.location.href = '/ProjectAurora/login';
        } catch (error) {
            console.error(error);
        }
    }

    async checkSession() {
        try {
            const res = await this.post({ action: 'check_session' });
            if (res.success) {
                this.updateUI(res.user);
            } else {
                this.resetUI();
            }
        } catch (error) {
            // No hacer nada si falla check session silencioso
        }
    }

    updateUI(user) {
        document.body.classList.add('is-logged-in');
        
        // Actualizar avatar
        const avatarImg = document.getElementById('user-avatar-img');
        if (avatarImg && user.avatar) {
            avatarImg.src = user.avatar;
        }
    }

    resetUI() {
        document.body.classList.remove('is-logged-in');
    }

    async post(data) {
        const response = await fetch(this.apiPath, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return await response.json();
    }

    setLoading(btn, isLoading) {
        if(btn) {
            btn.disabled = isLoading;
            btn.textContent = isLoading ? 'Cargando...' : 'Continuar';
        }
    }
}