// public/assets/js/tooltip-manager.js
import { createPopper } from 'https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/esm/popper.min.js';

// Eliminamos i18n-manager porque en este proyecto usaremos el texto directo
// import { getTranslation } from './i18n-manager.js'; 

let tooltipEl;
let popperInstance;

function createTooltipElementInstance() {
    const newTooltipEl = document.createElement('div');
    newTooltipEl.id = 'main-tooltip';
    newTooltipEl.className = 'tooltip';
    newTooltipEl.setAttribute('role', 'tooltip');

    const textEl = document.createElement('div');
    textEl.className = 'tooltip-text';
    newTooltipEl.appendChild(textEl);

    return newTooltipEl;
}

function showTooltip(target) {
    // ADAPTACIÓN: Obtenemos el texto directamente del atributo data-tooltip
    // en lugar de usar una key de traducción.
    const tooltipText = target.getAttribute('data-tooltip');
    if (!tooltipText) return;

    tooltipEl = createTooltipElementInstance();
    document.body.appendChild(tooltipEl);

    // ADAPTACIÓN: Insertamos el texto directo
    tooltipEl.querySelector('.tooltip-text').textContent = tooltipText;
    tooltipEl.style.display = 'block';

    popperInstance = createPopper(target, tooltipEl, {
      placement: 'bottom',
        placement: 'auto',
        modifiers: [
            {
                name: 'offset',
                options: {
                    offset: [0, 8], // Tu offset original
                },
            },
            {
                name: 'preventOverflow', // Añadido recomendado para evitar que se salga de pantalla en móbiles
                options: {
                    padding: 8,
                },
            },
        ],
    });
}

function hideTooltip() {
    if (popperInstance) {
        popperInstance.destroy();
        popperInstance = null;
    }

    if (tooltipEl && tooltipEl.parentNode) {
        tooltipEl.parentNode.removeChild(tooltipEl);
        tooltipEl = null;
    }
}

export function initTooltipManager() {
    const isCoarsePointer = window.matchMedia && window.matchMedia("(pointer: coarse)").matches;

    if (isCoarsePointer) {
        return;
    }

    document.body.addEventListener('mouseover', (e) => {
        const target = e.target.closest('[data-tooltip]');
        if (!target) return;
        showTooltip(target);
    });

    document.body.addEventListener('mouseout', (e) => {
        const target = e.target.closest('[data-tooltip]');
        if (!target) return;
        hideTooltip();
    });
    
    // ADAPTACIÓN: Es bueno ocultarlo también al hacer clic para que no se quede pegado
    document.body.addEventListener('click', () => {
        hideTooltip();
    });
}