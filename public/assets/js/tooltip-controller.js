// public/assets/js/tooltip-controller.js

export class TooltipController {
    constructor() {
        this.activeTooltip = null;
        this.popperInstance = null;
        this.currentTarget = null;
        this.hideTimeout = null;
        
        // Control de carga del script
        this.isLoadingPopper = false;
        this.popperPromise = null;
        
        this.init();
    }

    init() {
        // 1. Pre-carga silenciosa inicial: 
        // Si entra desde una PC, descargamos Popper de fondo inmediatamente sin esperar al hover.
        this.preloadIfDesktop();

        // 2. Por si el usuario empieza con la ventana pequeña y luego la agranda (Resize)
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                this.preloadIfDesktop();
            }, 250);
        }, { passive: true });

        // 3. Manejo del Hover (Event Delegation)
        document.body.addEventListener('mouseover', async (e) => {
            // Si es pantalla chica (móvil/tablet), cortamos la ejecución (sin tooltips)
            if (window.innerWidth <= 768) return;

            const target = e.target.closest('[data-tooltip]');
            if (target) {
                if (this.currentTarget === target) return;
                this.currentTarget = target;

                // Si es PC, para este momento la promesa de Popper ya debería estar resuelta por el preload.
                // Si por alguna razón de red súper lenta no lo está, entonces sí esperamos.
                if (!window.Popper) {
                    try {
                        await this.loadPopperScript();
                    } catch (error) {
                        console.error(error);
                        return; // Si falla la red, abortamos
                    }
                }

                // Verificamos que el usuario siga con el mouse en el elemento
                if (this.currentTarget === target) {
                    this.showTooltip(target);
                }
            }
        });

        document.body.addEventListener('mouseout', (e) => {
            const target = e.target.closest('[data-tooltip]');
            if (target) {
                // Verificamos que el mouse realmente salió del elemento
                if (!e.relatedTarget || !target.contains(e.relatedTarget)) {
                    this.currentTarget = null;
                    this.hideTooltip();
                }
            }
        });

        document.body.addEventListener('click', (e) => {
            const target = e.target.closest('[data-tooltip]');
            if (target) {
                this.currentTarget = null;
                this.hideTooltip(true);
            }
        });
    }

    // --- Inyección dinámica del script silenciosa ---
    preloadIfDesktop() {
        if (window.innerWidth > 768 && !window.Popper && !this.isLoadingPopper) {
            // Inicia la descarga en segundo plano para que esté listo antes del hover
            this.loadPopperScript().catch(() => {});
        }
    }

    loadPopperScript() {
        if (window.Popper) return Promise.resolve(true);
        if (this.isLoadingPopper) return this.popperPromise;

        this.isLoadingPopper = true;
        this.popperPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/@popperjs/core@2';
            script.async = true; // Se carga de forma asíncrona para no bloquear el renderizado visual
            
            script.onload = () => resolve(true);
            script.onerror = () => reject(new Error('Fallo al cargar Popper.js'));
            
            document.head.appendChild(script);
        });

        return this.popperPromise;
    }

    showTooltip(element) {
        // Limpiamos cualquier tooltip previo forzosamente
        this.hideTooltip(true);

        const text = element.getAttribute('data-tooltip');
        if (!text) return;

        // Crear dinámicamente el DOM
        const tooltipEl = document.createElement('div');
        tooltipEl.className = 'aurora-tooltip';
        tooltipEl.innerHTML = `${text}<div class="aurora-tooltip-arrow" data-popper-arrow></div>`;
        
        document.body.appendChild(tooltipEl);
        this.activeTooltip = tooltipEl;

        // Inicializar Popper.js
        this.popperInstance = window.Popper.createPopper(element, tooltipEl, {
            placement: 'bottom',
            strategy: 'absolute', 
            modifiers: [
                {
                    name: 'offset',
                    options: { offset: [0, 8] },
                },
                {
                    name: 'preventOverflow',
                    options: { boundary: 'viewport' },
                }
            ],
        });

        // Forzar reflow para animación CSS
        void tooltipEl.offsetWidth;
        tooltipEl.classList.add('show');
    }

    hideTooltip(immediate = false) {
        if (!this.activeTooltip) return;

        const tooltipEl = this.activeTooltip;
        const popper = this.popperInstance;

        // Limpiar referencias
        this.activeTooltip = null;
        this.popperInstance = null;
        if (this.hideTimeout) clearTimeout(this.hideTimeout);

        tooltipEl.classList.remove('show');

        // Eliminar del DOM y destruir instancia
        if (immediate) {
            if (popper) popper.destroy();
            if (tooltipEl.parentNode) tooltipEl.remove();
        } else {
            // Esperamos 200ms para la transición CSS
            this.hideTimeout = setTimeout(() => {
                if (popper) popper.destroy();
                if (tooltipEl.parentNode) tooltipEl.remove();
            }, 200);
        }
    }
}