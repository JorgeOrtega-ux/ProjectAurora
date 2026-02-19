// public/assets/js/spa-router.js
export class SpaRouter {
    constructor(options = {}) {
        this.outlet = document.querySelector(options.outlet || '#app-router-outlet');
        this.basePath = '/ProjectAurora';
        // Se eliminaron las líneas que forzaban transition: none y opacity: 1
        this.init();
    }

    init() {
        window.addEventListener('popstate', (e) => {
            this.loadRoute(window.location.pathname);
        });

        document.body.addEventListener('click', (e) => {
            const navTarget = e.target.closest('[data-nav]');
            if (navTarget) {
                e.preventDefault();
                const module = navTarget.closest('.component-module');
                if(module && module.dataset.module !== 'moduleSurface') module.classList.add('disabled');
                
                const url = navTarget.dataset.nav;
                this.navigate(url);
                this.updateActiveNav(navTarget);
            }
        });
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

        this.updateSurfaceMenu(url);

        try {
            const fetchPromise = fetch(url, {
                method: 'GET',
                headers: { 'X-SPA-Request': 'true' }
            });
            // SE MANTIENEN LOS 200ms INTACTOS COMO PEDISTE
            const delayPromise = new Promise(resolve => setTimeout(resolve, 200));
            const [response] = await Promise.all([fetchPromise, delayPromise]);

            if (response.ok) {
                const html = await response.text();
                this.render(html);
                this.highlightCurrentRoute();
                
                // NUEVO: Emitimos un evento para avisar que la vista se cargó
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

    updateActiveNav(targetElement) {
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
        if(targetElement) targetElement.classList.add('active');
    }

    highlightCurrentRoute() {
        const path = window.location.pathname;
        const target = document.querySelector(`[data-nav="${path}"]`) || document.querySelector(`[data-nav="${path}/"]`);
        if(target) this.updateActiveNav(target);
        this.updateSurfaceMenu(path);
    }

    updateSurfaceMenu(url) {
        const mainAppMenu = document.getElementById('menu-surface-main');
        const settingsMenu = document.getElementById('menu-surface-settings');
        if (!mainAppMenu || !settingsMenu) return;

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