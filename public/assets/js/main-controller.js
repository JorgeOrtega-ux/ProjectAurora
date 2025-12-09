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

    // 2. Configuración del Buscador
    const searchBtn = document.getElementById('searchToggleBtn');
    const headerCenter = document.getElementById('headerCenter');
    
    if (searchBtn && headerCenter) {
        const searchInput = headerCenter.querySelector('input');
        searchBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            headerCenter.classList.toggle('active');
            if (headerCenter.classList.contains('active') && searchInput) {
                searchInput.focus();
            }
        });
    }

    // 3. Cerrar módulos al hacer clic fuera
    document.addEventListener('click', (e) => {
        const modules = document.querySelectorAll('.module-content.active');
        modules.forEach(mod => {
            if (!mod.contains(e.target)) {
                 mod.classList.remove('active');
                 mod.classList.add('disabled');
            }
        });
    });

    // 4. Cerrar con tecla Escape
    if (closeOnEsc) {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAllActiveModules();
                if (headerCenter && headerCenter.classList.contains('active')) {
                    headerCenter.classList.remove('active');
                }
            }
        });
    }
};

export const initMainController = () => {
    console.log('MainController: Inicializando UI...');
    setupEventListeners();
};