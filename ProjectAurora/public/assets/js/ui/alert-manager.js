// assets/js/alert-manager.js [CORREGIDO]

/**
 * Clase para gestionar la creación y destrucción de alertas
 * en la esquina de la pantalla de forma dinámica.
 */
export class AlertManager {

    /**
     * @param {number} animationDuration Duración (ms) de la animación CSS.
     */
    constructor(animationDuration = 500) {
        // Configuración de selectores elegida
        this.containerClass = 'ui-notification-dock';
        this.containerDataAttr = 'alerts';
        
        this.animationDuration = animationDuration;
        this.alertContainer = null;
    }

    /**
     * Helper para generar el selector CSS completo.
     * Retorna: .ui-notification-dock[data-container="alerts"]
     */
    getSelector() {
        return `.${this.containerClass}[data-container="${this.containerDataAttr}"]`;
    }

    /**
     * Método interno para obtener el contenedor.
     * Si no existe en el DOM, lo crea usando la clase y el data-attribute.
     */
    getContainer() {
        let container = document.querySelector(this.getSelector());
        
        if (!container) {
            container = document.createElement('div');
            container.classList.add(this.containerClass);
            container.dataset.container = this.containerDataAttr;
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
        
        // [MODIFICADO] Usamos DOM nodes en lugar de innerHTML para seguridad (XSS)
        const iconSpan = document.createElement('span');
        iconSpan.className = 'material-symbols-rounded';
        iconSpan.textContent = iconName;

        const textSpan = document.createElement('span');
        textSpan.textContent = message; // <-- Uso seguro de textContent

        alertBox.appendChild(iconSpan);
        alertBox.appendChild(textSpan);

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
            // Buscamos el contenedor usando el selector de clase + data
            const container = document.querySelector(this.getSelector());
            
            if (container && container.children.length === 0) {
                if (container.parentNode) {
                    container.parentNode.removeChild(container);
                }
                this.alertContainer = null; // Limpiamos la referencia
            }

        }, this.animationDuration);
    }
}