/**
 * public/assets/js/core/tooltip-manager.js
 * Sistema de Tooltips usando Popper.js
 * Lógica: Creación y destrucción dinámica de elementos en el DOM.
 */

export const TooltipManager = {
    init: () => {
        // Verificación de dependencia
        if (!window.Popper) {
            console.error('Popper.js no encontrado. Tooltips desactivados.');
            return;
        }

        let popperInstance = null;
        let activeTrigger = null;
        let currentTooltipEl = null; // Referencia al elemento DOM actual

        // Función helper para fabricar el nodo HTML
        const createTooltipElement = () => {
            const el = document.createElement('div');
            el.id = 'global-tooltip';
            el.classList.add('tooltip');
            el.setAttribute('role', 'tooltip');
            return el;
        };

        // Mostrar Tooltip (Crear + Insertar)
        const showTooltip = (trigger) => {
            // Limpieza preventiva por si hay un cambio muy rápido entre elementos
            if (currentTooltipEl) {
                removeTooltipFromDOM();
            }

            const text = trigger.dataset.tooltip;
            const shortcut = trigger.dataset.shortcut;
            
            if (!text) return;

            activeTrigger = trigger;

            // 1. Crear elemento dinámicamente
            currentTooltipEl = createTooltipElement();

            // 2. Construir contenido (con o sin atajo de teclado)
            if (shortcut) {
                currentTooltipEl.classList.add('tooltip--with-shortcut');
                currentTooltipEl.innerHTML = `
                    <span class="tooltip-text">${text}</span>
                    <span class="tooltip-shortcut"><kbd>${shortcut}</kbd></span>
                `;
            } else {
                currentTooltipEl.classList.remove('tooltip--with-shortcut');
                currentTooltipEl.innerHTML = `<span class="tooltip-text">${text}</span>`;
            }

            // 3. Insertar en el DOM (Body)
            document.body.appendChild(currentTooltipEl);
            
            // Hacerlo visible (block) para que Popper pueda calcular dimensiones.
            // (El CSS original tiene display: none por defecto)
            currentTooltipEl.style.display = 'block';

            // 4. Configurar Popper.js
            popperInstance = Popper.createPopper(trigger, currentTooltipEl, {
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
            
            // Atributo data-show para posibles transiciones CSS
            currentTooltipEl.setAttribute('data-show', '');
        };

        // Ocultar Tooltip (Destruir + Remover)
        const removeTooltipFromDOM = () => {
            // Destruir instancia de Popper para limpiar listeners internos
            if (popperInstance) {
                popperInstance.destroy();
                popperInstance = null;
            }
            
            // Eliminar el nodo HTML del DOM
            if (currentTooltipEl) {
                currentTooltipEl.remove();
                currentTooltipEl = null;
            }
            
            activeTrigger = null;
        };

        // --- DELEGACIÓN DE EVENTOS ---

        const eventsShow = ['mouseenter', 'focus'];
        const eventsHide = ['mouseleave', 'blur'];

        // Listener para mostrar
        eventsShow.forEach(event => {
            document.body.addEventListener(event, (e) => {
                const trigger = e.target.closest('[data-tooltip]');
                if (trigger) {
                    showTooltip(trigger);
                }
            }, true); // Use capture para asegurar detección
        });

        // Listener para ocultar
        eventsHide.forEach(event => {
            document.body.addEventListener(event, (e) => {
                const trigger = e.target.closest('[data-tooltip]');
                // Solo remover si el evento viene del trigger actualmente activo
                if (trigger && trigger === activeTrigger) {
                    removeTooltipFromDOM();
                }
            }, true);
        });
        
        console.log("TooltipManager: Inicializado (Modo Dinámico - On Demand)");
    }
};