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
            updateActiveMenu(event.state.section);
        } else {
            location.reload();
        }
    });

    // Interceptar clics en elementos con data-nav
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('[data-nav]');
        if (link) {
            e.preventDefault();
            const section = link.dataset.nav;
            
            // Si estamos haciendo clic, probablemente queremos navegar.
            if (!link.classList.contains('active')) {
                navigateTo(section);
            }
            
            // Cerrar menú móvil si está abierto
            // EXCEPCIÓN: Si estamos en modo escritorio (sidebar visible), no queremos "cerrarlo" visualmente
            // pero la lógica actual usa 'active' para móvil. Lo dejamos así para consistencia móvil.
            const activeModules = document.querySelectorAll('.module-content.active');
            activeModules.forEach(mod => {
                // No cerramos el surface si es navegación interna del surface
                // Pero sí cerramos el menú de perfil si venimos de ahí
                if (mod.dataset.module !== 'moduleSurface' || window.innerWidth < 725) {
                     mod.classList.remove('active');
                     mod.classList.add('disabled');
                }
            });
            
            // Específico: Si clicamos desde el menú de perfil, cerrarlo explícitamente
            const profileModule = document.querySelector('[data-module="moduleProfile"]');
            if(profileModule) {
                profileModule.classList.remove('active');
                profileModule.classList.add('disabled');
            }
        }
    });
    
    // Al iniciar, determinar qué menú mostrar según la URL actual
    // Obtenemos la sección actual desde la URL (o default 'main')
    const path = window.location.pathname.replace(window.BASE_PATH, '');
    const currentSection = path || 'main';
    updateActiveMenu(currentSection);
}

export function navigateTo(section) {
    const basePath = window.BASE_PATH || '/ProjectAurora/';
    
    // Construir URL amigable
    const url = (section === 'main') ? basePath : basePath + section;
    
    // Actualizar historial
    history.pushState({ section: section }, '', url);
    
    // Cargar contenido
    loadContent(section);
    
    // Actualizar menú activo y visibilidad de listas
    updateActiveMenu(section);
}

async function loadContent(section) {
    const container = document.querySelector('.general-content-scrolleable');
    if (!container) return;

    // Mostrar loader (Centrado)
    container.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;"><div class="spinner"></div></div>';
    
    // === RETRASO ARTIFICIAL (200ms) ===
    await new Promise(resolve => setTimeout(resolve, 200));

    try {
        const response = await fetch(`${window.BASE_PATH}public/loader.php?section=${section}`);
        const html = await response.text();
        
        container.innerHTML = html;
        container.scrollTop = 0; 
        
    } catch (error) {
        console.error("Error cargando sección:", error);
        container.innerHTML = '<div style="padding: 20px;"><p>Error de conexión al cargar la sección.</p></div>';
    }
}

function updateActiveMenu(section) {
    // 1. Limpiar activos anteriores
    document.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
    
    // 2. Marcar el nuevo activo
    let links = document.querySelectorAll(`.menu-link[data-nav="${section}"]`);
    links.forEach(l => l.classList.add('active'));

    // 3. Lógica de cambio de Menú (Settings vs Main)
    const navMain = document.getElementById('nav-main');
    const navSettings = document.getElementById('nav-settings');

    if (navMain && navSettings) {
        if (section.startsWith('settings/')) {
            // Estamos en configuración -> Mostrar menú de settings
            navMain.style.display = 'none';
            navSettings.style.display = 'flex'; // o 'block' si prefieres
            navSettings.style.flexDirection = 'column';
            navSettings.style.gap = '4px';
        } else {
            // Estamos en navegación normal
            navSettings.style.display = 'none';
            navMain.style.display = 'flex';
            navMain.style.flexDirection = 'column';
            navMain.style.gap = '4px';
        }
    }
}