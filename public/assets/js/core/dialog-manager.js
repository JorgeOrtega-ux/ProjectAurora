/**
 * public/assets/js/core/dialog-manager.js
 * Sistema de Diálogos Personalizados
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

            // Si es regen-codes, el título y mensaje ya vienen en el HTML, 
            // pero permitimos sobreescribirlos si se pasan explícitamente.
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
            
            // Foco inteligente
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
            setTimeout(() => {
                Dialog.elements.wrapper.innerHTML = '';
            }, 200);
        }
    },

    _render: (templateId, data) => {
        if (!Dialog.elements.templates || !Dialog.elements.wrapper) return;

        const template = Dialog.elements.templates.querySelector(`#${templateId}`);
        if (!template) return;

        Dialog.elements.wrapper.innerHTML = template.innerHTML;
        
        const elTitle = Dialog.elements.wrapper.querySelector('.dialog-title');
        const elMsg = Dialog.elements.wrapper.querySelector('.dialog-message');
        const card = Dialog.elements.wrapper;

        // Solo sobreescribimos si data.title/message existen y no están vacíos
        // Esto permite usar el texto por defecto del template 'template-regen-codes'
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
    }
};