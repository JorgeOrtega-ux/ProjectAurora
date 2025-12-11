/**
 * MainController.js
 * Encargado de la lógica de UI (Menús, Buscador, Interacción visual).
 * ACTUALIZADO: Manejo de temas (Light/Dark/System).
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

/* --- NUEVO: LÓGICA DE TEMAS --- */
export const applyAppTheme = (themePreference) => {
    const html = document.documentElement;
    // Eliminamos clases previas
    html.classList.remove('light-theme', 'dark-theme', 'system-theme-pending');

    if (themePreference === 'system') {
        // Checar preferencia del SO
        const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        html.classList.add(systemDark ? 'dark-theme' : 'light-theme');
    } else if (themePreference === 'dark') {
        html.classList.add('dark-theme');
    } else {
        // Por defecto light
        html.classList.add('light-theme');
    }
};

// Listener para cambios en el SO si está en modo system
const setupSystemThemeListener = () => {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {
        // Solo actualizar si la preferencia del usuario es 'system'
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
    console.log('MainController: Inicializando UI...');
    setupEventListeners();
    setupScrollEffects();
    initTheme(); // Inicializar tema
};