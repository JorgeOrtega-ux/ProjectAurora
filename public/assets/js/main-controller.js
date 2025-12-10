/**
 * MainController.js
 * Encargado de la lógica de UI (Menús, Buscador, Interacción visual).
 */

// ==========================================
// CONFIGURACIÓN
// ==========================================
let allowMultipleModules = false; 
let closeOnEsc = true;            

const toggleModuleState = (moduleElement) => {
    if (!moduleElement) return;
    if (moduleElement.classList.contains('disabled')) {
        moduleElement.classList.remove('disabled');
        moduleElement.classList.add('active');
    } else {
        moduleElement.classList.remove('active');
        moduleElement.classList.add('disabled');
    }
};

const closeAllActiveModules = (exceptModule = null) => {
    const activeModules = document.querySelectorAll('.module-content.active');
    activeModules.forEach(mod => {
        if (mod !== exceptModule) {
            mod.classList.remove('active');
            mod.classList.add('disabled');
        }
    });
};

/* --- NUEVO: LÓGICA DE SOMBRA AL SCROLL --- */
const setupScrollEffects = () => {
    // 1. Identificar el contenedor que hace scroll y el header
    const scrollContainer = document.querySelector('.general-content-scrolleable');
    const topHeader = document.querySelector('.general-content-top');

    // 2. Validación de seguridad por si los elementos no existen
    if (scrollContainer && topHeader) {
        // 3. Agregar el listener
        scrollContainer.addEventListener('scroll', () => {
            // Si el scroll vertical es mayor a 0, agregamos la sombra
            if (scrollContainer.scrollTop > 0) {
                if (!topHeader.classList.contains('shadow')) {
                    topHeader.classList.add('shadow');
                }
            } else {
                // Si estamos arriba del todo, quitamos la sombra
                topHeader.classList.remove('shadow');
            }
        });
    }
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

    // 2. Cerrar módulos al hacer clic fuera
    document.addEventListener('click', (e) => {
        const modules = document.querySelectorAll('.module-content.active');
        modules.forEach(mod => {
            if (!mod.contains(e.target)) {
                 mod.classList.remove('active');
                 mod.classList.add('disabled');
            }
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

export const initMainController = () => {
    console.log('MainController: Inicializando UI...');
    setupEventListeners();
    
    // Inicializar efecto de scroll
    setupScrollEffects();
};