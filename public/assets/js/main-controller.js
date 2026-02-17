export class MainController {
    constructor() {
        // --- CONFIGURACIÓN ---
        this.allowMultipleModules = false; 
        this.closeOnEsc = true;
        
        // --- ESTADO DEL GESTO ---
        this.dragState = {
            startY: 0,
            currentY: 0,
            isDragging: false,
            panel: null,
            module: null
        };

        this.init();
    }

    get isMobile() {
        return window.matchMedia('(max-width: 480px)').matches;
    }

    init() {
        this.header = document.querySelector('.header');
        
        this.handleMobileSearch();
        this.handleResize();
        this.handleScrollShadow();
        this.handleModules();
    }

    handleModules() {
        const triggers = document.querySelectorAll('[data-action^="toggle"]');

        triggers.forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const action = trigger.dataset.action;
                const actionMap = {
                    'toggleModuleSurface': 'moduleSurface',
                    'toggleModuleMainOptions': 'moduleMainOptions'
                };

                const targetModuleName = actionMap[action];
                if (targetModuleName) {
                    this.toggleModule(targetModuleName);
                }
            });
        });

        document.addEventListener('click', (e) => {
            const openModules = document.querySelectorAll('.component-module:not(.disabled)');
            openModules.forEach(module => {
                const panel = module.querySelector('.component-module-panel');
                if (this.dragState.isDragging) return;
                if (module.contains(e.target) && !panel.contains(e.target)) {
                    this.closeModule(module);
                }
            });
        });

        document.addEventListener('keydown', (e) => {
            if (this.closeOnEsc && e.key === 'Escape') {
                this.closeAllModules();
            }
        });

        this.initBottomSheets();
    }

    toggleModule(moduleName) {
        const module = document.querySelector(`[data-module="${moduleName}"]`);
        if (!module) return;

        const isClosed = module.classList.contains('disabled');

        if (isClosed) {
            if (!this.allowMultipleModules) {
                this.closeAllModules();
            }
            this.openModule(module);
        } else {
            this.closeModule(module);
        }
    }

    openModule(module) {
        module.classList.remove('disabled');
    }

    closeModule(module) {
        module.classList.add('disabled');
        const panel = module.querySelector('.component-module-panel');
        if(panel) panel.style.transform = '';
    }

    closeAllModules() {
        const activeModules = document.querySelectorAll('.component-module:not(.disabled)');
        activeModules.forEach(module => this.closeModule(module));
    }

    // --- AQUÍ ESTÁ LA MAGIA DEL RESIZE ---
    handleResize() {
        let resizeTimer;
        
        window.addEventListener('resize', () => {
            // 1. Añadimos la clase que MATA todas las transiciones instantáneamente
            document.body.classList.add('resize-animation-stopper');
            
            // 2. Lógica existente de header
            if (window.innerWidth > 768 && this.header.classList.contains('is-search-active')) {
                this.header.classList.remove('is-search-active');
            }
            
            // 3. Limpieza de estado drag si pasamos a PC
            if (!this.isMobile) {
                const activeModules = document.querySelectorAll('.component-module');
                activeModules.forEach(m => {
                    m.classList.remove('is-dragging');
                    const p = m.querySelector('.component-module-panel');
                    if(p) p.style.transform = '';
                });
            }

            // 4. Debounce: Esperamos a que el usuario termine de redimensionar
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                // Restauramos las animaciones para que funcionen los clicks normales
                document.body.classList.remove('resize-animation-stopper');
            }, 400); // 400ms de espera tras el último movimiento
        });
    }

    initBottomSheets() {
        const modules = document.querySelectorAll('.component-module--display-overlay');

        modules.forEach(module => {
            const panel = module.querySelector('.component-module-panel');
            if (!panel) return;

            // Touch
            panel.addEventListener('touchstart', (e) => this.handleDragStart(e, module, panel, 'touch'), { passive: false });
            panel.addEventListener('touchmove', (e) => this.handleDragMove(e, 'touch'), { passive: false });
            panel.addEventListener('touchend', (e) => this.handleDragEnd(e, 'touch'), { passive: false });

            // Mouse
            panel.addEventListener('mousedown', (e) => this.handleDragStart(e, module, panel, 'mouse'));
            document.addEventListener('mousemove', (e) => this.handleDragMove(e, 'mouse'));
            document.addEventListener('mouseup', (e) => this.handleDragEnd(e, 'mouse'));
        });
    }

    handleDragStart(e, module, panel, type) {
        if (!this.isMobile) return;
        const clientY = type === 'touch' ? e.touches[0].clientY : e.clientY;
        this.dragState.isDragging = true;
        this.dragState.startY = clientY;
        this.dragState.module = module;
        this.dragState.panel = panel;
        module.classList.add('is-dragging');
    }

    handleDragMove(e, type) {
        if (!this.dragState.isDragging) return;
        const clientY = type === 'touch' ? e.touches[0].clientY : e.clientY;
        const diff = clientY - this.dragState.startY;
        if (diff > 0) {
            if (type === 'touch' && e.cancelable) e.preventDefault();
            this.dragState.panel.style.transform = `translateY(${diff}px)`;
            this.dragState.currentDiff = diff;
        }
    }

    handleDragEnd(e, type) {
        if (!this.dragState.isDragging) return;
        if (type === 'mouse' && e.button !== 0 && e.button !== undefined) return;

        this.dragState.isDragging = false;
        this.dragState.module.classList.remove('is-dragging');

        const panelHeight = this.dragState.panel.offsetHeight;
        const threshold = panelHeight * 0.40; 

        if (this.dragState.currentDiff > threshold) {
            this.closeModule(this.dragState.module);
        } else {
            this.dragState.panel.style.transform = '';
        }

        this.dragState.currentDiff = 0;
        this.dragState.module = null;
        this.dragState.panel = null;
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

    handleMobileSearch() {
        const searchTrigger = document.querySelector('.mobile-search-trigger');
        if (searchTrigger && this.header) {
            searchTrigger.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation(); 
                this.header.classList.toggle('is-search-active');
            });
        }
    }
}