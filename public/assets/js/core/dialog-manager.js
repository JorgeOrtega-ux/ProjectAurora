/**
 * public/assets/js/core/dialog-manager.js
 * Sistema de Diálogos Personalizados con Drag & Drop (Swipe to close)
 */

export const Dialog = {
    elements: {
        overlay: null,
        wrapper: null,
        container: null, // Referencia al contenedor que se mueve
        templates: null,
        pill: null // Referencia al handle
    },

    init: () => {
        Dialog.elements.overlay = document.getElementById('dialog-overlay');
        Dialog.elements.container = document.querySelector('.dialog-container');
        Dialog.elements.wrapper = document.getElementById('dialog-content-wrapper');
        Dialog.elements.templates = document.getElementById('dialog-templates');
        
        // Inicializar lógica de arrastre si existe el contenedor
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
            
            if (type === 'danger' && btnCancel) setTimeout(() => btnCancel.focus(), 50);
            else if (btnConfirm) setTimeout(() => btnConfirm.focus(), 50);
        });
    },

    showLoading: (text = 'Procesando...') => {
        Dialog._render('template-loading', { title: text });
        Dialog._show();
    },

    close: () => {
        if (Dialog.elements.overlay) {
            Dialog.elements.overlay.classList.remove('active');
            
            // Si estaba en estado "closing" por drag, lo limpiamos
            if (Dialog.elements.container) {
                Dialog.elements.container.classList.remove('closing');
                Dialog.elements.container.style.transform = ''; // Resetear transformaciones manuales
            }

            setTimeout(() => {
                // Limpiar contenido pero MANTENER el pill-container
                // La función _render se encarga de no borrar el pill container si está estructurado fuera
                // Pero como el pill está DENTRO de dialog-card en el HTML estático, 
                // debemos tener cuidado de no perder la referencia si el innerHTML reemplaza todo.
                
                // En la implementación actual, _render reemplaza todo el innerHTML del wrapper.
                // Como el pill está en el PHP estático dentro de .dialog-card, 
                // al hacer _render se sobreescribe. 
                // SOLUCIÓN: El pill container ya está en el layout base del PHP.
                // Sin embargo, _render inyecta templates.
                // REVISIÓN: El pill debe inyectarse en _render si no existe, o preservarse.
                
                // Simplificación: Limpiamos todo, y _render se asegura de que el HTML inyectado sea correcto.
                // NOTA: Para que el Drag funcione entre aperturas, el Pill debe ser parte del Template o re-inyectado.
                // Dado que pusimos el pill en el PHP base, pero _render hace `wrapper.innerHTML = template.innerHTML`,
                // el pill se perdería.
                
                // CORRECCIÓN EN _render: Ver abajo.
                Dialog.elements.wrapper.innerHTML = ''; 
            }, 200);
        }
    },

    _render: (templateId, data) => {
        if (!Dialog.elements.templates || !Dialog.elements.wrapper) return;

        const template = Dialog.elements.templates.querySelector(`#${templateId}`);
        if (!template) return;

        // 1. Guardar el Pill Container si existe (para no perderlo al sobreescribir)
        // O mejor: Reconstruirlo siempre.
        const pillHTML = `
            <div class="dialog-pill-container">
                <div class="dialog-drag-handle"></div>
            </div>
        `;

        // 2. Insertar contenido (Pill + Template)
        Dialog.elements.wrapper.innerHTML = pillHTML + template.innerHTML;
        
        // 3. Re-vincular eventos del Pill (el DOM cambió)
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
        if (Dialog.elements.overlay) {
            Dialog.elements.overlay.classList.add('active');
        }
    },

    // === LÓGICA DE DRAG AND DROP (Swipe to close) ===
    _bindDragEvents: () => {
        const handle = Dialog.elements.wrapper.querySelector('.dialog-pill-container');
        const container = Dialog.elements.container;

        if (!handle || !container) return;

        let startY = 0;
        let currentY = 0;
        let isDragging = false;
        let containerHeight = 0;

        const startDrag = (clientY) => {
            // Solo activar en móvil (layout bottom sheet)
            if (window.innerWidth > 468) return;
            
            startY = clientY;
            containerHeight = container.offsetHeight;
            isDragging = true;
            container.style.transition = 'none'; // Desactivar transición para movimiento fluido
        };

        const moveDrag = (clientY, event) => {
            if (!isDragging) return;
            
            const deltaY = clientY - startY;
            
            // Solo permitir arrastrar hacia abajo (positivo)
            if (deltaY > 0) {
                if (event.cancelable) event.preventDefault(); // Evitar scroll de la página
                container.style.transform = `translateY(${deltaY}px)`;
                currentY = deltaY;
            }
        };

        const endDrag = () => {
            if (!isDragging) return;
            isDragging = false;
            
            // Restaurar transición CSS
            container.style.transition = 'transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1)';
            
            // Umbral de cierre (si se arrastra más del 30% de la altura o 100px)
            const threshold = Math.min(containerHeight * 0.3, 100);

            if (currentY > threshold) {
                // Completar el cierre visualmente
                container.classList.add('closing');
                Dialog.close(); // Ejecutar lógica de cierre real
            } else {
                // Rebotar a la posición original
                container.style.transform = '';
            }
            
            currentY = 0;
        };

        // Eventos Touch
        handle.addEventListener('touchstart', (e) => startDrag(e.touches[0].clientY), { passive: false });
        handle.addEventListener('touchmove', (e) => moveDrag(e.touches[0].clientY, e), { passive: false });
        handle.addEventListener('touchend', endDrag);

        // Eventos Mouse (Opcional, para testear en desktop con modo responsive)
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

    _initDragLogic: () => {
        // Wrapper para inicialización si fuera necesario
    }
};