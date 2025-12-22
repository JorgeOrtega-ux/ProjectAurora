/**
 * url-manager.js
 * Gestiona la navegación SPA
 */

export function initUrlManager() {
    console.log("SPA Router: Iniciado");

    // Manejar navegación inicial (popstate)
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            loadContent(event.state.section);
        } else {
            // Si no hay estado (ej: carga inicial o hash), recargar o manejar según URL
            location.reload();
        }
    });

    // Interceptar clics en elementos con data-nav
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('[data-nav]');
        if (link) {
            e.preventDefault();
            const section = link.dataset.nav;
            navigateTo(section);
            
            // Cerrar menú móvil si está abierto (Opcional, depende de tu UI)
            const activeModules = document.querySelectorAll('.module-content.active');
            activeModules.forEach(mod => {
                mod.classList.remove('active');
                mod.classList.add('disabled');
            });
        }
    });
}

export function navigateTo(section) {
    const basePath = window.BASE_PATH || '/ProjectAurora/';
    
    // Construir URL amigable
    const url = (section === 'main') ? basePath : basePath + section;
    
    // Actualizar historial
    history.pushState({ section: section }, '', url);
    
    // Cargar contenido
    loadContent(section);
    
    // Actualizar menú activo visualmente
    updateActiveMenu(section);
}

async function loadContent(section) {
    const container = document.querySelector('.general-content-scrolleable');
    if (!container) return;

    // Mostrar loader
    container.innerHTML = '<div style="display:flex;justify-content:center;padding:50px;"><div class="spinner"></div></div>';
    
    try {
        // Petición al loader PHP
        const response = await fetch(`${window.BASE_PATH}public/loader.php?section=${section}`);
        const html = await response.text();
        
        container.innerHTML = html;
        container.scrollTop = 0; // Reset scroll
        
    } catch (error) {
        console.error("Error cargando sección:", error);
        container.innerHTML = '<p>Error de conexión.</p>';
    }
}

function updateActiveMenu(section) {
    document.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
    
    // Buscar el link exacto
    let links = document.querySelectorAll(`.menu-link[data-nav="${section}"]`);
    links.forEach(l => l.classList.add('active'));
}