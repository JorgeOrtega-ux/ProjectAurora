
export function initMainController() {
    console.log("Inicializando controlador principal...");

    initModuleSystem();

}

function initModuleSystem() {
    const allowMultipleActive = false; 
    const closeOnEsc = true;           

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
            e.stopPropagation(); 

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

    if (closeOnEsc) {
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAllModules();
            }
        });
    }
}