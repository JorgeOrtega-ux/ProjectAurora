
export function initMainController() {
    console.log("Inicializando controlador principal...");

    initModuleSystem();

}

function initModuleSystem() {
    const allowMultipleActive = false;
    const closeOnEsc = true;
    const closeOnClickOutside = true; // Nueva configuración

    const buttons = document.querySelectorAll('[data-action]');
    const allModules = document.querySelectorAll('.module-content');

    const closeAllModules = (exceptModule = null) => {
        allModules.forEach(mod => {
            if (mod !== exceptModule) {
                mod.classList.remove('active');
                mod.classList.add('disabled');
            }
        });
    };

    buttons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation(); // Evita que el clic llegue al document y se cierre inmediatamente

            const action = btn.dataset.action;
            let targetModuleName = '';

            if (action === 'toggleModuleProfile') {
                targetModuleName = 'moduleProfile';
            } else if (action === 'toggleModuleSurface') {
                targetModuleName = 'moduleSurface';
            }

            if (targetModuleName) {
                const targetModule = document.querySelector(`[data-module="${targetModuleName}"]`);

                if (targetModule) {
                    const isActive = targetModule.classList.contains('active');

                    if (!allowMultipleActive && !isActive) {
                        closeAllModules(targetModule);
                    }

                    if (isActive) {
                        targetModule.classList.remove('active');
                        targetModule.classList.add('disabled');
                    } else {
                        targetModule.classList.remove('disabled');
                        targetModule.classList.add('active');
                    }
                }
            }
        });
    });

    // NUEVO: Lógica para cerrar al hacer clic fuera
    if (closeOnClickOutside) {
        document.addEventListener('click', (event) => {
            // Verificamos si el clic ocurrió DENTRO de algún módulo
            const isClickInsideModule = event.target.closest('.module-content');

            // Si el clic NO fue dentro de un módulo, cerramos todo
            if (!isClickInsideModule) {
                closeAllModules();
            }
        });
    }

    if (closeOnEsc) {
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAllModules();
            }
        });
    }
}