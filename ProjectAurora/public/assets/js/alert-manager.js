// assets/js/alert-manager.js

/**
 * Clase para gestionar la creación y destrucción de alertas
 * en la esquina de la pantalla.
 */
export class AlertManager {

    /**
     * @param {string} containerId ID del contenedor que se creará en el body.
     * @param {number} animationDuration Duración (ms) de la animación CSS de salida.
     */
    constructor(containerId = 'alert-container', animationDuration = 500) {
        this.containerId = containerId;
        this.animationDuration = animationDuration;
        this.alertContainer = null;

        // Iniciar al crear la instancia
        this.initContainer();
    }

    /**
     * Crea el <div> contenedor y lo añade al <body>.
     */
    initContainer() {
        // Evitar duplicados si se llama de nuevo
        if (document.getElementById(this.containerId)) return;

        this.alertContainer = document.createElement('div');
        this.alertContainer.id = this.containerId;
        document.body.appendChild(this.alertContainer);
    }

    /**
     * Muestra una nueva alerta.
     * @param {string} message El texto a mostrar.
     * @param {string} type Tipo de alerta ('info', 'success', 'error').
     * @param {number} duration Duración (ms) antes de desaparecer.
     */
    showAlert(message, type = 'info', duration = 4000) {
        if (!this.alertContainer) {
            console.error('AlertManager: El contenedor no está inicializado.');
            return;
        }

        // 1. Creación
        const alertBox = document.createElement('div');
        alertBox.className = `alert-box alert-${type}`;
        alertBox.textContent = message;

        // 2. Añadir al DOM
        this.alertContainer.appendChild(alertBox);

        // 3. Animación de Entrada (usamos un pequeño timeout para que la transición CSS se aplique)
        setTimeout(() => {
            alertBox.classList.add('show');
        }, 10); // Un pequeño delay es suficiente

        // 4. Duración
        const hideTimer = setTimeout(() => {
            this.hideAlert(alertBox);
        }, duration);

        // 5. Permitir cierre manual al hacer clic
        alertBox.addEventListener('click', () => {
            clearTimeout(hideTimer); // Cancelar el timer si se cierra manualmente
            this.hideAlert(alertBox);
        });
    }

    /**
     * Oculta y elimina una alerta específica.
     * @param {HTMLElement} alertBox El elemento de la alerta a ocultar.
     */
    hideAlert(alertBox) {
        // 6. Animación de Salida
        alertBox.classList.remove('show');

        // 7. Destrucción (esperar a que termine la animación de salida)
        setTimeout(() => {
            if (alertBox.parentNode) {
                alertBox.parentNode.removeChild(alertBox);
            }
        }, this.animationDuration); // Debe coincidir con la transition en CSS
    }
}