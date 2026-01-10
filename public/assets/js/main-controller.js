/* =========================================
   MAIN CONTROLLER
   Handles UI logic and module management
   ========================================= */

// CONFIGURATION
// Determines if multiple modules can be open simultaneously
var allowMultipleModules = false; 
// Determines if modules can be closed using the ESC key
var allowCloseOnEsc = true;

// ACTION MAP
const moduleActionMap = {
    'toggleModuleSurface': '.module-surface',
    'toggleModuleOptions': '.module-options'
};

/**
 * Closes all modules except the one explicitly passed (optional).
 * @param {HTMLElement} exceptionElement - The module to keep open (optional)
 */
function closeAllModules(exceptionElement) {
    var allModules = document.querySelectorAll('.module-content');
    
    allModules.forEach(function(module) {
        if (exceptionElement && module === exceptionElement) return;

        module.classList.remove('active');
        module.classList.add('disabled');
    });
}

/**
 * Toggles the state of a specific module.
 * @param {string} moduleSelector - The CSS selector of the module to toggle
 */
function toggleModule(moduleSelector) {
    var module = document.querySelector(moduleSelector);
    if (!module) return;

    var isActive = module.classList.contains('active');

    // If multiple modules are not allowed, close others before opening this one.
    if (!allowMultipleModules && !isActive) {
        closeAllModules(module);
    }

    if (isActive) {
        // Deactivate
        module.classList.remove('active');
        module.classList.add('disabled');
    } else {
        // Activate
        module.classList.remove('disabled');
        module.classList.add('active');
    }
}

/**
 * Initializes the main controller events.
 */
function initMainController() {
    console.log('Main Controller: Initialized');

    // 1. HEADER BUTTON HANDLERS
    var triggerButtons = document.querySelectorAll('[data-action]');
    
    triggerButtons.forEach(function(btn) {
        btn.addEventListener('click', function(event) {
            // Stop propagation prevents the document click from firing immediately
            event.stopPropagation();

            var action = btn.getAttribute('data-action');
            var targetSelector = moduleActionMap[action];
            
            if (targetSelector) {
                toggleModule(targetSelector);
            }
        });
    });

    // 2. OUTSIDE CLICK HANDLER (CLOSE MODULES)
    document.addEventListener('click', function(event) {
        // Check if the click originated from within a module
        var isClickInsideModule = event.target.closest('.module-content');

        if (!isClickInsideModule) {
            closeAllModules();
        }
    });

    // 3. ESC KEY HANDLER (CLOSE MODULES)
    document.addEventListener('keydown', function(event) {
        if (allowCloseOnEsc && event.key === 'Escape') {
            closeAllModules();
        }
    });
}

export { initMainController };