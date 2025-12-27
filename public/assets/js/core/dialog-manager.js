/**
 * public/assets/js/core/dialog-manager.js
 * Sistema de Diálogos Personalizados con Drag & Drop (Swipe to close)
 */

export const Dialog = {
    elements: {
        overlay: null,
        wrapper: null,
        container: null, 
        templates: null,
        pill: null 
    },
    
    // Timer para la limpieza del DOM
    cleanupTimer: null,

    init: () => {
        Dialog.elements.overlay = document.getElementById('dialog-overlay');
        Dialog.elements.container = document.querySelector('.dialog-container');
        Dialog.elements.wrapper = document.getElementById('dialog-content-wrapper');
        Dialog.elements.templates = document.getElementById('dialog-templates');
        
        if (Dialog.elements.container) {
            Dialog._initDragLogic();
        }

        console.log("DialogManager: Inicializado");
    },

    alert: ({ title = 'Atención', message = '' }) => {
        return new Promise((resolve) => {
            Dialog._render('template-alert', { title, message });
            
            const btn = Dialog.elements.wrapper.querySelector('.btn-accept');
            if (btn) {
                btn.onclick = () => {
                    Dialog.close();
                    resolve(true);
                };
                setTimeout(() => btn.focus(), 50);
            }
            Dialog._show();
        });
    },

    confirm: ({ title, message, type = 'default', confirmText, cancelText }) => {
        return new Promise((resolve) => {
            let templateId = 'template-confirm';
            if (type === 'danger') templateId = 'template-danger';
            if (type === 'regen-codes') templateId = 'template-regen-codes';
            // Soporte para el nuevo tipo verify-email
            if (type === 'verify-email') templateId = 'template-verify-email';

            Dialog._render(templateId, { title, message });

            const btnConfirm = Dialog.elements.wrapper.querySelector('.btn-confirm');
            const btnCancel = Dialog.elements.wrapper.querySelector('.btn-cancel');
            
            if(confirmText && btnConfirm) btnConfirm.innerText = confirmText;
            if(cancelText && btnCancel) btnCancel.innerText = cancelText;

            if (btnConfirm) {
                btnConfirm.onclick = () => {
                    Dialog.close();
                    resolve(true);
                };
            }

            if (btnCancel) {
                btnCancel.onclick = () => {
                    Dialog.close();
                    resolve(false);
                };
            }

            Dialog._show();
            
            // Foco automático
            if (type === 'verify-email') {
                setTimeout(() => {
                    const input = Dialog.elements.wrapper.querySelector('input');
                    if (input) input.focus();
                }, 50);
            } else if (type === 'danger' && btnCancel) {
                setTimeout(() => btnCancel.focus(), 50);
            } else if (btnConfirm) {
                setTimeout(() => btnConfirm.focus(), 50);
            }
        });
    },

    showLoading: (text = 'Procesando...') => {
        Dialog._render('template-loading', { title: text });
        Dialog._show();
    },

    close: () => {
        if (Dialog.elements.overlay) {
            Dialog.elements.overlay.classList.remove('active');
            
            if (Dialog.elements.container) {
                Dialog.elements.container.classList.remove('closing');
                Dialog.elements.container.style.transform = ''; 
            }

            // CORRECCIÓN: Guardamos el timer para poder cancelarlo si se abre otro dialog rápidamente
            if (Dialog.cleanupTimer) clearTimeout(Dialog.cleanupTimer);
            
            Dialog.cleanupTimer = setTimeout(() => {
                if (Dialog.elements.wrapper) {
                    Dialog.elements.wrapper.innerHTML = ''; 
                }
            }, 200);
        }
    },

    _render: (templateId, data) => {
        if (!Dialog.elements.templates || !Dialog.elements.wrapper) return;

        // CORRECCIÓN: Cancelar limpieza pendiente si abrimos un diálogo inmediatamente después de cerrar otro
        if (Dialog.cleanupTimer) clearTimeout(Dialog.cleanupTimer);

        const template = Dialog.elements.templates.querySelector(`#${templateId}`);
        if (!template) return;

        const pillHTML = `
            <div class="dialog-pill-container">
                <div class="dialog-drag-handle"></div>
            </div>
        `;

        Dialog.elements.wrapper.innerHTML = pillHTML + template.innerHTML;
        Dialog._bindDragEvents();

        const elTitle = Dialog.elements.wrapper.querySelector('.dialog-title');
        const elMsg = Dialog.elements.wrapper.querySelector('.dialog-message');
        const card = Dialog.elements.wrapper;

        if (elTitle && data.title) elTitle.innerText = data.title;
        if (elMsg && data.message) {
            elMsg.innerText = data.message;
            elMsg.style.display = 'block';
        }

        card.className = 'dialog-card';
    },

    _show: () => {
        // Aseguramos cancelación del timer aquí también por seguridad
        if (Dialog.cleanupTimer) clearTimeout(Dialog.cleanupTimer);
        
        if (Dialog.elements.overlay) {
            Dialog.elements.overlay.classList.add('active');
        }
    },

    _bindDragEvents: () => {
        const handle = Dialog.elements.wrapper.querySelector('.dialog-pill-container');
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
            const threshold = Math.min(containerHeight * 0.3, 100);

            if (currentY > threshold) {
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
            
            const onMouseMove = (evt) => moveDrag(evt.clientY, evt);
            const onMouseUp = () => {
                endDrag();
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
            };
            
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });
    },

    _initDragLogic: () => {}
};