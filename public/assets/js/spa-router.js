export class SpaRouter {
    constructor(options = {}) {
        this.outlet = document.querySelector(options.outlet || '#app-router-outlet');
        this.basePath = '/ProjectAurora';
        
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
        // 1. Limpiar contenido actual
        if (this.outlet) {
            this.outlet.innerHTML = ''; 
            // 2. Mostrar loader DENTRO del outlet
            this._showLoaderInOutlet();
        }

        try {
            const fetchPromise = fetch(url, {
                method: 'GET',
                headers: { 'X-SPA-Request': 'true' }
            });

            // Mantenemos el delay mínimo para evitar parpadeos muy rápidos
            const delayPromise = new Promise(resolve => setTimeout(resolve, 200));

            const [response] = await Promise.all([fetchPromise, delayPromise]);

            if (response.ok) {
                const html = await response.text();
                // 3. Renderizar sobrescribe el loader automáticamente
                this.render(html);
            } else {
                console.error('Error:', response.status);
                this.render('<div class="view-content"><h1>Error</h1><p>No se pudo cargar.</p></div>');
            }

        } catch (error) {
            console.error('Network error:', error);
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
        const target = document.querySelector(`[data-nav="${path}"]`) || 
                       document.querySelector(`[data-nav="${path}/"]`);
        if(target) this.updateActiveNav(target);
    }

    /* --- LOADER INTERNO --- */
    
    _showLoaderInOutlet() {
        // Creamos el contenedor del loader
        const loaderContainer = document.createElement('div');
        loaderContainer.className = 'content-loader-container';
        
        const spinner = document.createElement('div');
        spinner.className = 'spinner';
        
        loaderContainer.appendChild(spinner);
        
        // Lo añadimos al outlet (que ya fue vaciado)
        this.outlet.appendChild(loaderContainer);
    }
}