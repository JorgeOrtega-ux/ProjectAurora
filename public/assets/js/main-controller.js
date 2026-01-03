/**
 * public/assets/js/main-controller.js
 */

export function initMainController() {
    console.log("Inicializando controlador principal...");

    initModuleSystem();
    initScrollEffects();
    initGlobalStateListeners(); // Importante: Activamos la escucha de eventos
}

function initScrollEffects() {
    const scrollContainer = document.querySelector('.general-content-scrolleable');
    const topHeader = document.querySelector('.general-content-top');

    if (!scrollContainer || !topHeader) return;

    scrollContainer.addEventListener('scroll', () => {
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

    const buttons = document.querySelectorAll('[data-action]');
    const allModules = document.querySelectorAll('.module-content');

    const closeModuleWithAnimation = (mod) => {
        const isMobile = window.innerWidth <= 468;
        const isSheetModule = mod.dataset.module === 'moduleProfile' || mod.dataset.module === 'moduleNotifications';

        if (isMobile && isSheetModule && mod.classList.contains('active')) {
            mod.classList.add('closing');
            
            setTimeout(() => {
                mod.classList.remove('active', 'closing');
                mod.classList.add('disabled');
                
                const content = mod.querySelector('.menu-content');
                if(content) {
                    content.style.transform = '';
                    content.style.transition = ''; 
                }
            }, 280); 
        } else {
            mod.classList.remove('active');
            mod.classList.add('disabled');
        }
    };

    const closeAllModules = (exceptModule = null) => {
        allModules.forEach(mod => {
            if (mod !== exceptModule && mod.classList.contains('active')) {
                closeModuleWithAnimation(mod);
            }
        });
    };

    buttons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const action = btn.dataset.action;

            if (action.startsWith('toggleModule')) {
                e.stopPropagation(); 
                
                let targetModuleName = '';
                if (action === 'toggleModuleProfile') targetModuleName = 'moduleProfile';
                if (action === 'toggleModuleSurface') targetModuleName = 'moduleSurface';
                if (action === 'toggleModuleNotifications') targetModuleName = 'moduleNotifications';

                if (targetModuleName) {
                    const targetModule = document.querySelector(`[data-module="${targetModuleName}"]`);
                    if (targetModule) {
                        const isActive = targetModule.classList.contains('active');

                        if (!allowMultipleActive && !isActive) {
                            closeAllModules(targetModule);
                        }

                        if (isActive) {
                            closeModuleWithAnimation(targetModule);
                        } else {
                            targetModule.classList.remove('disabled');
                            targetModule.style.display = 'flex';
                            void targetModule.offsetHeight; 
                            targetModule.classList.add('active');
                            targetModule.style.display = '';
                        }
                    }
                }
            }

            if (action === 'toggleSearch') {
                const searchContainer = document.getElementById('header-search-bar');
                if (searchContainer) {
                    searchContainer.classList.toggle('active');
                    btn.classList.toggle('active'); 

                    if (searchContainer.classList.contains('active')) {
                        const input = searchContainer.querySelector('input');
                        if(input) input.focus();
                    }
                }
            }
        });
    });

    if (closeOnClickOutside) {
        document.addEventListener('click', (event) => {
            const isClickInsideModule = event.target.closest('.menu-content');
            const isToggleBtn = event.target.closest('[data-action^="toggleModule"]');
            
            if (!isClickInsideModule && !isToggleBtn) {
                closeAllModules();
            }
        });
    }

    if (closeOnEsc) {
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAllModules();
                const searchContainer = document.getElementById('header-search-bar');
                if (searchContainer && searchContainer.classList.contains('active')) {
                    searchContainer.classList.remove('active');
                    document.querySelectorAll('[data-action="toggleSearch"]').forEach(b => b.classList.remove('active'));
                }
            }
        });
    }

    const sheetModules = document.querySelectorAll('[data-module="moduleProfile"], [data-module="moduleNotifications"]');
    sheetModules.forEach(mod => {
        initMobileDrag(mod, closeModuleWithAnimation);
    });
}

function initMobileDrag(moduleElement, closeCallback) {
    if (!moduleElement) return;

    const content = moduleElement.querySelector('.menu-content');
    const handle = moduleElement.querySelector('.pill-container');

    if (!content || !handle) return;

    let startY = 0;
    let currentY = 0;
    let isDragging = false;
    let menuHeight = 0;

    const startDrag = (clientY) => {
        if (window.innerWidth > 468) return; 
        startY = clientY;
        menuHeight = content.offsetHeight;
        isDragging = true;
        content.style.transition = 'none';
    };

    const moveDrag = (clientY, event) => {
        if (!isDragging) return;
        const deltaY = clientY - startY;
        if (deltaY > 0) {
            if (event.cancelable) event.preventDefault();
            content.style.transform = `translateY(${deltaY}px)`;
            currentY = deltaY;
        }
    };

    const endDrag = () => {
        if (!isDragging) return;
        isDragging = false;
        content.style.transition = 'transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1)';
        const threshold = menuHeight * 0.4;
        if (currentY > threshold) {
            closeCallback(moduleElement);
        } else {
            content.style.transform = '';
        }
        currentY = 0;
    };

    handle.addEventListener('touchstart', (e) => startDrag(e.touches[0].clientY), { passive: false });
    handle.addEventListener('touchmove', (e) => moveDrag(e.touches[0].clientY, e), { passive: false });
    handle.addEventListener('touchend', endDrag);

    const onMouseMove = (e) => moveDrag(e.clientY, e);
    const onMouseUp = () => {
        endDrag();
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
    };

    handle.addEventListener('mousedown', (e) => {
        startDrag(e.clientY);
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });
}

/**
 * Escucha eventos globales de la aplicación para sincronizar UI (Avatar y otros)
 */
function initGlobalStateListeners() {
    // Escuchar evento 'user:avatar_update' disparado desde ProfileController
    document.addEventListener('user:avatar_update', (e) => {
        const newSrc = e.detail.src;
        if (!newSrc) return;

        console.log("MainController: Sincronizando avatar global...", newSrc);

        // Actualizar imagen en el Header
        const headerImg = document.querySelector('.header .profile-button .profile-img');
        if (headerImg) {
            headerImg.src = newSrc;
            
            // Animación de feedback
            if (headerImg.animate) {
                headerImg.animate([
                    { transform: 'scale(0.8)', opacity: 0.5 },
                    { transform: 'scale(1)', opacity: 1 }
                ], { duration: 300, easing: 'ease-out' });
            }
        }
    });
}