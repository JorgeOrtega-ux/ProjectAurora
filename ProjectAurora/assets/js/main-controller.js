// assets/js/main-controller.js

/**
 * Inicializa el control de módulos UI (Menú lateral, Popover de perfil).
 */
export function initMainController() {
    
    // Configuración interna
    const allowMultipleModules = false; 
    const allowCloseOnEsc = true;
    const allowCloseOnClickOutside = true; 

    // Listener principal de clics UI
    document.body.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-action]');

        if (trigger) {
            // Es un botón para abrir/cerrar algo
            const action = trigger.dataset.action;
            let targetModuleId = null;

            if (action === 'toggleModuleSurface') targetModuleId = 'moduleSurface';
            if (action === 'toggleModuleOptions') targetModuleId = 'moduleOptions';

            if (targetModuleId) {
                e.preventDefault(); 
                if (!allowMultipleModules) {
                    closeAllModules(targetModuleId);
                }
                toggleModule(targetModuleId);
            }
        } else {
            // Clic fuera de los botones
            if (allowCloseOnClickOutside) {
                const clickedInsideModule = e.target.closest('[data-module]');
                // Si no fue dentro de un módulo, cerramos todo
                if (!clickedInsideModule) {
                    closeAllModules();
                }
            }
        }
    });

    // Listener para tecla ESC
    document.addEventListener('keydown', (e) => {
        if (allowCloseOnEsc && e.key === 'Escape') {
            closeAllModules(); 
        }
    });
}

/* --- Helpers Internos --- */

function toggleModule(moduleId) {
    const module = document.querySelector(`[data-module="${moduleId}"]`);
    if (module) {
        if (module.classList.contains('disabled')) {
            module.classList.remove('disabled');
            module.classList.add('active');
        } else {
            module.classList.remove('active');
            module.classList.add('disabled');
        }
    }
}

function closeAllModules(exceptModuleId = null) {
    const modules = document.querySelectorAll('[data-module]');
    modules.forEach(mod => {
        if (mod.dataset.module !== exceptModuleId) {
            mod.classList.remove('active');
            mod.classList.add('disabled');
        }
    });
}