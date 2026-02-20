// public/assets/js/tooltip-controller.js

export class TooltipController {
    constructor() {
        this.activeTooltip = null;
        this.popperInstance = null;
        this.currentTarget = null;
        this.hideTimeout = null;
        
        this.init();
    }

    init() {
        // Usamos Event Delegation para soportar elementos dinámicos
        document.body.addEventListener('mouseover', (e) => {
            const target = e.target.closest('[data-tooltip]');
            if (target) {
                // Previene que se dispare varias veces si el mouse se mueve dentro del mismo botón
                if (this.currentTarget === target) return;
                this.currentTarget = target;
                this.showTooltip(target);
            }
        });

        document.body.addEventListener('mouseout', (e) => {
            const target = e.target.closest('[data-tooltip]');
            if (target) {
                // Verificamos que el mouse realmente salió del elemento (no hacia un hijo)
                if (!e.relatedTarget || !target.contains(e.relatedTarget)) {
                    this.currentTarget = null;
                    this.hideTooltip();
                }
            }
        });

        // Ocultar rápidamente al hacer click o perder foco (blur)
        document.body.addEventListener('click', (e) => {
            const target = e.target.closest('[data-tooltip]');
            if (target) {
                this.currentTarget = null;
                this.hideTooltip(true);
            }
        });
    }

    showTooltip(element) {
        // Si ya hay un tooltip en pantalla, forzamos su destrucción inmediata
        this.hideTooltip(true);

        const text = element.getAttribute('data-tooltip');
        if (!text) return;

        // 1. Crear dinámicamente el DOM
        const tooltipEl = document.createElement('div');
        tooltipEl.className = 'aurora-tooltip';
        tooltipEl.innerHTML = `${text}<div class="aurora-tooltip-arrow" data-popper-arrow></div>`;
        
        // 2. Insertarlo directamente en el body (Position Absolute/Fixed garantiza que no rompa layouts)
        document.body.appendChild(tooltipEl);
        this.activeTooltip = tooltipEl;

        // 3. Inicializar Popper.js
        this.popperInstance = window.Popper.createPopper(element, tooltipEl, {
            placement: 'auto',
            strategy: 'absolute', // Popper controlará que flote y no ocupe espacio
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

        // 4. Forzar reflow para asegurar la animación CSS de entrada
        void tooltipEl.offsetWidth;
        tooltipEl.classList.add('show');
    }

    hideTooltip(immediate = false) {
        if (!this.activeTooltip) return;

        const tooltipEl = this.activeTooltip;
        const popper = this.popperInstance;

        // Limpiar referencias en la clase
        this.activeTooltip = null;
        this.popperInstance = null;
        if (this.hideTimeout) clearTimeout(this.hideTimeout);

        tooltipEl.classList.remove('show');

        // Eliminar del DOM y destruir instancia
        if (immediate) {
            if (popper) popper.destroy();
            if (tooltipEl.parentNode) tooltipEl.remove();
        } else {
            // Esperamos 200ms para que termine la transición CSS de opacidad antes de borrar el div
            this.hideTimeout = setTimeout(() => {
                if (popper) popper.destroy();
                if (tooltipEl.parentNode) tooltipEl.remove();
            }, 200);
        }
    }
}