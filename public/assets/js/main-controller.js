export function initMainController() {
    console.log("Inicializando controlador principal...");

    initModuleSystem();
    initScrollEffects();
}

function initScrollEffects() {
    const scrollContainer = document.querySelector('.general-content-scrolleable');
    const topHeader = document.querySelector('.general-content-top');

    // Verificamos que existan los elementos (por si estamos en login/register donde no hay header)
    if (!scrollContainer || !topHeader) return;

    scrollContainer.addEventListener('scroll', () => {
        // Si el scroll es mayor a 0 (o un umbral pequeño como 10px), agregamos la sombra
        if (scrollContainer.scrollTop > 5) {
            topHeader.classList.add('shadow');
        } else {
            topHeader.classList.remove('shadow');
        }
    });
}

function initModuleSystem() {
    const allowMultipleActive = false;
    const closeOnEsc = true;
    const closeOnClickOutside = true;

    // Seleccionamos todos los botones con data-action
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
            const action = btn.dataset.action;

            // === CORRECCIÓN ===
            // Solo detenemos la propagación y ejecutamos lógica UI
            // si la acción ES de interfaz (abrir/cerrar menús).
            // Si es 'logout', DEJAMOS que el evento pase (burbujee) 
            // para que auth-controller lo capture.
            if (action === 'toggleModuleProfile' || action === 'toggleModuleSurface') {
                e.stopPropagation(); // Solo paramos el clic para estos
                
                let targetModuleName = '';
                if (action === 'toggleModuleProfile') targetModuleName = 'moduleProfile';
                if (action === 'toggleModuleSurface') targetModuleName = 'moduleSurface';

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
            }
            // Si es 'logout', no hacemos nada aquí y dejamos que el evento siga su camino.
        });
    });

    if (closeOnClickOutside) {
        document.addEventListener('click', (event) => {
            const isClickInsideModule = event.target.closest('.module-content');
            // Si el clic no fue dentro de un módulo, cerramos todo
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