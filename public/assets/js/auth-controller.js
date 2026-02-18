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

        document.body.addEventListener('click', (e) => {
            const logoutBtn = e.target.closest('[data-action="logout"]');
            if (logoutBtn) {
                e.preventDefault();
                this.handleLogout();
            }
        });
        
        // Ya no necesitamos checkSession() para la UI inicial, 
        // porque PHP ya pintó los botones correctos.
    }

    async handleLogin() {
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;
        const btn = document.querySelector('#form-login button');
        
        this.setLoading(btn, true);

        try {
            const res = await this.post({ action: 'login', email, password });
            if (res.success) {
                // --- CAMBIO CLAVE: Recargar en lugar de SPA ---
                window.location.href = '/ProjectAurora/'; 
            } else {
                alert(res.message);
                this.setLoading(btn, false);
            }
        } catch (error) {
            console.error(error);
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
                // --- CAMBIO CLAVE: Recargar en lugar de SPA ---
                window.location.href = '/ProjectAurora/';
            } else {
                alert(res.message);
                this.setLoading(btn, false);
            }
        } catch (error) {
            console.error(error);
            this.setLoading(btn, false);
        }
    }

    async handleLogout() {
        try {
            await this.post({ action: 'logout' });
            // Al hacer logout, recargamos al login o al home
            window.location.href = '/ProjectAurora/login';
        } catch (error) {
            console.error(error);
        }
    }

    // --- FUNCIONES QUE YA NO SON NECESARIAS SI RECARGAS ---
    // updateUI(user) { ...borrar... }
    // resetUI() { ...borrar... }

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