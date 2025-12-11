/**
 * ToastService.js
 * Sistema de notificaciones flotantes (Toasts).
 */

const createContainerIfNeeded = () => {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }
    return container;
};

export const Toast = {
    /**
     * Muestra un toast.
     * @param {string} message - El mensaje a mostrar.
     * @param {string} type - 'success', 'error', 'info'.
     * @param {number} duration - Tiempo en ms (base).
     */
    show: (message, type = 'info', duration = 4000) => {
        const container = createContainerIfNeeded();
        
        // MODIFICADO: Verificar preferencia de alertas extendidas
        // Si el usuario tiene 'extended_alerts' activado, aumentamos considerablemente la duración (ej. 10s)
        if (window.USER_PREFS && window.USER_PREFS.extended_alerts === 1) {
            duration = 10000;
        }
        
        // Configuración de iconos según tipo
        let iconName = 'info';
        if (type === 'success') iconName = 'check_circle';
        if (type === 'error') iconName = 'error';

        // Crear elemento
        const toastEl = document.createElement('div');
        toastEl.className = `toast ${type}`;
        
        toastEl.innerHTML = `
            <div class="toast-icon">
                <span class="material-symbols-rounded">${iconName}</span>
            </div>
            <div class="toast-message">${message}</div>
        `;

        // Agregar al DOM
        container.appendChild(toastEl);

        // Timer para eliminar
        setTimeout(() => {
            toastEl.classList.add('hiding');
            toastEl.addEventListener('animationend', () => {
                if (toastEl.parentElement) {
                    toastEl.remove();
                }
            });
        }, duration);
    },

    success: (message) => Toast.show(message, 'success'),
    error: (message) => Toast.show(message, 'error'),
    info: (message) => Toast.show(message, 'info')
};