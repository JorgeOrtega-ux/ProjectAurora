/**
 * public/assets/js/core/toast-manager.js
 * Sistema de notificaciones Toast
 */

export const Toast = {
    init: () => {
        if (!document.getElementById('toast-container')) {
            const container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }
    },

    /**
     * Muestra una notificación toast
     */
    show: (message, type = 'info', duration = 3000) => {
        const container = document.getElementById('toast-container');
        if (!container) return;

        // Comprobar preferencia de duración extendida
        if (window.USER_PREFS && window.USER_PREFS.extended_toast) {
            duration = 10000; // 10 segundos si está activo
        }

        const toast = document.createElement('div');
        toast.classList.add('toast-item', type);

        let iconName = 'info';
        if (type === 'success') iconName = 'check_circle';
        if (type === 'error') iconName = 'error';
        if (type === 'warning') iconName = 'warning';

        toast.innerHTML = `
            <span class="material-symbols-rounded toast-icon">${iconName}</span>
            <span class="toast-message">${message}</span>
            <span class="material-symbols-rounded toast-close">close</span>
        `;

        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        const removeToast = () => {
            toast.classList.remove('show');
            toast.addEventListener('transitionend', () => {
                toast.remove();
            });
        };

        toast.querySelector('.toast-close').addEventListener('click', removeToast);
        setTimeout(removeToast, duration);
    }
};