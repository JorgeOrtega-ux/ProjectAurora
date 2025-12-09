/**
 * MainController.js
 * Maneja la lógica de la interfaz principal, incluyendo menús laterales,
 * perfiles y la barra de búsqueda.
 */

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

const setupEventListeners = () => {
    // 1. Configuración de Módulos (Surface y Profile)
    // Mapeamos el 'data-action' del botón con el 'data-module' del contenido
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
                toggleModuleState(moduleEl);
            });
        }
    });

    // 2. Configuración del Buscador (Migrado desde tu script original)
    const searchBtn = document.getElementById('searchToggleBtn');
    const headerCenter = document.getElementById('headerCenter');
    
    if (searchBtn && headerCenter) {
        const searchInput = headerCenter.querySelector('input');

        searchBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            // Alternar la clase 'active' en el headerCenter
            headerCenter.classList.toggle('active');

            // Enfocar el input si se abre
            if (headerCenter.classList.contains('active') && searchInput) {
                searchInput.focus();
            }
        });
    }

    // 3. (Opcional) Cerrar módulos al hacer clic fuera de ellos
    document.addEventListener('click', (e) => {
        const modules = document.querySelectorAll('.module-content.active');
        modules.forEach(mod => {
            // Si el clic no fue dentro del módulo ni en un botón de acción
            if (!mod.contains(e.target)) {
                 mod.classList.remove('active');
                 mod.classList.add('disabled');
            }
        });
    });
};

// Función inicializadora exportada
export const initMainController = () => {
    console.log('MainController: Inicializando...');
    setupEventListeners();
};