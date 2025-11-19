// assets/js/alert-manager.js

/**
 * Clase para gestionar la creación y destrucción de alertas
 * en la esquina de la pantalla de forma dinámica.
 */
export class AlertManager {

    /**
     * @param {string} containerId ID del contenedor.
     * @param {number} animationDuration Duración (ms) de la animación CSS.
     */
    constructor(containerId = 'alert-container', animationDuration = 500) {
        this.containerId = containerId;
        this.animationDuration = animationDuration;
        this.alertContainer = null;

        // ELIMINADO: this.initContainer(); 
        // Ya no creamos el contenedor al instanciar la clase.
    }

    /**
     * Método interno para obtener el contenedor.
     * Si no existe en el DOM, lo crea en ese momento.
     */
    getContainer() {
        let container = document.getElementById(this.containerId);
        
        if (!container) {
            container = document.createElement('div');
            container.id = this.containerId;
            document.body.appendChild(container);
        }
        
        this.alertContainer = container;
        return container;
    }

    /**
     * Muestra una nueva alerta.
     */
    showAlert(message, type = 'info', duration = 4000) {
        // 1. Obtener (o crear) el contenedor dinámicamente
        const container = this.getContainer();

        // Definir icono según el tipo
        const iconMap = {
            'success': 'check_circle',
            'error': 'error',
            'info': 'info',
            'warning': 'warning'
        };
        const iconName = iconMap[type] || 'info';

        // 2. Creación del elemento alerta
        const alertBox = document.createElement('div');
        alertBox.className = `alert-box alert-${type}`;
        
        // Inyectamos HTML con el icono y el mensaje
        alertBox.innerHTML = `
            <span class="material-symbols-rounded">${iconName}</span>
            <span>${message}</span>
        `;

        // 3. Añadir al contenedor
        container.appendChild(alertBox);

        // 4. Animación de Entrada
        setTimeout(() => {
            alertBox.classList.add('show');
        }, 10);

        // 5. Timer de duración
        const hideTimer = setTimeout(() => {
            this.hideAlert(alertBox);
        }, duration);

        // 6. Cierre manual al hacer clic
        alertBox.addEventListener('click', () => {
            clearTimeout(hideTimer);
            this.hideAlert(alertBox);
        });
    }

    /**
     * Oculta y elimina una alerta, y si es la última, elimina el contenedor.
     */
    hideAlert(alertBox) {
        // Animación de Salida
        alertBox.classList.remove('show');

        setTimeout(() => {
            // 1. Eliminar la alerta del DOM
            if (alertBox.parentNode) {
                alertBox.parentNode.removeChild(alertBox);
            }

            // 2. VERIFICACIÓN DINÁMICA:
            // Si el contenedor ya no tiene hijos (alertas), lo eliminamos del body.
            const container = document.getElementById(this.containerId);
            if (container && container.children.length === 0) {
                if (container.parentNode) {
                    container.parentNode.removeChild(container);
                }
                this.alertContainer = null; // Limpiamos la referencia
            }

        }, this.animationDuration);
    }
}