/**
 * SettingsController
 * Maneja la lógica de interfaz (UI) para secciones de configuración:
 * - Modos de edición (View vs Edit)
 * - Menús desplegables (Popovers) custom
 * - Previsualización de imágenes (Avatares)
 * - Toggles y estados
 */

export const SettingsController = (function() {
    
    // --- Configuración y Selectores ---
    const CONFIG = {
        activeClass: 'active',
        disabledClass: 'disabled',
        popoverSelector: '.popover-module',
        triggerSelector: '.trigger-selector',
        wrapperSelector: '.trigger-select-wrapper'
    };

    /**
     * Alterna entre el modo "Lectura" y "Edición" de un componente.
     * Requiere que el HTML tenga la estructura data-state="[id]-view-state" y data-state="[id]-edit-state".
     * * @param {string} sectionId - El identificador base del componente (ej: 'username', 'email')
     * @param {boolean} isEditing - true para activar edición, false para cancelar/guardar
     */
    function toggleEdit(sectionId, isEditing) {
        const parent = document.querySelector(`[data-component="${sectionId}-section"]`);
        
        if (!parent) {
            console.error(`SettingsController: No se encontró el componente [data-component="${sectionId}-section"]`);
            return;
        }

        const viewState = parent.querySelector(`[data-state="${sectionId}-view-state"]`);
        const editState = parent.querySelector(`[data-state="${sectionId}-edit-state"]`);
        const actionsView = parent.querySelector(`[data-state="${sectionId}-actions-view"]`);
        const actionsEdit = parent.querySelector(`[data-state="${sectionId}-actions-edit"]`);
        const input = parent.querySelector('input');

        if (isEditing) {
            // --- ACTIVAR MODO EDICIÓN ---
            _switchState(viewState, editState);
            _switchState(actionsView, actionsEdit);
            
            // Guardar valor original para poder cancelar después
            if (input) {
                input.dataset.originalValue = input.value;
                input.focus();
            }

        } else {
            // --- CANCELAR / SALIR MODO EDICIÓN ---
            _switchState(editState, viewState);
            _switchState(actionsEdit, actionsView);

            // Restaurar valor original si estamos cancelando (opcional, depende de si se llama desde 'Guardar' o 'Cancelar')
            // Aquí asumimos comportamiento de "Cancelar" visual por defecto.
            // Para "Guardar", la función saveData() debería actualizar el DOM antes de llamar a toggleEdit(..., false)
            if (input && input.dataset.originalValue) {
                input.value = input.dataset.originalValue;
            }
        }
    }

    /**
     * Simula el guardado de datos (UI Only).
     * Actualiza el texto visible y cierra el modo edición.
     */
    function saveData(sectionId) {
        const parent = document.querySelector(`[data-component="${sectionId}-section"]`);
        const input = parent.querySelector('input');
        const display = parent.querySelector('.text-display-value');

        if (input && display) {
            // Actualizar UI
            display.innerText = input.value;
            // Actualizar el valor "original" para que al cancelar futura edición vuelva a este nuevo valor
            input.dataset.originalValue = input.value; 
            
            console.log(`[SettingsController] Guardado local para: ${sectionId} -> ${input.value}`);
            
            // Cerrar modo edición pasando false, pero hackeamos el dataset para que no restaure el valor viejo
            // (Ya que acabamos de "guardar")
            toggleEdit(sectionId, false);
            // Corregir visualmente si toggleEdit restauró algo indebido (refinamiento)
            input.value = display.innerText; 
        }
    }

    /**
     * Maneja la apertura/cierre de los dropdowns personalizados.
     * Se puede llamar directamente desde el onclick del wrapper.
     */
    function toggleDropdown(wrapperElement) {
        const menu = wrapperElement.querySelector(CONFIG.popoverSelector);
        const trigger = wrapperElement.querySelector(CONFIG.triggerSelector);
        
        if (!menu || !trigger) return;

        const isActive = menu.classList.contains(CONFIG.activeClass);

        // 1. Cerrar todos los otros dropdowns abiertos primero
        closeAllDropdowns();

        // 2. Si no estaba activo, abrirlo ahora
        if (!isActive) {
            menu.classList.add(CONFIG.activeClass);
            trigger.classList.add(CONFIG.activeClass);
            wrapperElement.classList.add('dropdown-active');
        }
        
        // Evitar que el evento suba y active el listener del document (que cierra los menús)
        if (event) event.stopPropagation();
    }

    /**
     * Selecciona una opción del dropdown y actualiza la UI.
     */
    function selectOption(itemElement, textValue) {
        const wrapper = itemElement.closest(CONFIG.wrapperSelector);
        if (!wrapper) return;

        const triggerText = wrapper.querySelector('.trigger-select-text');
        
        // Actualizar texto visual
        if (triggerText) triggerText.innerText = textValue;

        // Mover clase 'active' al item seleccionado
        wrapper.querySelectorAll('.menu-link').forEach(link => link.classList.remove(CONFIG.activeClass));
        itemElement.classList.add(CONFIG.activeClass);

        // Cerrar menú
        closeAllDropdowns();
        
        console.log(`[SettingsController] Opción seleccionada: ${textValue}`);
        if (event) event.stopPropagation();
    }

    /**
     * Cierra todos los popovers abiertos en la página.
     */
    function closeAllDropdowns() {
        document.querySelectorAll(CONFIG.popoverSelector).forEach(el => el.classList.remove(CONFIG.activeClass));
        document.querySelectorAll(CONFIG.triggerSelector).forEach(el => el.classList.remove(CONFIG.activeClass));
        document.querySelectorAll(CONFIG.wrapperSelector).forEach(el => el.classList.remove('dropdown-active'));
    }

    /**
     * Helper privado para cambiar clases active/disabled
     */
    function _switchState(hideElement, showElement) {
        if (hideElement) {
            hideElement.classList.remove(CONFIG.activeClass);
            hideElement.classList.add(CONFIG.disabledClass);
        }
        if (showElement) {
            showElement.classList.remove(CONFIG.disabledClass);
            showElement.classList.add(CONFIG.activeClass);
        }
    }

    /**
     * Inicializa listeners globales (clicks fuera para cerrar menús, inputs de archivo, etc).
     */
    function init() {
        // Cerrar dropdowns al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!e.target.closest(CONFIG.wrapperSelector)) {
                closeAllDropdowns();
            }
        });

        console.log("SettingsController inicializado.");
    }

    // --- Lógica Específica de Previsualización de Imagen (Avatar) ---
    // Esta función se puede llamar manualmente al cargar la página si existen los elementos
    function initAvatarUploader(inputId, imgPreviewId, actionsDefaultData, actionsPreviewData) {
        const fileInput = document.getElementById(inputId);
        const previewImg = document.getElementById(imgPreviewId);
        
        if (!fileInput || !previewImg) return;

        const actionsDefault = document.querySelector(`[data-state="${actionsDefaultData}"]`);
        const actionsPreview = document.querySelector(`[data-state="${actionsPreviewData}"]`);
        let originalSrc = previewImg.src;

        // Listener para cambio de archivo
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (evt) => {
                    previewImg.src = evt.target.result;
                    _switchState(actionsDefault, actionsPreview);
                };
                reader.readAsDataURL(file);
            }
        });

        // Configurar botones de cancelar/guardar si existen mediante data-attributes
        // Nota: Asumimos que los botones están dentro del contenedor padre o accesibles
        // Aquí usamos delegación simple buscando por data-action globalmente para simplificar
        document.addEventListener('click', (e) => {
            if (e.target.dataset.action === 'profile-picture-cancel') {
                previewImg.src = originalSrc;
                fileInput.value = '';
                _switchState(actionsPreview, actionsDefault);
            }
            
            if (e.target.dataset.action === 'profile-picture-save') {
                originalSrc = previewImg.src; // Confirmar cambio visualmente
                _switchState(actionsPreview, actionsDefault);
                console.log("[SettingsController] Imagen guardada localmente");
            }
        });
    }

    // API Pública
    return {
        init,
        toggleEdit,
        saveData,
        toggleDropdown,
        selectOption,
        initAvatarUploader,
        closeAllDropdowns
    };

})();

// Inicializar automáticamente al cargar el script
document.addEventListener('DOMContentLoaded', () => {
    SettingsController.init();
    
    // Inicializar el uploader de avatar específicamente (ya que requiere IDs concretos)
    // Puedes llamar a esto desde tu HTML o aquí mismo si los IDs son fijos
    SettingsController.initAvatarUploader(
        'upload-avatar', 
        'preview-avatar', 
        'profile-picture-actions-default', 
        'profile-picture-actions-preview'
    );
});

// Mapeo global para compatibilidad con onclicks existentes
window.toggleEdit = SettingsController.toggleEdit;
window.saveData = SettingsController.saveData;
window.toggleDropdown = SettingsController.toggleDropdown;
window.selectOption = SettingsController.selectOption;