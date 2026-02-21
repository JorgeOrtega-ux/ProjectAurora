// public/assets/js/components/dialog-controller.js
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

        // HTML reestructurado para incluir la barra de arrastre y un contenedor de contenido
        overlay.innerHTML = `
            <div class="component-dialog-box">
                <div class="pill-container"><div class="drag-handle"></div></div>
                <div class="component-dialog-content">
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
            </div>
        `;

        document.body.appendChild(overlay);

        // --- LÓGICA DE DRAG & DROP (SWIPE TO DISMISS) ---
        const box = overlay.querySelector('.component-dialog-box');
        const pill = overlay.querySelector('.pill-container');

        if (pill && box) {
            let isDragging = false;
            let startY = 0;
            let currentDiff = 0;

            pill.addEventListener('pointerdown', (e) => {
                // Solo activamos el drag en resoluciones móviles (coincidiendo con el CSS)
                if (window.innerWidth > 468) return;
                // Ignorar clics secundarios del ratón
                if (e.pointerType === 'mouse' && e.button !== 0) return;

                isDragging = true;
                startY = e.clientY;
                box.style.transition = 'none'; // Desactivar transición suave para seguir el dedo instantáneamente
                box.setPointerCapture(e.pointerId);
            });

            box.addEventListener('pointermove', (e) => {
                if (!isDragging) return;
                if (e.cancelable) e.preventDefault();

                const diff = e.clientY - startY;
                // Solo permitimos arrastrar hacia abajo
                if (diff > 0) {
                    box.style.transform = `translateY(${diff}px)`;
                    currentDiff = diff;
                }
            });

            const endDrag = (e) => {
                if (!isDragging) return;
                isDragging = false;
                
                // Restaurar la transición CSS original
                box.style.transition = 'transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1)';
                
                if (box.hasPointerCapture(e.pointerId)) {
                    box.releasePointerCapture(e.pointerId);
                }

                // Si se arrastró más del 40% del alto, cerrar el diálogo
                const threshold = box.offsetHeight * 0.40; 
                if (currentDiff > threshold) {
                    this.close(dialogId);
                } else {
                    box.style.transform = ''; // Vuelve a la posición de apertura
                }
                currentDiff = 0;
            };

            box.addEventListener('pointerup', endDrag);
            box.addEventListener('pointercancel', endDrag);
        }

        // Forzar reflow para que la animación CSS (fade in/scale) se ejecute correctamente
        void overlay.offsetWidth;
        overlay.classList.add('active');
    }

    close(dialogId) {
        const dialog = document.getElementById(dialogId);
        if (dialog) {
            dialog.classList.remove('active');
            
            // Forzar transform en móvil para animar la salida hacia abajo si fue arrastrado
            const box = dialog.querySelector('.component-dialog-box');
            if (box && window.innerWidth <= 468) {
                box.style.transform = 'translateY(100%)';
            }

            // Eliminar del DOM después de la animación de cierre (300ms)
            setTimeout(() => {
                dialog.remove();
            }, 300);
        }
    }
}