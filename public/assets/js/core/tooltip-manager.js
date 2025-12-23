/**
 * public/assets/js/core/tooltip-manager.js
 * Sistema de Tooltips usando Popper.js (Posicionamiento Automático)
 */

export const TooltipManager = {
    init: () => {
        if (!window.Popper) {
            console.error('Popper.js no encontrado. Tooltips desactivados.');
            return;
        }

        // Crear el elemento tooltip único en el DOM si no existe
        let tooltipEl = document.getElementById('global-tooltip');
        if (!tooltipEl) {
            tooltipEl = document.createElement('div');
            tooltipEl.id = 'global-tooltip';
            tooltipEl.classList.add('tooltip');
            tooltipEl.setAttribute('role', 'tooltip');
            document.body.appendChild(tooltipEl);
        }

        let popperInstance = null;
        let activeTrigger = null;

        const showTooltip = (trigger) => {
            const text = trigger.dataset.tooltip;
            const shortcut = trigger.dataset.shortcut;
            
            // Ya no leemos data-placement, será automático

            if (!text) return;

            activeTrigger = trigger;

            // Construir contenido
            if (shortcut) {
                tooltipEl.classList.add('tooltip--with-shortcut');
                tooltipEl.innerHTML = `
                    <span class="tooltip-text">${text}</span>
                    <span class="tooltip-shortcut"><kbd>${shortcut}</kbd></span>
                `;
            } else {
                tooltipEl.classList.remove('tooltip--with-shortcut');
                tooltipEl.innerHTML = `<span class="tooltip-text">${text}</span>`;
            }

            tooltipEl.style.display = 'block';

            // Configuración de Popper con placement: 'auto'
            popperInstance = Popper.createPopper(trigger, tooltipEl, {
                placement: 'auto',
                modifiers: [
                    {
                        name: 'offset',
                        options: {
                            offset: [0, 8],
                        },
                    },
                    {
                        name: 'preventOverflow',
                        options: {
                            padding: 8,
                        },
                    },
                ],
            });
            
            // Atributo para controlar visibilidad/estilo
            tooltipEl.setAttribute('data-show', '');
        };

        const hideTooltip = () => {
            tooltipEl.style.display = 'none';
            tooltipEl.removeAttribute('data-show');
            if (popperInstance) {
                popperInstance.destroy();
                popperInstance = null;
            }
            activeTrigger = null;
        };

        // Delegación de eventos
        const eventsShow = ['mouseenter', 'focus'];
        const eventsHide = ['mouseleave', 'blur'];

        eventsShow.forEach(event => {
            document.body.addEventListener(event, (e) => {
                const trigger = e.target.closest('[data-tooltip]');
                if (trigger) {
                    showTooltip(trigger);
                }
            }, true);
        });

        eventsHide.forEach(event => {
            document.body.addEventListener(event, (e) => {
                const trigger = e.target.closest('[data-tooltip]');
                if (trigger && trigger === activeTrigger) {
                    hideTooltip();
                }
            }, true);
        });
        
        console.log("TooltipManager: Inicializado (Auto Placement)");
    }
};