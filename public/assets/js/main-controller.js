/**
 * MainController.js
 * Encargado de la lógica de UI (Menús, Buscador, Interacción visual).
 * ACTUALIZADO: Manejo de temas y GESTOS MOBILE (Drag to dismiss con animación fluida).
 */

// ==========================================
// CONFIGURACIÓN
// ==========================================
let allowMultipleModules = false; 
let closeOnEsc = true;            

// Función centralizada para abrir/cerrar módulos
const toggleModuleState = (moduleElement) => {
    if (!moduleElement) return;
    
    // Si está desactivado (cerrado), lo abrimos
    if (moduleElement.classList.contains('disabled')) {
        moduleElement.classList.remove('disabled');
        
        // requestAnimationFrame asegura que el navegador renderice el display:flex
        // antes de añadir la clase active, permitiendo la transición de entrada.
        requestAnimationFrame(() => {
            moduleElement.classList.add('active');
        });
    } else {
        // Si está abierto, lo cerramos
        closeModuleWithAnimation(moduleElement);
    }
};

const closeModuleWithAnimation = (moduleElement) => {
    // 1. IMPORTANTE: Limpiar cualquier transformación inline (del drag) INMEDIATAMENTE.
    // Al quitar el style inline y la clase .active simultáneamente, el navegador
    // calcula la ruta desde la posición actual hasta el estado CSS inactivo (translateY 100%).
    const content = moduleElement.querySelector('.menu-content');
    if(content) {
        content.style.transform = ''; 
    }

    // 2. Quitar clase active para iniciar la transición CSS de salida
    moduleElement.classList.remove('active');
    
    // 3. Esperar a que termine la transición (300ms según CSS) para ocultarlo del DOM
    setTimeout(() => {
        if (!moduleElement.classList.contains('active')) {
            moduleElement.classList.add('disabled');
        }
    }, 300);
};

const closeAllActiveModules = (exceptModule = null) => {
    const activeModules = document.querySelectorAll('.module-content.active');
    activeModules.forEach(mod => {
        if (mod !== exceptModule) {
            closeModuleWithAnimation(mod);
        }
    });
};

/* --- LÓGICA DE SOMBRA AL SCROLL --- */
const setupScrollEffects = () => {
    const scrollContainer = document.querySelector('.general-content-scrolleable');
    const topHeader = document.querySelector('.general-content-top');

    if (scrollContainer && topHeader) {
        scrollContainer.addEventListener('scroll', () => {
            if (scrollContainer.scrollTop > 0) {
                if (!topHeader.classList.contains('shadow')) {
                    topHeader.classList.add('shadow');
                }
            } else {
                topHeader.classList.remove('shadow');
            }
        });
    }
};

/* --- LÓGICA DE GESTOS (DRAG) PARA MOBILE --- */
const setupMobileGestures = () => {
    const profileModule = document.querySelector('[data-module="moduleProfile"]');
    if (!profileModule) return;

    const pillContainer = profileModule.querySelector('.pill-container');
    const menuContent = profileModule.querySelector('.menu-content');

    if (!pillContainer || !menuContent) return;

    let startY = 0;
    let currentY = 0;
    let isDragging = false;
    let menuHeight = 0;

    // 1. Iniciar arrastre
    pillContainer.addEventListener('touchstart', (e) => {
        startY = e.touches[0].clientY;
        menuHeight = menuContent.offsetHeight;
        isDragging = true;
        
        // Quitamos la transición para que el menú siga al dedo en tiempo real sin retraso
        menuContent.style.transition = 'none';
    }, { passive: true });

    // 2. Mover
    pillContainer.addEventListener('touchmove', (e) => {
        if (!isDragging) return;
        
        currentY = e.touches[0].clientY;
        const deltaY = currentY - startY;

        // Solo permitir arrastrar hacia abajo (delta positivo)
        // Aplicamos la transformación directa al estilo inline
        if (deltaY > 0) {
            requestAnimationFrame(() => {
                menuContent.style.transform = `translateY(${deltaY}px)`;
            });
        }
    }, { passive: true });

    // 3. Soltar
    pillContainer.addEventListener('touchend', (e) => {
        if (!isDragging) return;
        isDragging = false;

        const deltaY = currentY - startY;
        
        // Restauramos la transición CSS para que cualquier movimiento a partir de ahora sea suave
        menuContent.style.transition = 'transform 0.3s cubic-bezier(0.1, 0.7, 0.1, 1)';

        // Umbral: Si bajó más del 40% de la altura...
        if (deltaY > (menuHeight * 0.4)) {
            // ...Cerramos. La función se encarga de limpiar el transform inline
            // permitiendo que el CSS complete la bajada suavemente.
            closeModuleWithAnimation(profileModule);
        } else {
            // ...Si no, rebote hacia arriba (volvemos a 0)
            requestAnimationFrame(() => {
                menuContent.style.transform = ''; 
            });
        }
        
        // Reset
        startY = 0;
        currentY = 0;
    });
};

const setupEventListeners = () => {
    // 1. Configuración de Módulos (Surface y Profile)
    const moduleTriggers = [
        { action: 'toggleModuleSurface', target: 'moduleSurface' },
        { action: 'toggleModuleProfile', target: 'moduleProfile' }
    ];

    moduleTriggers.forEach(({ action, target }) => {
        const btn = document.querySelector(`[data-action="${action}"]`);
        const moduleEl = document.querySelector(`[data-module="${target}"]`);

        if (btn && moduleEl) {
            btn.addEventListener('click', (e) => {
                e.stopPropagation(); 
                if (!allowMultipleModules && moduleEl.classList.contains('disabled')) {
                    closeAllActiveModules(moduleEl);
                }
                toggleModuleState(moduleEl);
            });
        }
    });

    // 2. Cerrar módulos al hacer clic fuera (Backdrop)
    document.addEventListener('click', (e) => {
        const activeModules = document.querySelectorAll('.module-content.active');
        activeModules.forEach(mod => {
            const menuContent = mod.querySelector('.menu-content');
            
            // Si el clic es dentro del menú blanco, no cerrar
            if (menuContent && menuContent.contains(e.target)) {
                return;
            }
            // Si el clic es en el botón toggle, ignorar (ya tiene su evento)
            if (e.target.closest('[data-action^="toggleModule"]')) {
                return;
            }

            // Si es clic en el fondo oscuro -> Cerrar con animación
            closeModuleWithAnimation(mod);
        });
    });

    // 3. Cerrar con tecla Escape
    if (closeOnEsc) {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAllActiveModules();
            }
        });
    }
};

/* --- LÓGICA DE TEMAS --- */
export const applyAppTheme = (themePreference) => {
    const html = document.documentElement;
    html.classList.remove('light-theme', 'dark-theme', 'system-theme-pending');

    if (themePreference === 'system') {
        const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        html.classList.add(systemDark ? 'dark-theme' : 'light-theme');
    } else if (themePreference === 'dark') {
        html.classList.add('dark-theme');
    } else {
        html.classList.add('light-theme');
    }
};

const setupSystemThemeListener = () => {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {
        if (window.USER_PREFS && window.USER_PREFS.theme === 'system') {
            applyAppTheme('system');
        }
    });
};

const initTheme = () => {
    if (window.USER_PREFS && window.USER_PREFS.theme) {
        applyAppTheme(window.USER_PREFS.theme);
    }
    setupSystemThemeListener();
};

export const initMainController = () => {
    console.log('MainController: Inicializando UI con Gestos...');
    setupEventListeners();
    setupMobileGestures();
    setupScrollEffects();
    initTheme(); 
};