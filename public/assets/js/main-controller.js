export class MainController {
    constructor() {
        // --- CONFIGURACIÓN ---
        // true: Permite varios módulos abiertos a la vez. 
        // false: Cierra los otros al abrir uno nuevo.
        this.allowMultipleModules = false; 

        // true: Permite cerrar módulos con la tecla ESC.
        this.closeOnEsc = true;
        
        this.init();
    }

    init() {
        this.header = document.querySelector('.header');
        
        this.handleMobileSearch();
        this.handleResize();
        this.handleScrollShadow();
        this.handleModules();
    }

    handleModules() {
        // 1. Manejo de botones (Triggers)
        const triggers = document.querySelectorAll('[data-action^="toggle"]');

        triggers.forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                // Detenemos la propagación para que este click no active el evento "click fuera" del document
                e.stopPropagation();

                const action = trigger.dataset.action;
                
                // Mapa de acciones a módulos
                const actionMap = {
                    'toggleModuleSurface': 'moduleSurface',
                    'toggleModuleMainOptions': 'moduleMainOptions'
                };

                const targetModuleName = actionMap[action];

                if (targetModuleName) {
                    const targetModule = document.querySelector(`[data-module="${targetModuleName}"]`);
                    
                    if (targetModule) {
                        const isOpening = targetModule.classList.contains('disabled');

                        // Si NO se permiten múltiples módulos y estamos intentando ABRIR uno nuevo...
                        if (!this.allowMultipleModules && isOpening) {
                            // ...cerramos todos antes de abrir este.
                            this.closeAllModules(); 
                        }

                        // Alternamos el estado del módulo objetivo
                        targetModule.classList.toggle('disabled');
                    }
                }
            });
        });

        // 2. Click Fuera (General - Sin variable de control, siempre activo)
        document.addEventListener('click', (e) => {
            // Buscamos todos los módulos que estén visibles (sin la clase disabled)
            const activeModules = document.querySelectorAll('.component-module:not(.disabled)');
            
            activeModules.forEach(module => {
                // Si el click NO ocurrió dentro del módulo, lo cerramos
                if (!module.contains(e.target)) {
                    module.classList.add('disabled');
                }
            });
        });

        // 3. Tecla ESC (Controlada por this.closeOnEsc)
        document.addEventListener('keydown', (e) => {
            if (this.closeOnEsc && e.key === 'Escape') {
                this.closeAllModules();
            }
        });
    }

    // Helper para cerrar todo
    closeAllModules() {
        const activeModules = document.querySelectorAll('.component-module:not(.disabled)');
        activeModules.forEach(module => {
            module.classList.add('disabled');
        });
    }

    handleMobileSearch() {
        const searchTrigger = document.querySelector('.mobile-search-trigger');

        if (searchTrigger && this.header) {
            searchTrigger.addEventListener('click', (e) => {
                e.preventDefault();
                // Detenemos propagación para evitar conflictos con otros clicks globales si fuera necesario
                e.stopPropagation(); 
                this.header.classList.toggle('is-search-active');
            });
        }
    }

    handleResize() {
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768 && this.header.classList.contains('is-search-active')) {
                this.header.classList.remove('is-search-active');
            }
        });
    }

    handleScrollShadow() {
        const scrollableArea = document.querySelector('.general-content-scrolleable');
        const topSection = document.querySelector('.general-content-top');

        if (scrollableArea && topSection) {
            scrollableArea.addEventListener('scroll', () => {
                if (scrollableArea.scrollTop > 0) {
                    topSection.classList.add('shadow');
                } else {
                    topSection.classList.remove('shadow');
                }
            });
        }
    }
}