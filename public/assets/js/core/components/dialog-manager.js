import { DialogTemplates } from './dialog-definitions.js';

const DialogManager = {
    elements: {
        overlay: null,
        container: null, 
        wrapper: null
    },
    
    cleanupTimer: null,

    init: () => {
    },

    _injectDOM: () => {
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
        
        DialogManager.elements.overlay = overlay;
        DialogManager.elements.container = overlay.querySelector('.component-dialog-wrapper');
        DialogManager.elements.wrapper = overlay.querySelector('.component-dialog');

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay || e.target === DialogManager.elements.container) {
                DialogManager.close();
            }
        });
    },

    _ensureReady: () => {
        if (!DialogManager.elements.wrapper || !document.body.contains(DialogManager.elements.overlay)) {
            DialogManager._injectDOM();
        }
        return !!DialogManager.elements.wrapper;
    },

    alert: ({ title = 'Atención', message = '' }) => {
        return new Promise((resolve) => {
            if (!DialogManager._ensureReady()) return resolve(true);

            DialogManager._render('default', { 
                title, 
                message, 
                confirmText: 'Aceptar',
                cancelText: null 
            });
            
            const btnCancel = DialogManager.elements.wrapper.querySelector('[data-action="cancel"]');
            if (btnCancel) btnCancel.style.display = 'none';

            const btnConfirm = DialogManager.elements.wrapper.querySelector('[data-action="confirm"]');
            if (btnConfirm) {
                btnConfirm.onclick = () => { DialogManager.close(); resolve(true); };
                setTimeout(() => btnConfirm.focus(), 50);
            }
            DialogManager._show();
        });
    },

    confirm: ({ title, message, type = 'default', confirmText, cancelText, onReady }) => {
        return new Promise((resolve) => {
            if (!DialogManager._ensureReady()) return resolve(false);

            let templateKey = 'default';
            if (type === 'regen-codes') templateKey = 'regen-codes';
            if (type === 'verify-email') templateKey = 'verify-email';
            
            DialogManager._render(templateKey, { title, message, confirmText, cancelText });

            const btnConfirm = DialogManager.elements.wrapper.querySelector('[data-action="confirm"]');
            const cancelButtons = DialogManager.elements.wrapper.querySelectorAll('[data-action="cancel"]');

            if (btnConfirm) {
                if (type === 'danger') {
                    btnConfirm.style.backgroundColor = 'var(--color-error)';
                    btnConfirm.style.borderColor = 'var(--color-error)';
                    btnConfirm.style.color = '#fff';
                }
                
                btnConfirm.onclick = () => { 
                    const input = DialogManager.elements.wrapper.querySelector('#verify-email-code');
                    const resolution = input ? input.value : true;
                    DialogManager.close(); 
                    resolve(resolution); 
                };
            }

            cancelButtons.forEach(btn => {
                btn.onclick = () => { DialogManager.close(); resolve(false); };
            });

            DialogManager._show();
            
            if (type === 'verify-email') {
                setTimeout(() => {
                    const input = DialogManager.elements.wrapper.querySelector('input');
                    if (input) input.focus();
                }, 50);
            } else if (btnConfirm) {
                setTimeout(() => btnConfirm.focus(), 50);
            }

            if (typeof onReady === 'function') {
                onReady(DialogManager.elements.wrapper);
            }
        });
    },

    showLoading: (text = 'Procesando...') => {
        if (!DialogManager._ensureReady()) return;
        DialogManager._render('loading', { title: text });
        DialogManager._show();
    },

    close: () => {
        const overlay = DialogManager.elements.overlay;
        const container = DialogManager.elements.container;

        if (overlay) {
            const isMobile = window.innerWidth <= 468;
            
            if (isMobile && container) {
                container.classList.add('closing');
                
                setTimeout(() => {
                    destroyDOM();
                }, 280); 
            } else {
                overlay.classList.remove('active');
                setTimeout(() => {
                    destroyDOM();
                }, 200);
            }
        }

        function destroyDOM() {
            if (overlay && overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
            DialogManager.elements.overlay = null;
            DialogManager.elements.container = null;
            DialogManager.elements.wrapper = null;
        }
    },

    _render: (templateKey, data) => {
        const wrapper = DialogManager.elements.wrapper;
        const pillHTML = `<div class="component-dialog-drag-zone" data-action="drag-handle"><div class="component-dialog-drag-handle"></div></div>`;
        
        let renderFn = DialogTemplates[templateKey];
        if (!renderFn) renderFn = DialogTemplates['default'];
        
        wrapper.innerHTML = pillHTML + renderFn(data);
        DialogManager._bindDragEvents();

        const elMsg = wrapper.querySelector('[data-element="message"]');
        if (elMsg) {
            elMsg.style.display = data.message ? 'block' : 'none';
        }
        
        wrapper.className = 'component-dialog';
    },

    _show: () => {
        if (DialogManager.cleanupTimer) clearTimeout(DialogManager.cleanupTimer);
        void DialogManager.elements.overlay.offsetWidth; 
        DialogManager.elements.overlay.classList.add('active');
    },

    _bindDragEvents: () => {
        const handle = DialogManager.elements.wrapper.querySelector('.component-dialog-drag-zone');
        const container = DialogManager.elements.container; 
        
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
            
            const threshold = Math.min(containerHeight * 0.4, 150);
            
            if (currentY > threshold) {
                DialogManager.close(); 
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
    }
};

export { DialogManager };