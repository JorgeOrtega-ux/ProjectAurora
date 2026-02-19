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
            this.loadRoute(window.location.pathname);
        });

        // Interceptar todos los clics en enlaces con data-nav
        document.body.addEventListener('click', (e) => {
            const navTarget = e.target.closest('[data-nav]');
            if (navTarget) {
                e.preventDefault();
                
                // Ocultar cualquier módulo emergente que no sea el principal (ej: el de los 3 puntos)
                const module = navTarget.closest('.component-module');
                if (module && module.dataset.module !== 'moduleSurface') {
                    module.classList.add('disabled');
                }
                
                const url = navTarget.dataset.nav;
                this.navigate(url);
            }
        });
        
        // Marcar la ruta actual al iniciar la app
        this.highlightCurrentRoute();
    }

    navigate(url) {
        if (window.location.pathname === url) return;
        window.history.pushState(null, '', url);
        this.loadRoute(url);
    }

    async loadRoute(url) {
        if (this.outlet) {
            this.outlet.innerHTML = ''; 
            this._showLoaderInOutlet();
        }

        // Actualizamos los menús del panel lateral dinámicamente
        this.updateSurfaceMenu(url);

        try {
            const fetchPromise = fetch(url, {
                method: 'GET',
                headers: { 'X-SPA-Request': 'true' }
            });
            // Mantenemos el pequeño delay visual que tenías
            const delayPromise = new Promise(resolve => setTimeout(resolve, 200));
            const [response] = await Promise.all([fetchPromise, delayPromise]);

            if (response.ok) {
                const html = await response.text();
                this.render(html);
                
                // Remarcamos la ruta de forma global después de cargar la vista
                this.highlightCurrentRoute();
                
                // Emitimos un evento para que el auth-controller lo intercepte
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
        
        // 1. Limpiamos la clase 'active' de TODOS los enlaces de navegación
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
        
        // 2. Buscamos TODOS los enlaces que coincidan con la ruta actual (querySelectorAll en vez de querySelector)
        // Esto resuelve el conflicto entre el enlace del dropdown del usuario y el panel lateral
        const targets = document.querySelectorAll(`[data-nav="${path}"], [data-nav="${path}/"]`);
        
        // 3. Aplicamos la clase 'active' a todos los encontrados
        targets.forEach(target => {
            target.classList.add('active');
        });

        // Revalidamos el menú lateral (por si se entró recargando la página directamente en una sub-ruta)
        this.updateSurfaceMenu(path);
    }

    updateSurfaceMenu(url) {
        const mainAppMenu = document.getElementById('menu-surface-main');
        const settingsMenu = document.getElementById('menu-surface-settings');
        if (!mainAppMenu || !settingsMenu) return;

        // Alternar la visibilidad de los menús en el módulo Surface
        if (url.includes('/settings/')) {
            mainAppMenu.style.display = 'none';
            settingsMenu.style.display = 'flex';
        } else {
            mainAppMenu.style.display = 'flex';
            settingsMenu.style.display = 'none';
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