/**
 * public/assets/js/core/dialog-manager.js
 * Sistema de Diálogos Personalizados (Alert, Confirm, Loading)
 * Reemplaza los nativos del navegador usando Promesas.
 */

export const Dialog = {
    elements: {
        overlay: null,
        wrapper: null,
        templates: null
    },

    init: () => {
        Dialog.elements.overlay = document.getElementById('dialog-overlay');
        Dialog.elements.wrapper = document.getElementById('dialog-content-wrapper');
        Dialog.elements.templates = document.getElementById('dialog-templates');
        
        console.log("DialogManager: Inicializado");
    },

    /**
     * Muestra un mensaje simple (Reemplazo de alert)
     */
    alert: ({ title = 'Atención', message = '', icon = 'info' }) => {
        return new Promise((resolve) => {
            Dialog._render('template-alert', { title, message, icon });
            
            // Configurar botón
            const btn = Dialog.elements.wrapper.querySelector('.btn-accept');
            btn.onclick = () => {
                Dialog.close();
                resolve(true);
            };

            Dialog._show();
        });
    },

    /**
     * Muestra una confirmación (Reemplazo de confirm)
     * Retorna true si acepta, false si cancela.
     */
    confirm: ({ title = '¿Estás seguro?', message = '', type = 'default', confirmText = 'Confirmar', cancelText = 'Cancelar' }) => {
        return new Promise((resolve) => {
            const templateId = type === 'danger' ? 'template-danger' : 'template-confirm';
            Dialog._render(templateId, { title, message });

            // Personalizar textos de botones
            const btnConfirm = Dialog.elements.wrapper.querySelector('.btn-confirm');
            const btnCancel = Dialog.elements.wrapper.querySelector('.btn-cancel');
            
            if(confirmText) btnConfirm.innerText = confirmText;
            if(cancelText) btnCancel.innerText = cancelText;

            // Lógica de botones
            btnConfirm.onclick = () => {
                Dialog.close();
                resolve(true);
            };

            btnCancel.onclick = () => {
                Dialog.close();
                resolve(false);
            };

            Dialog._show();
        });
    },

    /**
     * Muestra un spinner de carga bloqueante
     */
    showLoading: (text = 'Procesando...') => {
        Dialog._render('template-loading', { message: text });
        Dialog._show();
    },

    /**
     * Cierra cualquier diálogo abierto
     */
    close: () => {
        if (Dialog.elements.overlay) {
            Dialog.elements.overlay.classList.remove('active');
            // Limpiar contenido después de la animación
            setTimeout(() => {
                Dialog.elements.wrapper.innerHTML = '';
            }, 200);
        }
    },

    // --- MÉTODOS PRIVADOS ---

    _render: (templateId, data) => {
        if (!Dialog.elements.templates || !Dialog.elements.wrapper) return;

        const template = Dialog.elements.templates.querySelector(`#${templateId}`);
        if (!template) return;

        // Clonar contenido
        Dialog.elements.wrapper.innerHTML = template.innerHTML;
        
        // Inyectar datos
        const elTitle = Dialog.elements.wrapper.querySelector('.dialog-title');
        const elMsg = Dialog.elements.wrapper.querySelector('.dialog-message');
        const elIcon = Dialog.elements.wrapper.querySelector('.dialog-icon');
        const card = Dialog.elements.wrapper;

        if (elTitle && data.title) elTitle.innerText = data.title;
        if (elMsg && data.message) elMsg.innerText = data.message;
        if (elIcon && data.icon) elIcon.innerText = data.icon;

        // Clases de tipo
        card.className = 'dialog-card'; // Reset
        if (templateId === 'template-danger') card.classList.add('dialog-type-danger');
    },

    _show: () => {
        if (Dialog.elements.overlay) {
            Dialog.elements.overlay.classList.add('active');
        }
    }
};