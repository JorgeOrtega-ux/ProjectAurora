/**
 * url-manager.js
 * Gestiona la navegación SPA.
 * REFACTORIZADO: Emisión de eventos de ciclo de vida.
 */

export function initUrlManager() {
    console.log("SPA Router: Iniciado");

    // Manejar navegación inicial (popstate - botones atrás/adelante)
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            loadContent(event.state.section);
            updateActiveMenu(event.state.section);
        } else {
            // Fallback si no hay estado (reload limpio)
            location.reload();
        }
    });

    // Delegación de clics en enlaces [data-nav]
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('[data-nav]');
        if (link) {
            e.preventDefault();
            const section = link.dataset.nav;
            
            // Navegar solo si no es la sección actual
            if (!link.classList.contains('active')) {
                navigateTo(section);
            }
            
            // UX: Cerrar menús móviles o overlays al navegar
            const activeModules = document.querySelectorAll('.module-content.active');
            activeModules.forEach(mod => {
                if (mod.dataset.module !== 'moduleSurface' || window.innerWidth < 725) {
                     mod.classList.remove('active');
                     mod.classList.add('disabled');
                }
            });
            
            const profileModule = document.querySelector('[data-module="moduleProfile"]');
            if(profileModule) {
                profileModule.classList.remove('active');
                profileModule.classList.add('disabled');
            }
        }
    });
    
    // Marcar menú activo inicial
    const path = window.location.pathname.replace(window.BASE_PATH, '');
    // Eliminamos slashes iniciales/finales para consistencia
    const cleanPath = path.replace(/^\/+|\/+$/g, ''); 
    const currentSection = cleanPath || 'main';
    updateActiveMenu(currentSection);
}

export function navigateTo(section) {
    const basePath = window.BASE_PATH || '/ProjectAurora/';
    const url = (section === 'main') ? basePath : basePath + section;
    
    history.pushState({ section: section }, '', url);
    loadContent(section);
    updateActiveMenu(section);
}

async function loadContent(section) {
    const container = document.querySelector('.general-content-scrolleable');
    if (!container) return;

    // Loader UI
    container.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;"><div class="spinner"></div></div>';
    
    await new Promise(resolve => setTimeout(resolve, 200));

    try {
        const response = await fetch(`${window.BASE_PATH}public/loader.php?section=${section}`);
        const html = await response.text();
        
        // 1. Inyectar HTML
        container.innerHTML = html;
        container.scrollTop = 0; 
        
        // 2. [ARQUITECTURA] Disparar evento de ciclo de vida
        // Esto avisa a app-init.js que la vista está lista para inicializar controladores.
        const event = new CustomEvent('spa:view_loaded', { detail: { section } });
        document.dispatchEvent(event);
        
    } catch (error) {
        console.error("Error cargando sección:", error);
        container.innerHTML = '<div style="padding: 20px;"><p>Error de conexión al cargar la sección.</p></div>';
    }
}

function updateActiveMenu(section) {
    document.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
    
    // Buscar coincidencia exacta
    let links = document.querySelectorAll(`.menu-link[data-nav="${section}"]`);
    links.forEach(l => l.classList.add('active'));

    // Gestión visual de menús (Main vs Settings)
    const navMain = document.getElementById('nav-main');
    const navSettings = document.getElementById('nav-settings');

    if (navMain && navSettings) {
        if (section.startsWith('settings/')) {
            navMain.style.display = 'none';
            navSettings.style.display = 'flex';
            navSettings.style.flexDirection = 'column';
            navSettings.style.gap = '4px';
        } else {
            navSettings.style.display = 'none';
            navMain.style.display = 'flex';
            navMain.style.flexDirection = 'column';
            navMain.style.gap = '4px';
        }
    }
}