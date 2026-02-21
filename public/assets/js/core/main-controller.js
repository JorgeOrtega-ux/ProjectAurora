// public/assets/js/main-controller.js

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

        // Parche: Vuelve a vincular los eventos táctiles cuando el Router SPA cambia la vista
        window.addEventListener('viewLoaded', () => {
            this.initBottomSheets();
        });
    }

    handleModules() {
        const triggers = document.querySelectorAll('[data-action^="toggle"]');

        triggers.forEach(trigger => {
            // Ignoramos triggers personalizados como "toggle-dropdown" para que profile-controller los gestione
            if (trigger.dataset.action === 'toggle-dropdown') return; 

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

                if (panel && !panel.contains(e.target)) {
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

    handleResize() {
        let resizeTimer;
        
        window.addEventListener('resize', () => {
            document.body.classList.add('resize-animation-stopper');
            
            if (window.innerWidth > 768 && this.header && this.header.classList.contains('is-search-active')) {
                this.header.classList.remove('is-search-active');
            }
            
            if (!this.isMobile) {
                const activeModules = document.querySelectorAll('.component-module');
                activeModules.forEach(m => {
                    m.classList.remove('is-dragging');
                    const p = m.querySelector('.component-module-panel');
                    if(p) p.style.transform = '';
                });
            }

            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                document.body.classList.remove('resize-animation-stopper');
            }, 400);
        });
    }

    initBottomSheets() {
        // Seleccionamos módulos que aún no han sido inicializados para evitar duplicidad en el DOM estático
        const modules = document.querySelectorAll('.component-module--display-overlay:not(.bottom-sheet-initialized)');

        modules.forEach(module => {
            module.classList.add('bottom-sheet-initialized');
            const panel = module.querySelector('.component-module-panel');
            if (!panel) return;

            const dragHandle = panel.querySelector('.pill-container');

            if (dragHandle) {
                dragHandle.addEventListener('pointerdown', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.handleDragStart(e, module, panel);
                });
            }

            panel.addEventListener('pointermove', (e) => this.handleDragMove(e));
            panel.addEventListener('pointerup', (e) => this.handleDragEnd(e));
            panel.addEventListener('pointercancel', (e) => this.handleDragEnd(e));
        });
    }

    handleDragStart(e, module, panel) {
        if (!this.isMobile) return;
        
        if (e.pointerType === 'mouse' && e.button !== 0) return;

        panel.setPointerCapture(e.pointerId);

        this.dragState.isDragging = true;
        this.dragState.startY = e.clientY;
        this.dragState.module = module;
        this.dragState.panel = panel;
        module.classList.add('is-dragging');
    }

    handleDragMove(e) {
        if (!this.dragState.isDragging) return;
        
        if (e.cancelable) e.preventDefault();

        const diff = e.clientY - this.dragState.startY;
        
        if (diff > 0) {
            this.dragState.panel.style.transform = `translateY(${diff}px)`;
            this.dragState.currentDiff = diff;
        }
    }

    handleDragEnd(e) {
        if (!this.dragState.isDragging) return;

        this.dragState.isDragging = false;
        this.dragState.module.classList.remove('is-dragging');
        
        if (this.dragState.panel.hasPointerCapture(e.pointerId)) {
            this.dragState.panel.releasePointerCapture(e.pointerId);
        }

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