// public/assets/js/dialog-controller.js
import { DIALOG_CONFIG } from './dialog-config.js';

export class DialogController {
    constructor() {
        this.init();
    }

    init() {
        // Manejador global de eventos para cerrar dialogos
        document.body.addEventListener('click', (e) => {
            const btnClose = e.target.closest('[data-action="close-dialog"]');
            if (btnClose) {
                e.preventDefault();
                this.close(btnClose.dataset.target);
                return;
            }

            // Cerrar al hacer click en el overlay oscuro exterior
            if (e.target.classList.contains('component-dialog-overlay')) {
                this.close(e.target.id);
            }
        });
    }

    open(dialogId) {
        const config = DIALOG_CONFIG[dialogId];
        if (!config) {
            console.error(`Configuración de diálogo no encontrada para: ${dialogId}`);
            return;
        }

        // Eliminar si ya existe uno en el DOM para evitar duplicados
        let existing = document.getElementById(dialogId);
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.className = 'component-dialog-overlay';
        overlay.id = dialogId;

        let buttonsHtml = '';
        if (config.buttons && config.buttons.length > 0) {
            buttonsHtml = '<div class="component-dialog-footer">';
            config.buttons.forEach(btn => {
                const idAttr = btn.id ? `id="${btn.id}"` : '';
                const actionAttr = btn.action === 'close' ? `data-action="close-dialog" data-target="${dialogId}"` : '';
                const styleAttr = btn.style ? `style="${btn.style}"` : '';
                buttonsHtml += `<button class="${btn.class}" ${idAttr} ${actionAttr} ${styleAttr}>${btn.text}</button>`;
            });
            buttonsHtml += '</div>';
        }

        overlay.innerHTML = `
            <div class="component-dialog-box">
                <div class="component-dialog-header">
                    <h3 class="component-dialog-title">${config.title}</h3>
                    <button class="component-dialog-close" data-action="close-dialog" data-target="${dialogId}">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>
                <div class="component-dialog-body">
                    ${config.body}
                </div>
                ${buttonsHtml}
            </div>
        `;

        document.body.appendChild(overlay);

        // Forzar reflow para que la animación CSS (fade in/scale) se ejecute correctamente
        void overlay.offsetWidth;
        overlay.classList.add('active');
    }

    close(dialogId) {
        const dialog = document.getElementById(dialogId);
        if (dialog) {
            dialog.classList.remove('active');
            // Eliminar del DOM después de la animación de cierre (300ms)
            setTimeout(() => {
                dialog.remove();
            }, 300);
        }
    }
}