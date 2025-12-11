/**
 * TooltipService.js
 * Maneja la visualización de tooltips usando Popper.js
 */

export const initTooltipService = () => {
    // 1. Crear el elemento tooltip único en el DOM si no existe
    let tooltipEl = document.getElementById('global-tooltip');
    if (!tooltipEl) {
        tooltipEl = document.createElement('div');
        tooltipEl.id = 'global-tooltip';
        tooltipEl.className = 'tooltip';
        // Estructura interna para texto y atajo
        tooltipEl.innerHTML = `
            <span class="tooltip-text"></span>
            <div class="tooltip-shortcut" style="display:none;"></div>
        `;
        document.body.appendChild(tooltipEl);
    }

    const textEl = tooltipEl.querySelector('.tooltip-text');
    const shortcutEl = tooltipEl.querySelector('.tooltip-shortcut');
    let popperInstance = null;

    // Función para mostrar
    const showTooltip = (target) => {
        const text = target.dataset.tooltip;
        const shortcut = target.dataset.shortcut;

        if (!text) return;

        // Actualizar contenido
        textEl.textContent = text;

        if (shortcut) {
            shortcutEl.style.display = 'block';
            shortcutEl.innerHTML = `<kbd>${shortcut}</kbd>`;
            tooltipEl.classList.add('tooltip--with-shortcut');
        } else {
            shortcutEl.style.display = 'none';
            tooltipEl.classList.remove('tooltip--with-shortcut');
        }

        tooltipEl.style.display = 'block';

        // Crear instancia Popper (Usando variable global Popper cargada desde CDN en index.php)
        if (typeof Popper !== 'undefined') {
            popperInstance = Popper.createPopper(target, tooltipEl, {
                placement: 'bottom',
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
        } else {
            console.warn('Popper.js no está cargado.');
        }
    };

    // Función para ocultar
    const hideTooltip = () => {
        tooltipEl.style.display = 'none';
        if (popperInstance) {
            popperInstance.destroy();
            popperInstance = null;
        }
    };

    // 2. Delegación de eventos (CORREGIDO)
    // Se agregan validaciones para evitar el error "closest is not a function"

    document.addEventListener('mouseenter', (e) => {
        // Validamos que e.target exista y tenga la función closest
        if (e.target && typeof e.target.closest === 'function') {
            const target = e.target.closest('[data-tooltip]');
            if (target) {
                showTooltip(target);
            }
        }
    }, true);

    document.addEventListener('mouseleave', (e) => {
        // Validamos que e.target exista y tenga la función closest
        if (e.target && typeof e.target.closest === 'function') {
            const target = e.target.closest('[data-tooltip]');
            if (target) {
                hideTooltip();
            }
        }
    }, true);

    // Ocultar al hacer click (UX: para que no estorbe la acción)
    document.addEventListener('click', (e) => {
        hideTooltip();
    });

    console.log('TooltipService: Inicializado.');
};