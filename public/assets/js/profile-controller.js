// public/assets/js/profile-controller.js

export class ProfileController {
    constructor() {
        this.init();
    }

    init() {
        // Usamos delegación de eventos en el body para que funcione con la SPA
        // sin importar cuándo el Router inyecte el HTML
        document.body.addEventListener('click', (e) => {
            
            // 1. Iniciar edición
            const btnStartEdit = e.target.closest('[data-action="start-edit"]');
            if (btnStartEdit) {
                e.preventDefault();
                this.handleStartEdit(btnStartEdit.dataset.target);
                return;
            }

            // 2. Cancelar edición
            const btnCancelEdit = e.target.closest('[data-action="cancel-edit"]');
            if (btnCancelEdit) {
                e.preventDefault();
                this.handleCancelEdit(btnCancelEdit.dataset.target);
                return;
            }

            // 3. Guardar campo
            const btnSaveField = e.target.closest('[data-action="save-field"]');
            if (btnSaveField) {
                e.preventDefault();
                this.handleSaveField(btnSaveField.dataset.target);
                return;
            }

            // 4. Toggle Dropdown de idioma
            const triggerSelector = e.target.closest('.trigger-selector');
            if (triggerSelector) {
                e.preventDefault();
                this.handleDropdownToggle(triggerSelector, e);
                return;
            }

            // 5. Seleccionar opción de idioma
            const optionSelect = e.target.closest('[data-action="select-option"]');
            if (optionSelect) {
                e.preventDefault();
                this.handleOptionSelect(optionSelect);
                return;
            }

            // 6. Cerrar popovers al hacer click fuera de ellos
            if (!e.target.closest('.trigger-select-wrapper')) {
                this.closeAllPopovers();
            }
        });

        // Evento 'input' para el buscador del dropdown
        document.body.addEventListener('input', (e) => {
            const filterInput = e.target.closest('[data-action="filter-options"]');
            if (filterInput) {
                this.handleFilter(filterInput);
            }
        });
    }

    /* ========================================================================
       MÉTODOS DE ESTADO: VER / EDITAR
       ======================================================================== */

    toggleFieldState(target, mode) {
        const viewState = document.querySelector(`[data-state="${target}-view-state"]`);
        const editState = document.querySelector(`[data-state="${target}-edit-state"]`);
        const viewActions = document.querySelector(`[data-state="${target}-actions-view"]`);
        const editActions = document.querySelector(`[data-state="${target}-actions-edit"]`);

        // Si no existen los elementos en el DOM actual, no hacemos nada
        if (!viewState || !editState || !viewActions || !editActions) return;

        if (mode === 'edit') {
            viewState.classList.replace('active', 'disabled');
            viewActions.classList.replace('active', 'disabled');
            editState.classList.replace('disabled', 'active');
            editActions.classList.replace('disabled', 'active');
            
            const input = document.getElementById(`input-${target}`);
            if (input) input.focus();
        } else {
            editState.classList.replace('active', 'disabled');
            editActions.classList.replace('active', 'disabled');
            viewState.classList.replace('disabled', 'active');
            viewActions.classList.replace('disabled', 'active');
        }
    }

    handleStartEdit(target) {
        this.toggleFieldState(target, 'edit');
    }

    handleCancelEdit(target) {
        const originalValue = document.getElementById(`display-${target}`).textContent;
        const inputEl = document.getElementById(`input-${target}`);
        if (inputEl) inputEl.value = originalValue;
        
        this.toggleFieldState(target, 'view');
    }

    handleSaveField(target) {
        const inputEl = document.getElementById(`input-${target}`);
        const displayEl = document.getElementById(`display-${target}`);
        
        if (!inputEl || !displayEl) return;

        const newValue = inputEl.value.trim();

        if (newValue !== "") {
            displayEl.textContent = newValue;
            this.toggleFieldState(target, 'view');
            
            // Aquí en el futuro harás la llamada a ApiService.post(...)
            console.log(`[API Simulación] ${target} guardado como: ${newValue}`);
        } else {
            // Podrías usar tu componente de error en lugar de alert más adelante
            alert("El campo no puede estar vacío"); 
        }
    }

    /* ========================================================================
       MÉTODOS DEL DROPDOWN DE IDIOMA
       ======================================================================== */

    handleDropdownToggle(selector, event) {
        event.stopPropagation();
        const wrapper = selector.closest('.trigger-select-wrapper');
        const popover = wrapper.querySelector('.popover-module');
        
        this.closeAllPopovers(popover); // Cierra otros si los hay
        
        if (popover) popover.classList.toggle('active');
    }

    handleOptionSelect(option) {
        const wrapper = option.closest('.trigger-select-wrapper');
        const popover = wrapper.querySelector('.popover-module');
        const textDisplay = wrapper.querySelector('.trigger-select-text');
        
        const selectedValue = option.dataset.value;
        const selectedLabel = option.dataset.label;
        
        textDisplay.textContent = selectedLabel;
        
        popover.querySelectorAll('.menu-link').forEach(link => link.classList.remove('active'));
        option.classList.add('active');
        
        popover.classList.remove('active');
        
        console.log(`[API Simulación] Idioma cambiado a: ${selectedValue}`);
    }

    handleFilter(searchInput) {
        const popover = searchInput.closest('.popover-module');
        const term = searchInput.value.toLowerCase().trim();
        
        popover.querySelectorAll('.menu-link').forEach(link => {
            const label = link.dataset.label.toLowerCase();
            if (label.includes(term)) {
                link.style.display = 'flex';
            } else {
                link.style.display = 'none';
            }
        });
    }

    closeAllPopovers(exceptThisOne = null) {
        document.querySelectorAll('.popover-module.active').forEach(p => {
            if (p !== exceptThisOne) p.classList.remove('active');
        });
    }
}