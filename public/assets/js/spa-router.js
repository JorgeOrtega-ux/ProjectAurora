export class SpaRouter {
    constructor(options = {}) {
        this.outlet = document.querySelector(options.outlet || '#app-router-outlet');
        this.basePath = '/ProjectAurora';
        
        // Eliminamos cualquier estilo de transición previo
        if (this.outlet) {
            this.outlet.style.transition = 'none';
            this.outlet.style.opacity = '1';
        }

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
        // 1. INMEDIATO: Mostrar Loader y BORRAR contenido viejo
        this._createLoader();
        
        if (this.outlet) {
            this.outlet.innerHTML = ''; // Se elimina la sección vieja al instante
        }

        try {
            // 2. ESPERA: Fetch + Tiempo mínimo (200ms)
            const fetchPromise = fetch(url, {
                method: 'GET',
                headers: { 'X-SPA-Request': 'true' }
            });

            const delayPromise = new Promise(resolve => setTimeout(resolve, 200));

            const [response] = await Promise.all([fetchPromise, delayPromise]);

            // 3. CARGA: Insertar contenido nuevo
            if (response.ok) {
                const html = await response.text();
                this.render(html);
            } else {
                console.error('Error:', response.status);
                this.render('<div class="view-content"><h1>Error</h1><p>No se pudo cargar.</p></div>');
            }

        } catch (error) {
            console.error('Network error:', error);
            this.render('<div class="view-content"><h1>Error de Red</h1></div>');
        } finally {
            // 4. LIMPIEZA: Quitar loader
            this._removeLoader();
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
        const target = document.querySelector(`[data-nav="${path}"]`) || 
                       document.querySelector(`[data-nav="${path}/"]`);
        if(target) this.updateActiveNav(target);
    }

    /* --- LOADER SPINNER --- */

    _createLoader() {
        if (document.getElementById('dynamic-loader')) return;

        const loader = document.createElement('div');
        loader.id = 'dynamic-loader';
        loader.className = 'loader-overlay'; 
        loader.classList.add('active'); // Activo directamente, sin esperas
        
        const spinner = document.createElement('div');
        spinner.className = 'spinner';
        
        loader.appendChild(spinner);
        document.body.appendChild(loader);
    }

    _removeLoader() {
        const loader = document.getElementById('dynamic-loader');
        if (loader && loader.parentNode) {
            loader.parentNode.removeChild(loader);
        }
    }
}