// assets/js/main-controller.js

// Variable de estado global para controlar animaciones
let isAnimating = false;

export function initMainController() {
    const allowCloseOnEsc = true;
    const allowCloseOnClickOutside = true;

    // --- 1. EVENT LISTENER DE CLICS ---
    document.body.addEventListener('click', async (e) => {
        if (isAnimating) return;

        const trigger = e.target.closest('[data-action]');
        
        if (trigger) {
            const action = trigger.dataset.action;
            let targetModuleId = null;

            if (action === 'toggleModuleSurface') targetModuleId = 'moduleSurface';
            if (action === 'toggleModuleOptions') targetModuleId = 'moduleOptions';
            if (action === 'toggleModuleNotifications') targetModuleId = 'moduleNotifications';

            if (targetModuleId) {
                e.preventDefault();
                toggleModule(targetModuleId);
                return; 
            }
        }

        const loadMoreBtn = e.target.closest('.btn-load-more');
        if (loadMoreBtn) {
            e.preventDefault();
            await handleLoadMore(loadMoreBtn);
            return;
        }

        if (allowCloseOnClickOutside) {
            const clickedInsideContent = e.target.closest('.menu-content');
            const clickedInsideNotifs = e.target.closest('.notifications-container');
            const clickedInsideTrigger = e.target.closest('[data-action^="toggleModule"]'); 

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

function toggleModule(moduleId) {
    const module = document.querySelector(`[data-module="${moduleId}"]`);
    if (!module) return;

    const isMobile = window.innerWidth <= 468;
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
        closeAllModules(moduleId); // Cierra otros
        
        module.classList.remove('disabled');
        module.classList.add('active');

        if (isMobile && isOptions) {
            animateOpen(module);
        }
    }
}

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

function closeWithAnimation(module) {
    const content = module.querySelector('.menu-content');
    if (!content) {
        module.classList.remove('active');
        module.classList.add('disabled');
        return;
    }

    isAnimating = true;
    module.classList.add('animate-fade-out');
    content.classList.add('animate-out');

    module.addEventListener('animationend', (e) => {
        if (e.target === module) {
            module.classList.remove('active', 'animate-fade-out');
            module.classList.add('disabled');
            content.classList.remove('animate-out');
            content.removeAttribute('style'); 
            isAnimating = false;
        }
    }, { once: true });
}

/**
 * [MODIFICADO] Ahora acepta `animate` (default true).
 * Si pasas false, cierra de golpe (útil cuando ya arrastraste el menú).
 */
export function closeAllModules(exceptModuleId = null, animate = true) {
    const modules = document.querySelectorAll('[data-module]');
    const isMobile = window.innerWidth <= 468;

    modules.forEach(mod => {
        if (mod.dataset.module !== exceptModuleId && mod.classList.contains('active')) {
            
            // Solo animamos si es móvil, es el menú de opciones Y si `animate` es true
            if (mod.dataset.module === 'moduleOptions' && isMobile && animate) {
                closeWithAnimation(mod);
            } else {
                // Cierre directo (sin CSS keyframes)
                mod.classList.remove('active');
                mod.classList.add('disabled');

                // Limpieza de seguridad por si quedaron estilos inline del drag
                mod.classList.remove('animate-fade-in', 'animate-fade-out');
                const content = mod.querySelector('.menu-content');
                if(content) {
                    content.classList.remove('animate-in', 'animate-out');
                    content.removeAttribute('style'); 
                }
            }
        }
    });
}

export function isAppAnimating() {
    return isAnimating;
}

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
                loadMoreBtn.dataset.offset = offset + 2;
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