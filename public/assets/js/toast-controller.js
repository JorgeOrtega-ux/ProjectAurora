// public/assets/js/toast-controller.js

export class Toast {
    /**
     * Muestra una notificación Toast en la pantalla.
     * @param {string} message - El mensaje a mostrar.
     * @param {string} type - 'success' o 'error'.
     */
    static show(message, type = 'success') {
        // 1. Buscar o crear el contenedor global de Toasts
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }

        // 2. Crear el elemento Toast
        const toast = document.createElement('div');
        toast.className = `component-toast component-toast--${type}`;
        
        // Asignar el icono dependiendo del tipo
        const iconName = type === 'success' ? 'check_circle' : 'error';
        
        toast.innerHTML = `
            <span class="material-symbols-rounded">${iconName}</span>
            <span class="component-toast-message">${message}</span>
        `;

        // 3. Añadirlo al DOM
        container.appendChild(toast);

        // 4. Forzar el reflow para que la animación de CSS funcione correctamente
        void toast.offsetWidth;
        
        // 5. Mostrar (deslizar hacia adentro)
        toast.classList.add('show');

        // 6. Ocultar y remover después de 3.5 segundos
        setTimeout(() => {
            toast.classList.remove('show');
            // Esperar a que termine la transición de CSS para removerlo del DOM
            toast.addEventListener('transitionend', () => {
                if (toast.parentNode) {
                    toast.remove();
                }
            });
        }, 3500);
    }
}