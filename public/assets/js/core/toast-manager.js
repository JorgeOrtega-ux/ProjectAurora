/**
 * public/assets/js/core/toast-manager.js
 * Sistema de notificaciones Toast
 * Lógica: Creación y destrucción dinámica del contenedor #toast-container
 */

export const Toast = {
    init: () => {
        // Ya no creamos el contenedor al inicio.
        console.log("ToastManager: Inicializado (Dinámico - On Demand)");
    },

    /**
     * Muestra una notificación toast
     */
    show: (message, type = 'info', duration = 3000) => {
        // 1. Obtener o crear el contenedor dinámicamente
        let container = document.getElementById('toast-container');
        
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }

        // Comprobar preferencia de duración extendida
        if (window.USER_PREFS && window.USER_PREFS.extended_toast) {
            duration = 10000; // 10 segundos si está activo
        }

        // Crear el elemento toast
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

        // Animación de entrada
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        // Función para eliminar el toast y limpiar el contenedor
        const removeToast = () => {
            // Evitar errores si ya fue eliminado
            if (!toast.parentElement) return;

            toast.classList.remove('show');
            
            // Esperar a que termine la transición CSS (opacity/transform)
            toast.addEventListener('transitionend', () => {
                if (toast.parentElement) {
                    toast.remove();
                }

                // 2. Comprobar si el contenedor quedó vacío para eliminarlo
                const currentContainer = document.getElementById('toast-container');
                if (currentContainer && currentContainer.children.length === 0) {
                    currentContainer.remove();
                }
            }, { once: true }); // Asegura que el listener se ejecute una sola vez
        };

        // Eventos de cierre
        toast.querySelector('.toast-close').addEventListener('click', removeToast);
        setTimeout(removeToast, duration);
    }
};