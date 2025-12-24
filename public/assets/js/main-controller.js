/**
 * public/assets/js/main-controller.js
 */

export function initMainController() {
    console.log("Inicializando controlador principal...");

    initModuleSystem();
    initScrollEffects();
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

    // Función auxiliar para cerrar con animación
    const closeModuleWithAnimation = (mod) => {
        const isMobile = window.innerWidth <= 468;
        const isProfile = mod.dataset.module === 'moduleProfile';

        if (isMobile && isProfile && mod.classList.contains('active')) {
            mod.classList.add('closing');
            
            setTimeout(() => {
                mod.classList.remove('active', 'closing');
                mod.classList.add('disabled');
                
                // Limpiar transformaciones inline
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

            // --- Lógica de módulos existentes ---
            if (action === 'toggleModuleProfile' || action === 'toggleModuleSurface') {
                e.stopPropagation(); 
                
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
                            closeModuleWithAnimation(targetModule);
                        } else {
                            // Abrir con animación
                            targetModule.classList.remove('disabled');
                            targetModule.style.display = 'flex';
                            void targetModule.offsetHeight; // Forzar Reflow
                            targetModule.classList.add('active');
                            targetModule.style.display = '';
                        }
                    }
                }
            }

            // --- NUEVA LÓGICA: TOGGLE BÚSQUEDA MÓVIL ---
            if (action === 'toggleSearch') {
                const searchContainer = document.getElementById('header-search-bar');
                if (searchContainer) {
                    searchContainer.classList.toggle('active');
                    btn.classList.toggle('active'); // Opcional: para iluminar el botón

                    // Enfocar el input si se abre
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
                
                // Cerrar búsqueda si está abierta
                const searchContainer = document.getElementById('header-search-bar');
                if (searchContainer && searchContainer.classList.contains('active')) {
                    searchContainer.classList.remove('active');
                    document.querySelectorAll('[data-action="toggleSearch"]').forEach(b => b.classList.remove('active'));
                }
            }
        });
    }

    initMobileDrag(closeModuleWithAnimation);
}

function initMobileDrag(closeCallback) {
    const profileModule = document.querySelector('[data-module="moduleProfile"]');
    if (!profileModule) return;

    const content = profileModule.querySelector('.menu-content');
    const handle = profileModule.querySelector('.pill-container');

    if (!content || !handle) return;

    let startY = 0;
    let currentY = 0;
    let isDragging = false;
    let menuHeight = 0;

    // Lógica común para iniciar arrastre
    const startDrag = (clientY) => {
        if (window.innerWidth > 468) return; // Solo móvil
        startY = clientY;
        menuHeight = content.offsetHeight;
        isDragging = true;
        
        // Quitar transición para respuesta instantánea
        content.style.transition = 'none';
    };

    // Lógica común para mover
    const moveDrag = (clientY, event) => {
        if (!isDragging) return;
        
        const deltaY = clientY - startY;

        // Solo permitir bajar (deltaY > 0)
        // Añadimos una resistencia elástica si intentan subir (deltaY < 0) opcional, 
        // pero por ahora bloqueamos subir más allá del 0.
        if (deltaY > 0) {
            if (event.cancelable) event.preventDefault(); // Evitar scroll/refresh
            
            content.style.transform = `translateY(${deltaY}px)`;
            currentY = deltaY;
        }
    };

    // Lógica común para terminar
    const endDrag = () => {
        if (!isDragging) return;
        isDragging = false;

        // Restaurar transición suave
        content.style.transition = 'transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1)';

        // Umbral del 40%
        const threshold = menuHeight * 0.4;

        if (currentY > threshold) {
            closeCallback(profileModule);
        } else {
            // Rebote
            content.style.transform = '';
        }
        currentY = 0;
    };

    // --- EVENTOS TOUCH (Móviles reales) ---
    handle.addEventListener('touchstart', (e) => startDrag(e.touches[0].clientY), { passive: false });
    handle.addEventListener('touchmove', (e) => moveDrag(e.touches[0].clientY, e), { passive: false });
    handle.addEventListener('touchend', endDrag);

    // --- EVENTOS MOUSE (Desktop / Testing) ---
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