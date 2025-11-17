document.addEventListener('DOMContentLoaded', () => {

    // --- CONFIGURACIÓN ---
    
    // Si es 'true', permite tener varios módulos abiertos (ej. menú y perfil a la vez).
    // Si es 'false', al abrir uno se cerrarán los demás automáticamente.
    let allowMultipleModules = false; 

    // Si es 'true', permite cerrar todos los módulos activos presionando la tecla ESC.
    let allowCloseOnEsc = true;

    // ---------------------

    // Delegación de eventos global para manejar los clics en la UI
    document.body.addEventListener('click', (e) => {
        
        // Buscamos si el elemento clickeado (o sus padres) tiene el atributo data-action
        const trigger = e.target.closest('[data-action]');

        if (trigger) {
            const action = trigger.dataset.action;
            let targetModuleId = null;

            // Mapeamos la acción al nombre del módulo
            if (action === 'toggleModuleSurface') {
                targetModuleId = 'moduleSurface';
            } else if (action === 'toggleModuleOptions') {
                targetModuleId = 'moduleOptions';
            }

            // Si encontramos un módulo objetivo válido
            if (targetModuleId) {
                e.preventDefault(); // Prevenir comportamiento default

                // Si NO se permiten múltiples módulos, cerramos los otros antes de abrir este
                if (!allowMultipleModules) {
                    // Pasamos el ID actual para que NO se cierre a sí mismo antes de alternar
                    closeAllModules(targetModuleId);
                }

                toggleModule(targetModuleId);
            }
        } 
    });

    // Listener para la tecla ESC
    document.addEventListener('keydown', (e) => {
        if (allowCloseOnEsc && e.key === 'Escape') {
            closeAllModules(); // Cierra todo sin excepciones
        }
    });
});

/**
 * Alterna el estado (active/disabled) de un módulo específico.
 * @param {string} moduleId - El valor del atributo data-module.
 */
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
    } else {
        console.warn(`No se encontró ningún módulo con data-module="${moduleId}"`);
    }
}

/**
 * Cierra todos los módulos, con opción a excluir uno (útil al alternar).
 * @param {string|null} exceptModuleId - ID del módulo que NO se debe cerrar (opcional).
 */
function closeAllModules(exceptModuleId = null) {
    const modules = document.querySelectorAll('[data-module]');
    
    modules.forEach(mod => {
        // Si el módulo actual no es el que queremos excluir...
        if (mod.dataset.module !== exceptModuleId) {
            // ...lo cerramos forzosamente.
            mod.classList.remove('active');
            mod.classList.add('disabled');
        }
    });
}