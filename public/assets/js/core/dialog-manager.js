/**
 * public/assets/js/core/dialog-manager.js
 * Sistema de Diálogos Dinámicos (On-Demand)
 * Se crea el DOM al abrir y se elimina al cerrar.
 */

import { DialogTemplates } from './dialog-definitions.js';

export const Dialog = {
    elements: {
        overlay: null,
        container: null, 
        wrapper: null
    },
    
    cleanupTimer: null,

    // 1. MODIFICADO: init ya no inyecta nada al cargar la página
    init: () => {
        console.log("DialogManager: Listo (Modo On-Demand)");
    },

    // Crea el HTML solo cuando se necesita
    _injectDOM: () => {
        // Verificar si ya existe para no duplicar
        if (document.getElementById('dialog-overlay')) return;

        const overlay = document.createElement('div');
        overlay.id = 'dialog-overlay';
        overlay.className = 'component-overlay';
        overlay.innerHTML = `
            <div class="component-dialog-wrapper">
                <div class="component-dialog" id="dialog-content-wrapper"></div>
            </div>
        `;
        document.body.appendChild(overlay);
        
        // Guardar referencias
        Dialog.elements.overlay = overlay;
        Dialog.elements.container = overlay.querySelector('.component-dialog-wrapper');
        Dialog.elements.wrapper = overlay.querySelector('.component-dialog');
    },

    // Verifica si el DOM existe, si no, lo crea
    _ensureReady: () => {
        if (!Dialog.elements.wrapper || !document.body.contains(Dialog.elements.overlay)) {
            Dialog._injectDOM();
        }
        // Doble verificación por seguridad
        return !!Dialog.elements.wrapper;
    },

    alert: ({ title = 'Atención', message = '' }) => {
        return new Promise((resolve) => {
            if (!Dialog._ensureReady()) return resolve(true);

            Dialog._render('default', { 
                title, 
                message, 
                confirmText: 'Aceptar',
                // Ocultar cancelar para alertas simples
                cancelText: null 
            });
            
            // Ocultar botón cancelar manualmente si es alerta
            const btnCancel = Dialog.elements.wrapper.querySelector('[data-action="cancel"]');
            if (btnCancel) btnCancel.style.display = 'none';

            const btnConfirm = Dialog.elements.wrapper.querySelector('[data-action="confirm"]');
            if (btnConfirm) {
                btnConfirm.onclick = () => { Dialog.close(); resolve(true); };
                setTimeout(() => btnConfirm.focus(), 50);
            }
            Dialog._show();
        });
    },

    confirm: ({ title, message, type = 'default', confirmText, cancelText, onReady }) => {
        return new Promise((resolve) => {
            if (!Dialog._ensureReady()) return resolve(false);

            let templateKey = 'default';
            if (type === 'regen-codes') templateKey = 'regen-codes';
            if (type === 'verify-email') templateKey = 'verify-email';
            
            Dialog._render(templateKey, { title, message, confirmText, cancelText });

            const btnConfirm = Dialog.elements.wrapper.querySelector('[data-action="confirm"]');
            const cancelButtons = Dialog.elements.wrapper.querySelectorAll('[data-action="cancel"]');

            if (btnConfirm) {
                if (type === 'danger') {
                    btnConfirm.style.backgroundColor = 'var(--color-error)';
                    btnConfirm.style.borderColor = 'var(--color-error)';
                    btnConfirm.style.color = '#fff';
                }
                btnConfirm.onclick = () => { Dialog.close(); resolve(true); };
            }

            cancelButtons.forEach(btn => {
                btn.onclick = () => { Dialog.close(); resolve(false); };
            });

            Dialog._show();
            
            if (type === 'verify-email') {
                setTimeout(() => {
                    const input = Dialog.elements.wrapper.querySelector('input');
                    if (input) input.focus();
                }, 50);
            } else if (btnConfirm) {
                setTimeout(() => btnConfirm.focus(), 50);
            }

            if (typeof onReady === 'function') {
                onReady(Dialog.elements.wrapper);
            }
        });
    },

    showLoading: (text = 'Procesando...') => {
        if (!Dialog._ensureReady()) return;
        Dialog._render('loading', { title: text });
        Dialog._show();
    },

    close: () => {
        const overlay = Dialog.elements.overlay;
        const container = Dialog.elements.container;

        if (overlay) {
            overlay.classList.remove('active');
            if (container) {
                container.classList.remove('closing');
                container.style.transform = ''; 
            }
            
            if (Dialog.cleanupTimer) clearTimeout(Dialog.cleanupTimer);
            
            // 2. MODIFICADO: Destrucción total al cerrar
            Dialog.cleanupTimer = setTimeout(() => {
                if (overlay && overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
                // Limpiar referencias
                Dialog.elements.overlay = null;
                Dialog.elements.container = null;
                Dialog.elements.wrapper = null;
            }, 200); // Esperar que termine la transición CSS (0.2s)
        }
    },

    _render: (templateKey, data) => {
        const wrapper = Dialog.elements.wrapper;
        const pillHTML = `<div class="component-dialog-drag-zone" data-action="drag-handle"><div class="component-dialog-drag-handle"></div></div>`;
        
        let renderFn = DialogTemplates[templateKey];
        if (!renderFn) renderFn = DialogTemplates['default'];
        
        wrapper.innerHTML = pillHTML + renderFn(data);
        Dialog._bindDragEvents();

        const elMsg = wrapper.querySelector('[data-element="message"]');
        if (elMsg) {
            elMsg.style.display = data.message ? 'block' : 'none';
        }
        
        wrapper.className = 'component-dialog';
    },

    _show: () => {
        if (Dialog.cleanupTimer) clearTimeout(Dialog.cleanupTimer);
        // Forzar reflow para que la animación CSS funcione en elementos recién creados
        void Dialog.elements.overlay.offsetWidth; 
        Dialog.elements.overlay.classList.add('active');
    },

    _bindDragEvents: () => {
        // ... (Tu lógica de arrastre existente se mantiene igual) ...
        const handle = Dialog.elements.wrapper.querySelector('.component-dialog-drag-zone');
        const container = Dialog.elements.container;
        if (!handle || !container) return;

        let startY = 0;
        let currentY = 0;
        let isDragging = false;
        let containerHeight = 0;

        const startDrag = (clientY) => {
            if (window.innerWidth > 468) return;
            startY = clientY;
            containerHeight = container.offsetHeight;
            isDragging = true;
            container.style.transition = 'none'; 
        };

        const moveDrag = (clientY, event) => {
            if (!isDragging) return;
            const deltaY = clientY - startY;
            if (deltaY > 0) {
                if (event.cancelable) event.preventDefault(); 
                container.style.transform = `translateY(${deltaY}px)`;
                currentY = deltaY;
            }
        };

        const endDrag = () => {
            if (!isDragging) return;
            isDragging = false;
            container.style.transition = 'transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1)';
            if (currentY > Math.min(containerHeight * 0.3, 100)) {
                container.classList.add('closing');
                Dialog.close(); 
            } else {
                container.style.transform = '';
            }
            currentY = 0;
        };

        handle.addEventListener('touchstart', (e) => startDrag(e.touches[0].clientY), { passive: false });
        handle.addEventListener('touchmove', (e) => moveDrag(e.touches[0].clientY, e), { passive: false });
        handle.addEventListener('touchend', endDrag);
        
        handle.addEventListener('mousedown', (e) => {
            startDrag(e.clientY);
            const onMove = (ev) => moveDrag(ev.clientY, ev);
            const onUp = () => {
                endDrag();
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    },

    _initDragLogic: () => {}
};