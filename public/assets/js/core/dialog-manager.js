/**
 * public/assets/js/core/dialog-manager.js
 * Sistema de Diálogos Dinámico (Just-in-Time Injection).
 */

import { DialogTemplates } from './dialog-definitions.js';

export const Dialog = {
    _overlay: null,
    _cleanupTimer: null,

    init: () => {
        console.log("DialogManager: Inicializado (JIT Mode)");
    },

    /**
     * Muestra un diálogo de alerta simple.
     */
    alert: ({ title = 'Atención', message = '' }) => {
        return Dialog._createDialog({
            title,
            message,
            buttons: [{ text: 'Aceptar', action: 'confirm', class: 'primary' }]
        });
    },

    /**
     * Muestra un diálogo de confirmación o acción compleja.
     */
    confirm: ({ title, message, type = 'default', confirmText = 'Confirmar', cancelText = 'Cancelar', onReady }) => {
        const isDanger = type === 'danger';
        
        return Dialog._createDialog({
            title,
            message,
            type,
            onReady, // Callback para inicializar lógica custom (ej: timers)
            buttons: [
                { text: cancelText, action: 'cancel', class: '' },
                { text: confirmText, action: 'confirm', class: isDanger ? 'primary' : 'primary' } // CSS class handler
            ]
        });
    },

    /**
     * Muestra un diálogo de carga bloqueante.
     */
    showLoading: (text = 'Procesando...') => {
        Dialog._createOverlay();
        const wrapper = Dialog._overlay.querySelector('.component-dialog-wrapper');
        
        // Renderizado simplificado para Loading
        wrapper.innerHTML = `<div class="component-dialog">${DialogTemplates.LOADING}</div>`;
        const titleEl = wrapper.querySelector('[data-element="title"]');
        if (titleEl) titleEl.textContent = text;

        requestAnimationFrame(() => Dialog._overlay.classList.add('active'));
    },

    /**
     * Cierra el diálogo actual y limpia el DOM.
     */
    close: () => {
        if (!Dialog._overlay) return;

        Dialog._overlay.classList.remove('active');
        
        // Esperar animación CSS (0.2s) antes de eliminar del DOM
        Dialog._cleanupTimer = setTimeout(() => {
            if (Dialog._overlay) {
                Dialog._overlay.remove();
                Dialog._overlay = null;
            }
        }, 200);
    },

    // --- MÉTODOS PRIVADOS ---

    _createOverlay: () => {
        if (Dialog._overlay) return; // Ya existe

        const overlay = document.createElement('div');
        overlay.id = 'dialog-overlay'; // ID solo para el contenedor raíz por CSS
        overlay.className = 'component-overlay';
        
        overlay.innerHTML = `
            <div class="component-dialog-wrapper">
                </div>
        `;
        
        document.body.appendChild(overlay);
        Dialog._overlay = overlay;
        
        // Setup Drag para Mobile
        Dialog._initDragLogic(overlay.querySelector('.component-dialog-wrapper'));
    },

    _createDialog: ({ title, message, type, buttons, onReady }) => {
        return new Promise((resolve) => {
            Dialog._createOverlay();
            const wrapper = Dialog._overlay.querySelector('.component-dialog-wrapper');
            
            // 1. Inyectar Estructura Base
            wrapper.innerHTML = `<div class="component-dialog">${DialogTemplates.BASE}</div>`;
            const dialog = wrapper.querySelector('.component-dialog');

            // 2. Llenar Datos
            const elTitle = dialog.querySelector('[data-element="title"]');
            const elMsg = dialog.querySelector('[data-element="message"]');
            const elContent = dialog.querySelector('[data-element="content-area"]');
            const elFooter = dialog.querySelector('[data-element="footer"]');

            if (elTitle) elTitle.textContent = title || '';
            if (elMsg) {
                if (message) elMsg.textContent = message;
                else elMsg.style.display = 'none';
            }

            // 3. Inyectar Contenido Específico (Templates)
            if (type === 'verify-email') {
                elContent.innerHTML = DialogTemplates.VERIFY_EMAIL;
            } else if (type === 'regen-codes') {
                elContent.innerHTML = ''; // Si se necesita template específico
            }

            // 4. Generar Botones
            buttons.forEach(btnConfig => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `component-button ${btnConfig.class}`;
                btn.textContent = btnConfig.text;
                btn.dataset.action = btnConfig.action;
                
                // Evento Click
                btn.onclick = () => {
                    if (btnConfig.action === 'cancel') {
                        Dialog.close();
                        resolve(false);
                    } else {
                        // Capturar input si existe (para verify-email)
                        const input = dialog.querySelector('[data-element="input-code"]');
                        const result = input ? input.value : true;
                        
                        Dialog.close();
                        resolve(result);
                    }
                };
                elFooter.appendChild(btn);
            });

            // 5. Animación de Entrada
            requestAnimationFrame(() => {
                Dialog._overlay.classList.add('active');
                
                // Auto-foco inteligente
                const input = dialog.querySelector('input');
                const confirmBtn = dialog.querySelector('[data-action="confirm"]');
                const cancelBtn = dialog.querySelector('[data-action="cancel"]');

                if (input) input.focus();
                else if (type === 'danger' && cancelBtn) cancelBtn.focus();
                else if (confirmBtn) confirmBtn.focus();
            });

            // 6. Callback de inicialización (para lógica custom como timers)
            if (typeof onReady === 'function') {
                onReady(dialog);
            }
        });
    },

    _initDragLogic: (container) => {
        // Lógica de arrastre móvil (Swipe to close)
        let startY = 0, currentY = 0, isDragging = false;

        container.addEventListener('touchstart', (e) => {
            if (window.innerWidth > 468 || !e.target.closest('[data-action="drag-handle"]')) return;
            startY = e.touches[0].clientY;
            isDragging = true;
            container.style.transition = 'none';
        }, { passive: false });

        container.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            const deltaY = e.touches[0].clientY - startY;
            if (deltaY > 0) {
                if(e.cancelable) e.preventDefault();
                container.style.transform = `translateY(${deltaY}px)`;
                currentY = deltaY;
            }
        }, { passive: false });

        container.addEventListener('touchend', () => {
            if (!isDragging) return;
            isDragging = false;
            container.style.transition = 'transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1)';
            
            if (currentY > 100) {
                Dialog.close(); // Se considera cancelación
            } else {
                container.style.transform = '';
            }
            currentY = 0;
        });
    }
};