// assets/js/main-controller.js

// Variable de estado global para controlar animaciones
let isAnimating = false;

/**
 * Inicializa el control de módulos UI.
 */
export function initMainController() {
    const allowCloseOnEsc = true;
    const allowCloseOnClickOutside = true;

    // --- 1. EVENT LISTENER DE CLICS (Navegación y Módulos) ---
    document.body.addEventListener('click', async (e) => {
        // Si hay una animación en curso, ignoramos clics para evitar conflictos
        if (isAnimating) return;

        const trigger = e.target.closest('[data-action]');
        
        // A. Manejo de Botones de Acción (Toggles)
        if (trigger) {
            const action = trigger.dataset.action;
            let targetModuleId = null;

            if (action === 'toggleModuleSurface') targetModuleId = 'moduleSurface';
            if (action === 'toggleModuleOptions') targetModuleId = 'moduleOptions';
            if (action === 'toggleModuleNotifications') targetModuleId = 'moduleNotifications';

            if (targetModuleId) {
                e.preventDefault();
                toggleModule(targetModuleId);
                return; // Salimos para no procesar click outside
            }
        }

        // B. Manejo de "Cargar Más" (Tu lógica existente)
        const loadMoreBtn = e.target.closest('.btn-load-more');
        if (loadMoreBtn) {
            e.preventDefault();
            await handleLoadMore(loadMoreBtn);
            return;
        }

        // C. Manejo de Clic Fuera (Cerrar módulos)
        if (allowCloseOnClickOutside) {
            // Verificamos si el clic fue dentro de un contenido seguro
            const clickedInsideContent = e.target.closest('.menu-content');
            const clickedInsideNotifs = e.target.closest('.notifications-container');
            const clickedInsideTrigger = e.target.closest('[data-action^="toggleModule"]'); // Evitar cerrar si clicamos el mismo botón

            // Si NO fue dentro de contenido ni notificaciones ni el botón trigger, cerramos.
            if (!clickedInsideContent && !clickedInsideNotifs && !clickedInsideTrigger) {
                closeAllModules();
            }
        }
    });

    // --- 2. EVENT LISTENER DE TECLADO ---
    document.addEventListener('keydown', (e) => {
        if (allowCloseOnEsc && e.key === 'Escape') {
            closeAllModules();
        }
    });
    
    // --- 3. LÓGICA DEL BUSCADOR ---
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = searchInput.value.trim();
                if (query.length > 0) {
                    if (window.navigateTo) {
                        window.navigateTo(`search?q=${encodeURIComponent(query)}`);
                    } else {
                        window.location.href = `search?q=${encodeURIComponent(query)}`;
                    }
                    searchInput.blur();
                }
            }
        });
    }
}

/**
 * Función para abrir/cerrar módulos con soporte de animación móvil.
 */
function toggleModule(moduleId) {
    const module = document.querySelector(`[data-module="${moduleId}"]`);
    if (!module) return;

    const isMobile = window.innerWidth <= 468;
    // Solo aplicamos animación especial al menú de opciones en móvil
    const isOptions = moduleId === 'moduleOptions';

    if (module.classList.contains('active')) {
        // CERRAR
        if (isMobile && isOptions) {
            closeWithAnimation(module);
        } else {
            module.classList.remove('active');
            module.classList.add('disabled');
        }
    } else {
        // ABRIR
        // Primero cerramos cualquier otro módulo abierto
        closeAllModules(moduleId);
        
        module.classList.remove('disabled');
        module.classList.add('active');

        if (isMobile && isOptions) {
            animateOpen(module);
        }
    }
}

/**
 * Anima la apertura del menú en móvil.
 */
function animateOpen(module) {
    const content = module.querySelector('.menu-content');
    if (!content) return;

    isAnimating = true;
    module.classList.add('animate-fade-in');
    content.classList.add('animate-in');

    content.addEventListener('animationend', () => {
        module.classList.remove('animate-fade-in');
        content.classList.remove('animate-in');
        isAnimating = false;
    }, { once: true });
}

/**
 * Anima el cierre del menú en móvil.
 */
function closeWithAnimation(module) {
    const content = module.querySelector('.menu-content');
    if (!content) {
        module.classList.remove('active');
        module.classList.add('disabled');
        return;
    }

    isAnimating = true;
    // Clases definidas en tu CSS nuevo
    module.classList.add('animate-fade-out');
    content.classList.add('animate-out');

    module.addEventListener('animationend', (e) => {
        // Aseguramos que capturamos el final de la animación correcta
        if (e.target === module) {
            module.classList.remove('active', 'animate-fade-out');
            module.classList.add('disabled');
            content.classList.remove('animate-out');
            
            // Limpiamos estilos inline (importante si se usó drag)
            content.removeAttribute('style'); 
            isAnimating = false;
        }
    }, { once: true });
}

/**
 * Cierra todos los módulos activos.
 * Se exporta para usarlo en drag-controller.js
 */
export function closeAllModules(exceptModuleId = null) {
    const modules = document.querySelectorAll('[data-module]');
    const isMobile = window.innerWidth <= 468;

    modules.forEach(mod => {
        if (mod.dataset.module !== exceptModuleId && mod.classList.contains('active')) {
            // Si es el menú de opciones en móvil, usar animación de salida
            if (mod.dataset.module === 'moduleOptions' && isMobile) {
                closeWithAnimation(mod);
            } else {
                mod.classList.remove('active');
                mod.classList.add('disabled');
            }
        }
    });
}

/**
 * Retorna si hay una animación en curso.
 */
export function isAppAnimating() {
    return isAnimating;
}

/**
 * Lógica para cargar más resultados (Tu código original refactorizado)
 */
async function handleLoadMore(loadMoreBtn) {
    const query = loadMoreBtn.dataset.query;
    const offset = parseInt(loadMoreBtn.dataset.offset);
    const originalText = loadMoreBtn.textContent;
    
    loadMoreBtn.textContent = 'Cargando...';
    loadMoreBtn.disabled = true;
    
    try {
        const url = `${window.BASE_PATH}public/loader.php?section=search&q=${encodeURIComponent(query)}&offset=${offset}&ajax_partial=1`;
        const response = await fetch(url);
        const html = await response.text();
        
        const resultsList = document.getElementById('search-results-list');
        if (resultsList) {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            const hasMoreFlag = tempDiv.querySelector('#ajax-has-more-flag');
            resultsList.insertAdjacentHTML('beforeend', html);
            
            if (hasMoreFlag) {
                loadMoreBtn.dataset.offset = offset + 2; // Ajusta esto según tu paginación
                loadMoreBtn.textContent = originalText;
                loadMoreBtn.disabled = false;
            } else {
                if (loadMoreBtn.parentElement) {
                    loadMoreBtn.parentElement.style.display = 'none';
                }
            }
        }
    } catch (error) {
        console.error(error);
        loadMoreBtn.textContent = 'Error';
    }
}