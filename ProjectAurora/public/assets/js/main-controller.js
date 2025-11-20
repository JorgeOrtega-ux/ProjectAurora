// assets/js/main-controller.js

/**
 * Inicializa el control de módulos UI (Menú lateral, Popover de perfil, Notificaciones).
 */
export function initMainController() {

    // Configuración interna
    const allowMultipleModules = false;
    const allowCloseOnEsc = true;
    const allowCloseOnClickOutside = true;

    // Listener principal de clics UI
    document.body.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-action]');

        if (trigger) {
            // Es un botón para abrir/cerrar algo
            const action = trigger.dataset.action;
            let targetModuleId = null;

            if (action === 'toggleModuleSurface') targetModuleId = 'moduleSurface';
            if (action === 'toggleModuleOptions') targetModuleId = 'moduleOptions';

            // [NUEVO] Acción para notificaciones
            if (action === 'toggleModuleNotifications') targetModuleId = 'moduleNotifications';

            if (targetModuleId) {
                e.preventDefault();
                if (!allowMultipleModules) {
                    closeAllModules(targetModuleId);
                }
                toggleModule(targetModuleId);
            }
        } else {
            // Clic fuera de los botones
            if (allowCloseOnClickOutside) {
                // Verificamos si el clic fue dentro de CUALQUIER módulo
                const clickedInsideModule = e.target.closest('[data-module]');

                // Si no fue dentro de un módulo, cerramos todo
                if (!clickedInsideModule) {
                    closeAllModules();
                }
            }
        }
    });

    // Listener para tecla ESC
    document.addEventListener('keydown', (e) => {
        if (allowCloseOnEsc && e.key === 'Escape') {
            closeAllModules();
        }
    });
    
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = searchInput.value.trim();
                if (query.length > 0) {
                    // Codificamos la URL para evitar errores con espacios o caracteres especiales
                    navigateTo(`search?q=${encodeURIComponent(query)}`);
                    // Opcional: Cerrar teclado en móvil
                    searchInput.blur();
                }
            }
        });
    }

    // --- NUEVO: LOGICA DE "CARGAR MÁS RESULTADOS" ---
    document.body.addEventListener('click', async (e) => {
        const loadMoreBtn = e.target.closest('.btn-load-more');
        
        if (loadMoreBtn) {
            e.preventDefault();
            
            // 1. Obtener datos
            const query = loadMoreBtn.dataset.query;
            const offset = parseInt(loadMoreBtn.dataset.offset);
            const originalText = loadMoreBtn.textContent;
            
            // 2. Estado de carga
            loadMoreBtn.textContent = 'Cargando...';
            loadMoreBtn.disabled = true;
            
            try {
                // 3. Petición AJAX
                const url = `${window.BASE_PATH}public/loader.php?section=search&q=${encodeURIComponent(query)}&offset=${offset}&ajax_partial=1`;
                
                const response = await fetch(url);
                const html = await response.text();
                
                // 4. Insertar resultados
                const resultsList = document.getElementById('search-results-list');
                if (resultsList) {
                    // Creamos un elemento temporal para buscar el flag de "hay más"
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    const hasMoreFlag = tempDiv.querySelector('#ajax-has-more-flag');
                    
                    // Insertamos las tarjetas
                    resultsList.insertAdjacentHTML('beforeend', html);
                    
                    // 5. Actualizar botón
                    if (hasMoreFlag) {
                        // Aumentamos el offset (según tu PHP el límite es 2)
                        loadMoreBtn.dataset.offset = offset + 2; 
                        loadMoreBtn.textContent = originalText;
                        loadMoreBtn.disabled = false;
                    } else {
                        // Si no hay más, ocultamos el contenedor del botón
                        loadMoreBtn.parentElement.style.display = 'none';
                    }
                }
                
            } catch (error) {
                console.error(error);
                loadMoreBtn.textContent = 'Error al cargar';
                setTimeout(() => {
                    loadMoreBtn.textContent = originalText;
                    loadMoreBtn.disabled = false;
                }, 2000);
            }
        }
    });
}

/* --- Helpers Internos --- */

function toggleModule(moduleId) {
    const module = document.querySelector(`[data-module="${moduleId}"]`);
    if (module) {
        if (module.classList.contains('disabled')) {
            module.classList.remove('disabled');
            module.classList.add('active');
        } else {
            module.classList.remove('active');
            module.classList.add('disabled');
        }
    }
}

function closeAllModules(exceptModuleId = null) {
    const modules = document.querySelectorAll('[data-module]');
    modules.forEach(mod => {
        if (mod.dataset.module !== exceptModuleId) {
            mod.classList.remove('active');
            mod.classList.add('disabled');
        }
    });
}