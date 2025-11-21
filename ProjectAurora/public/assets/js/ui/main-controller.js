// assets/js/main-controller.js

// Variable de estado global para controlar animaciones
let isAnimating = false;

// Lista de módulos que tienen animación especial en móvil
const allowedMobileMods = ['moduleOptions', 'moduleNotifications'];

export function initMainController() {
    const allowCloseOnEsc = true;
    const allowCloseOnClickOutside = true;

    // --- 1. EVENT LISTENER DE CLICS ---
    document.body.addEventListener('click', async (e) => {
        if (isAnimating) return;

        const target = e.target;

        // A) LÓGICA DE SELECCIÓN EN LISTA DE USUARIOS (NUEVO)
        const userRow = target.closest('.list-item-row');
        if (userRow) {
            // Evitamos que el click en botones de acción dispare la selección
            // si en el futuro agregas botones dentro de la fila.
            if (!target.closest('button') && !target.closest('a')) {
                const container = userRow.closest('.list-body');
                if (container) {
                    // 1. Deseleccionar todos los demás
                    container.querySelectorAll('.list-item-row.selected').forEach(row => {
                        row.classList.remove('selected');
                    });
                }
                // 2. Seleccionar el actual
                userRow.classList.add('selected');
                return; // Opcional: detenemos propagación si es necesario
            }
        }

        // B) LÓGICA DE MÓDULOS (MENÚS)
        const trigger = target.closest('[data-action]');
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

        const loadMoreBtn = target.closest('.btn-load-more');
        if (loadMoreBtn) {
            e.preventDefault();
            await handleLoadMore(loadMoreBtn);
            return;
        }

        if (allowCloseOnClickOutside) {
            const clickedInsideContent = target.closest('.menu-content');
            const clickedInsideTrigger = target.closest('[data-action^="toggleModule"]'); 

            if (!clickedInsideContent && !clickedInsideTrigger) {
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
    const supportsMobileAnim = allowedMobileMods.includes(moduleId);

    if (module.classList.contains('active')) {
        if (isMobile && supportsMobileAnim) {
            closeWithAnimation(module);
        } else {
            module.classList.remove('active');
            module.classList.add('disabled');
        }
    } else {
        closeAllModules(moduleId); 
        module.classList.remove('disabled');
        module.classList.add('active');

        if (isMobile && supportsMobileAnim) {
            animateOpen(module);
        }
    }
}

function getContentElement(module) {
    return module.querySelector('.menu-content');
}

function animateOpen(module) {
    const content = getContentElement(module);
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
    const content = getContentElement(module);
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

export function closeAllModules(exceptModuleId = null, animate = true) {
    const modules = document.querySelectorAll('[data-module]');
    const isMobile = window.innerWidth <= 468;

    modules.forEach(mod => {
        const modId = mod.dataset.module;
        if (modId !== exceptModuleId && mod.classList.contains('active')) {
            const supportsMobileAnim = allowedMobileMods.includes(modId);

            if (supportsMobileAnim && isMobile && animate) {
                closeWithAnimation(mod);
            } else {
                mod.classList.remove('active');
                mod.classList.add('disabled');
                mod.classList.remove('animate-fade-in', 'animate-fade-out');
                const content = getContentElement(mod);
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