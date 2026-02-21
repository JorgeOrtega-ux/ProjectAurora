// public/assets/js/spa-router.js
export class SpaRouter {
    constructor(options = {}) {
        this.outlet = document.querySelector(options.outlet || '#app-router-outlet');
        this.basePath = '/ProjectAurora';
        this.init();
    }

    init() {
        // Escuchar retroceso/avance del navegador
        window.addEventListener('popstate', (e) => {
            const url = window.location.pathname;
            const noHeader = !document.querySelector('.general-content-top');
            const isAuthRoute = ['/login', '/register', '/forgot-password', '/reset-password'].some(route => url.includes(route));
            
            // Si las variables no coinciden, significa que estamos saltando el límite SPA y ocupamos recargar el layout entero
            if ((noHeader && !isAuthRoute) || (!noHeader && isAuthRoute)) {
                window.location.reload(); 
                return;
            }
            this.loadRoute(url);
        });

        // Interceptar todos los clics en enlaces con data-nav
        document.body.addEventListener('click', (e) => {
            const navTarget = e.target.closest('[data-nav]');
            if (navTarget) {
                e.preventDefault();
                
                // Ocultar cualquier módulo emergente que no sea el principal
                const module = navTarget.closest('.component-module');
                if (module && module.dataset.module !== 'moduleSurface') {
                    module.classList.add('disabled');
                }
                
                const url = navTarget.dataset.nav;
                this.navigate(url);
            }
        });
        
        this.highlightCurrentRoute();
    }

    navigate(url) {
        if (window.location.pathname === url) return;

        // Comprobación de límites (evitar fallos al navegar hacia un layout diferente con JS)
        const noHeader = !document.querySelector('.general-content-top');
        const isAuthRoute = ['/login', '/register', '/forgot-password', '/reset-password'].some(route => url.includes(route));
        
        if ((noHeader && !isAuthRoute) || (!noHeader && isAuthRoute)) {
            window.location.href = url; // Full reload
            return;
        }

        window.history.pushState(null, '', url);
        this.loadRoute(url);
    }

    async loadRoute(url) {
        if (this.outlet) {
            this.outlet.innerHTML = ''; 
            this._showLoaderInOutlet();
        }

        this.updateSurfaceMenu(url);

        try {
            const fetchPromise = fetch(url, {
                method: 'GET',
                headers: { 'X-SPA-Request': 'true' }
            });
            const delayPromise = new Promise(resolve => setTimeout(resolve, 200));
            const [response] = await Promise.all([fetchPromise, delayPromise]);

            // Módulo de protección: Manejar redirecciones silenciosas desde el backend (Router)
            const redirectUrl = response.headers.get('X-SPA-Redirect');
            if (redirectUrl) {
                window.location.href = redirectUrl; // Forzar recarga a la url permitida
                return;
            }

            if (response.ok) {
                const html = await response.text();
                this.render(html);
                this.highlightCurrentRoute();
                window.dispatchEvent(new CustomEvent('viewLoaded', { detail: { url } }));
            } else {
                this.render('<div class="view-content"><h1>Error</h1></div>');
            }
        } catch (error) {
            this.render('<div class="view-content"><h1>Error de Red</h1></div>');
        }
    }

    render(html) {
        if (this.outlet) {
            this.outlet.innerHTML = html;
            this.outlet.scrollTop = 0;
        }
    }

    highlightCurrentRoute() {
        const path = window.location.pathname;
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
        
        const targets = document.querySelectorAll(`[data-nav="${path}"], [data-nav="${path}/"]`);
        targets.forEach(target => {
            target.classList.add('active');
        });

        this.updateSurfaceMenu(path);
    }

    updateSurfaceMenu(url) {
        const mainAppMenu = document.getElementById('menu-surface-main');
        const settingsMenu = document.getElementById('menu-surface-settings');
        const adminMenu = document.getElementById('menu-surface-admin'); // Seleccionamos el nuevo menú admin

        // Verificamos que al menos existan main y settings
        if (!mainAppMenu || !settingsMenu) return;

        if (url.includes('/settings/')) {
            // Si está en settings, muestra solo settings
            mainAppMenu.style.display = 'none';
            settingsMenu.style.display = 'flex';
            if (adminMenu) adminMenu.style.display = 'none';
            
        } else if (url.includes('/admin/')) {
            // Si está en admin, muestra solo admin
            mainAppMenu.style.display = 'none';
            settingsMenu.style.display = 'none';
            if (adminMenu) adminMenu.style.display = 'flex';
            
        } else {
            // Rutas por defecto (Home, Explore)
            mainAppMenu.style.display = 'flex';
            settingsMenu.style.display = 'none';
            if (adminMenu) adminMenu.style.display = 'none';
        }
    }

    _showLoaderInOutlet() {
        const loaderContainer = document.createElement('div');
        loaderContainer.className = 'content-loader-container';
        const spinner = document.createElement('div');
        spinner.className = 'spinner';
        loaderContainer.appendChild(spinner);
        this.outlet.appendChild(loaderContainer);
    }
}