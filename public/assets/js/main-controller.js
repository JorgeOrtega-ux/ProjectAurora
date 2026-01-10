/* =========================================
   MAIN CONTROLLER
   Handles UI logic and module management
   ========================================= */

// CONFIGURATION
var allowMultipleModules = false; 
var allowCloseOnEsc = true;

const moduleActionMap = {
    'toggleModuleSurface': '.module-surface',
    'toggleModuleOptions': '.module-options'
};

/**
 * Cierra todos los módulos flotantes (sidebar, menú opciones y popovers internos).
 * @param {HTMLElement} exceptionElement - Elemento que se mantendrá abierto.
 */
function closeAllModules(exceptionElement) {
    // 1. Cerrar Módulos Globales (Sidebar, Header Menu)
    const globalModules = document.querySelectorAll('.module-content');
    globalModules.forEach(module => {
        if (exceptionElement && (module === exceptionElement || module.contains(exceptionElement))) return;
        module.classList.remove('active');
        module.classList.add('disabled');
    });

    // 2. Cerrar Popovers Internos (Dropdowns de Preferencias)
    const popovers = document.querySelectorAll('.popover-module');
    popovers.forEach(popover => {
        // Encontrar el wrapper padre para verificar la excepción
        const wrapper = popover.closest('.trigger-select-wrapper');
        if (exceptionElement && wrapper && wrapper.contains(exceptionElement)) return;
        
        popover.classList.remove('active');
        // Remover clase activa del trigger visual también
        if(wrapper) {
            const selector = wrapper.querySelector('.trigger-selector');
            if(selector) selector.classList.remove('active');
        }
    });
}

/**
 * Alterna la visibilidad de un módulo global.
 */
function toggleGlobalModule(moduleSelector) {
    const module = document.querySelector(moduleSelector);
    if (!module) return;

    const isActive = module.classList.contains('active');
    
    if (!allowMultipleModules && !isActive) {
        closeAllModules(module); // Cerrar otros antes de abrir
    }

    if (isActive) {
        module.classList.remove('active');
        module.classList.add('disabled');
    } else {
        module.classList.remove('disabled');
        module.classList.add('active');
    }
}

/**
 * Maneja la lógica de los Dropdowns (Selectores dentro de Preferencias)
 */
function handleDropdownToggle(triggerWrapper) {
    const popover = triggerWrapper.querySelector('.popover-module');
    const selector = triggerWrapper.querySelector('.trigger-selector');
    
    if (!popover) return;

    const isActive = popover.classList.contains('active');

    // Cerrar otros menús abiertos para evitar solapamiento
    closeAllModules(popover);

    if (isActive) {
        popover.classList.remove('active');
        if(selector) selector.classList.remove('active');
    } else {
        popover.classList.add('active');
        if(selector) selector.classList.add('active');
    }
}

/**
 * Actualiza la UI cuando se selecciona una opción
 */
function handleOptionSelect(optionLink) {
    const wrapper = optionLink.closest('.trigger-select-wrapper');
    if (!wrapper) return;

    const label = optionLink.getAttribute('data-label');
    const value = optionLink.getAttribute('data-value'); // Para uso futuro (backend)

    // 1. Actualizar texto del selector principal
    const textSpan = wrapper.querySelector('.trigger-select-text');
    if (textSpan && label) {
        textSpan.textContent = label;
    }

    // 2. Actualizar estado visual (check icons, active class)
    const allLinks = wrapper.querySelectorAll('.menu-link');
    allLinks.forEach(link => link.classList.remove('active'));
    optionLink.classList.add('active');

    // 3. Cerrar el menú
    closeAllModules();

    console.log(`Opción seleccionada: ${label} (${value})`);
}

/**
 * Initializes the main controller events using Delegation.
 */
function initMainController() {
    console.log('Main Controller: Initialized with Event Delegation');

    // DELEGACIÓN DE EVENTOS GLOBAL (Maneja clics presentes y futuros)
    document.addEventListener('click', function(event) {
        const target = event.target;

        // A. CLICK EN BOTONES DEL HEADER (Acciones Globales)
        const btn = target.closest('[data-action]');
        if (btn) {
            const action = btn.getAttribute('data-action');
            
            // Acciones de Módulos Globales
            if (moduleActionMap[action]) {
                event.stopPropagation();
                toggleGlobalModule(moduleActionMap[action]);
                return;
            }
            
            // Acción de Seleccionar Opción (Dentro de Popovers)
            if (action === 'select-option') {
                event.preventDefault(); // Evitar navegación si es un <a>
                handleOptionSelect(btn);
                return;
            }
        }

        // B. CLICK EN TRIGGERS DE DROPDOWN (Preferencias)
        const triggerWrapper = target.closest('[data-trigger="dropdown"]');
        if (triggerWrapper) {
            // Verificamos si el clic fue en el input de búsqueda (no cerrar si es así)
            if (target.tagName === 'INPUT') return;

            event.stopPropagation();
            handleDropdownToggle(triggerWrapper);
            return;
        }

        // C. CLICK FUERA (Cerrar Todo)
        // Si el clic no fue dentro de un módulo de contenido ni en un trigger
        const isInsideModule = target.closest('.module-content') || target.closest('.popover-module');
        
        if (!isInsideModule) {
            closeAllModules();
        }
    });

    // ESC KEY HANDLER
    document.addEventListener('keydown', function(event) {
        if (allowCloseOnEsc && event.key === 'Escape') {
            closeAllModules();
        }
    });
}

export { initMainController };