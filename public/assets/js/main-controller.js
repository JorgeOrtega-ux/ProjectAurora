/**
 * MainController.js
 * Maneja la lógica de la interfaz principal, incluyendo menús laterales,
 * perfiles y la barra de búsqueda.
 */

// ==========================================
// CONFIGURACIÓN
// ==========================================
let allowMultipleModules = false; // true: permite varios módulos abiertos | false: solo uno a la vez
let closeOnEsc = true;            // true: permite cerrar módulos con la tecla ESC | false: no hace nada

// Función auxiliar para alternar visibilidad (active/disabled)
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

// Función auxiliar para cerrar todos los módulos activos
// Recibe un módulo "excepción" opcional para no cerrarlo (útil cuando estamos abriendo ese mismo)
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
                e.stopPropagation(); // Evita que el clic se propague al document
                
                // LÓGICA AGREGADA: Verificar si se permite más de un módulo
                // Si NO se permiten múltiples y el módulo actual está cerrado (se va a abrir), cerramos los demás.
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
            
            // Opcional: Si quieres que el buscador también se comporte como "módulo único",
            // podrías llamar a closeAllActiveModules() aquí.
            
            headerCenter.classList.toggle('active');

            if (headerCenter.classList.contains('active') && searchInput) {
                searchInput.focus();
            }
        });
    }

    // 3. Cerrar módulos al hacer clic fuera de ellos
    document.addEventListener('click', (e) => {
        const modules = document.querySelectorAll('.module-content.active');
        modules.forEach(mod => {
            if (!mod.contains(e.target)) {
                 mod.classList.remove('active');
                 mod.classList.add('disabled');
            }
        });
        
        // Opcional: Cerrar buscador si se hace clic fuera (si se desea)
        // if (headerCenter && headerCenter.classList.contains('active') && !headerCenter.contains(e.target)) {
        //    headerCenter.classList.remove('active');
        // }
    });

    // 4. LÓGICA AGREGADA: Cerrar con tecla Escape
    if (closeOnEsc) {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAllActiveModules();
                
                // También cerramos el buscador si está activo
                if (headerCenter && headerCenter.classList.contains('active')) {
                    headerCenter.classList.remove('active');
                }
            }
        });
    }
};

// Función inicializadora exportada
export const initMainController = () => {
    console.log('MainController: Inicializando...');
    setupEventListeners();
};