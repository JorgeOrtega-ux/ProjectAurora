/* =========================================
   MAIN CONTROLLER
   Handles UI logic and module management
   ========================================= */

// CONFIGURATION
var allowMultipleModules = false; 
var allowCloseOnEsc = true;
var _prefsManager = null; // Instancia local del gestor

const moduleActionMap = {
    'toggleModuleSurface': '.module-surface',
    'toggleModuleOptions': '.module-options'
};

function closeAllModules(exceptionElement) {
    const globalModules = document.querySelectorAll('.module-content');
    globalModules.forEach(module => {
        if (exceptionElement && (module === exceptionElement || module.contains(exceptionElement))) return;
        module.classList.remove('active');
        module.classList.add('disabled');
    });

    const popovers = document.querySelectorAll('.popover-module');
    popovers.forEach(popover => {
        const wrapper = popover.closest('.trigger-select-wrapper');
        if (exceptionElement && wrapper && wrapper.contains(exceptionElement)) return;
        
        popover.classList.remove('active');
        if(wrapper) {
            const selector = wrapper.querySelector('.trigger-selector');
            if(selector) selector.classList.remove('active');
        }
    });
}

function toggleGlobalModule(moduleSelector) {
    const module = document.querySelector(moduleSelector);
    if (!module) return;
    const isActive = module.classList.contains('active');
    
    if (!allowMultipleModules && !isActive) closeAllModules(module);

    if (isActive) {
        module.classList.remove('active');
        module.classList.add('disabled');
    } else {
        module.classList.remove('disabled');
        module.classList.add('active');
    }
}

function handleDropdownToggle(triggerWrapper) {
    const popover = triggerWrapper.querySelector('.popover-module');
    const selector = triggerWrapper.querySelector('.trigger-selector');
    if (!popover) return;

    const isActive = popover.classList.contains('active');
    closeAllModules(popover);

    if (isActive) {
        popover.classList.remove('active');
        if(selector) selector.classList.remove('active');
    } else {
        popover.classList.add('active');
        if(selector) selector.classList.add('active');
    }
}

/**
 * Actualiza la UI y guarda la preferencia
 */
function handleOptionSelect(optionLink) {
    const wrapper = optionLink.closest('.trigger-select-wrapper');
    if (!wrapper) return;

    const label = optionLink.getAttribute('data-label');
    const value = optionLink.getAttribute('data-value');
    
    // Identificar qué tipo de preferencia es basado en un ID o data attribute del wrapper
    const prefType = wrapper.id; // 'pref-trigger-language' o 'pref-trigger-theme'

    // 1. Guardar Preferencia usando el Manager
    if (_prefsManager) {
        if (prefType === 'pref-trigger-language') {
            _prefsManager.setLanguage(value);
        } else if (prefType === 'pref-trigger-theme') {
            _prefsManager.setTheme(value);
        }
    }

    // 2. Actualizar UI
    const textSpan = wrapper.querySelector('.trigger-select-text');
    if (textSpan && label) textSpan.textContent = label;

    const allLinks = wrapper.querySelectorAll('.menu-link');
    allLinks.forEach(link => link.classList.remove('active'));
    optionLink.classList.add('active');

    closeAllModules();
}

function initScrollEffects() {
    document.addEventListener('scroll', function(event) {
        const target = event.target;
        if (!target.classList) return;

        if (target.classList.contains('general-content-scrolleable')) {
            const header = document.querySelector('.general-content-top');
            if (header) target.scrollTop > 0 ? header.classList.add('shadow') : header.classList.remove('shadow');
        }

        if (target.classList.contains('menu-list--scrollable')) {
            const menuContent = target.closest('.menu-content');
            if (menuContent) {
                const searchHeader = menuContent.querySelector('.menu-search-header');
                if (searchHeader) target.scrollTop > 0 ? searchHeader.classList.add('shadow') : searchHeader.classList.remove('shadow');
            }
        }
    }, true); 
}

/**
 * Sincroniza la interfaz visual (checkboxes y textos) con los datos guardados en LocalStorage.
 * Se llama cada vez que cambia el contenido (Navegación SPA)
 */
function syncUIWithStoredPrefs() {
    if (!_prefsManager) return;
    const currentPrefs = _prefsManager.getCurrentSettings();

    // 1. Sincronizar Idioma
    const langWrapper = document.getElementById('pref-trigger-language');
    if (langWrapper) {
        const activeLink = langWrapper.querySelector(`.menu-link[data-value="${currentPrefs.language}"]`);
        if (activeLink) {
            // Actualizar texto del selector
            const textSpan = langWrapper.querySelector('.trigger-select-text');
            if (textSpan) textSpan.textContent = activeLink.getAttribute('data-label');
            
            // Actualizar clase active en la lista
            langWrapper.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
            activeLink.classList.add('active');
        }
    }

    // 2. Sincronizar Tema
    const themeWrapper = document.getElementById('pref-trigger-theme');
    if (themeWrapper) {
        const activeLink = themeWrapper.querySelector(`.menu-link[data-value="${currentPrefs.theme}"]`);
        if (activeLink) {
            const textSpan = themeWrapper.querySelector('.trigger-select-text');
            if (textSpan) textSpan.textContent = activeLink.getAttribute('data-label');

            themeWrapper.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
            activeLink.classList.add('active');
        }
    }

    // 3. Sincronizar Toggle de Enlaces
    const linksToggle = document.getElementById('pref-open-links');
    if (linksToggle) {
        linksToggle.checked = currentPrefs.openLinksNewTab;
    }
}

/**
 * Inicialización principal
 * @param {Object} prefsManagerInstance - Instancia importada de preferences-manager
 */
function initMainController(prefsManagerInstance) {
    console.log('Main Controller: Initialized');
    _prefsManager = prefsManagerInstance;

    initScrollEffects();

    // Observer para detectar cuando se carga contenido nuevo (SPA) y actualizar la UI de preferencias
    const observer = new MutationObserver(() => {
        syncUIWithStoredPrefs();
    });
    const appContent = document.getElementById('app-content');
    if (appContent) {
        observer.observe(appContent, { childList: true, subtree: false });
    }

    // DELEGACIÓN DE EVENTOS
    document.addEventListener('click', function(event) {
        const target = event.target;

        const btn = target.closest('[data-action]');
        if (btn) {
            const action = btn.getAttribute('data-action');
            
            if (moduleActionMap[action]) {
                event.stopPropagation();
                toggleGlobalModule(moduleActionMap[action]);
                return;
            }
            
            if (action === 'select-option') {
                event.preventDefault(); 
                handleOptionSelect(btn);
                return;
            }
        }

        const triggerWrapper = target.closest('[data-trigger="dropdown"]');
        if (triggerWrapper) {
            if (target.tagName === 'INPUT') return;
            event.stopPropagation();
            handleDropdownToggle(triggerWrapper);
            return;
        }

        const isInsideModule = target.closest('.module-content') || target.closest('.popover-module');
        if (!isInsideModule) closeAllModules();
    });

    document.addEventListener('keydown', function(event) {
        if (allowCloseOnEsc && event.key === 'Escape') closeAllModules();
    });

    // Listener especial para el Toggle Switch de Enlaces (Change event)
    document.addEventListener('change', function(event) {
        if (event.target && event.target.id === 'pref-open-links') {
            if (_prefsManager) {
                _prefsManager.setOpenLinksNewTab(event.target.checked);
                console.log('Preferencia Enlaces actualizada:', event.target.checked);
            }
        }
    });

    // Ejecución inicial por si acaso cargamos directo en la URL de settings
    syncUIWithStoredPrefs();
}

export { initMainController };