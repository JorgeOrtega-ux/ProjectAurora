/**
 * public/assets/js/core/toast-manager.js
 * Sistema de notificaciones Toast
 */

export const Toast = {
    init: () => {
        // Verificar si ya existe el contenedor, si no, crearlo
        if (!document.getElementById('toast-container')) {
            const container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }
    },

    /**
     * Muestra una notificación toast
     * @param {string} message - Mensaje a mostrar
     * @param {string} type - 'success', 'error', 'info', 'warning'
     * @param {number} duration - Duración en ms (default 3000)
     */
    show: (message, type = 'info', duration = 3000) => {
        const container = document.getElementById('toast-container');
        if (!container) return;

        // Crear elemento
        const toast = document.createElement('div');
        toast.classList.add('toast-item', type);

        // Icono según tipo
        let iconName = 'info';
        if (type === 'success') iconName = 'check_circle';
        if (type === 'error') iconName = 'error';
        if (type === 'warning') iconName = 'warning';

        toast.innerHTML = `
            <span class="material-symbols-rounded toast-icon">${iconName}</span>
            <span class="toast-message">${message}</span>
            <span class="material-symbols-rounded toast-close">close</span>
        `;

        // Agregar al contenedor
        container.appendChild(toast);

        // Animación de entrada
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        // Lógica de cierre
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