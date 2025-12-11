import { ContentService } from './api-services.js';

export function initUrlManager() {
    console.log("SPA Router: Iniciado");

    // 1. Listener de navegación
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('[data-nav]');
        if (link) {
            e.preventDefault();
            const section = link.dataset.nav;

            // --- PASO 1: CERRAR MÓDULOS (UI FEEDBACK) ---
            // Movemos esto al principio para que el menú SIEMPRE se cierre al hacer clic,
            // sin importar si recargamos la página o no.
            
            // Cerrar menú de perfil si está abierto
            const profileMenu = document.querySelector('.module-profile');
            if (profileMenu && profileMenu.classList.contains('active')) {
                profileMenu.classList.remove('active');
                profileMenu.classList.add('disabled');
            }

            // Cerrar menú móvil/sidebar si está abierto
            const surface = document.querySelector('[data-module="moduleSurface"]');
            if(surface && surface.classList.contains('active')) {
                surface.classList.remove('active');
                surface.classList.add('disabled');
            }

            // --- PASO 2: VERIFICAR SI DEBEMOS NAVEGAR ---
            const basePath = window.BASE_PATH || '/ProjectAurora/';
            
            // Obtener ruta actual relativa y limpia
            let currentPath = window.location.pathname;
            if (currentPath.startsWith(basePath)) {
                currentPath = currentPath.substring(basePath.length);
            }
            currentPath = currentPath.replace(/\/$/, '').trim();
            if (currentPath === '') currentPath = 'main';

            // Si ya estamos en la sección solicitada, DETENEMOS AQUÍ.
            // Como ya cerramos los menús arriba, la UX es correcta.
            if (section === currentPath) {
                return; 
            }

            // --- PASO 3: NAVEGAR ---
            navigateTo(section);
        }
    });

    // 2. Listener para botones Atrás/Adelante
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            loadContent(event.state.section);
            updateActiveMenu(event.state.section);
            updateSidebarContext(event.state.section);
        } else {
            location.reload(); 
        }
    });
}

export function navigateTo(section) {
    const basePath = window.BASE_PATH || '/ProjectAurora/';
    const url = (section === 'main') ? basePath : basePath + section;
    
    history.pushState({ section: section }, '', url);
    
    updateSidebarContext(section);
    updateActiveMenu(section);
    loadContent(section);
}

function updateSidebarContext(section) {
    const navMain = document.getElementById('nav-main');
    const navSettings = document.getElementById('nav-settings');
    
    if (!navMain || !navSettings) return;

    const isSettings = section.startsWith('settings/');

    if (isSettings) {
        navMain.classList.remove('active');
        navMain.classList.add('disabled');
        
        navSettings.classList.remove('disabled');
        navSettings.classList.add('active');
    } else {
        navSettings.classList.remove('active');
        navSettings.classList.add('disabled');
        
        navMain.classList.remove('disabled');
        navMain.classList.add('active');
    }
}

function updateActiveMenu(section) {
    document.querySelectorAll('.menu-link').forEach(link => {
        link.classList.remove('active');
    });

    document.querySelectorAll(`.menu-link[data-nav="${section}"]`).forEach(link => {
        link.classList.add('active');
    });
}

async function loadContent(section) {
    const container = document.querySelector('[data-container="main-section"]');
    if (!container) return;

    container.innerHTML = `
        <div class="loader-container">
            <div class="spinner"></div>
        </div>
    `;

    try {
        const [htmlContent] = await Promise.all([
            ContentService.fetchSection(section),
            new Promise(resolve => setTimeout(resolve, 200))
        ]);
        
        container.innerHTML = htmlContent;
        container.scrollTop = 0; 

    } catch (error) {
        console.error("Error cargando sección:", error);
        container.innerHTML = `
            <div style="text-align:center; padding: 40px; color: #d32f2f;">
                <span class="material-symbols-rounded" style="font-size: 48px;">error</span>
                <p>${window.t('error.load_content')}</p>
                <button onclick="location.reload()" style="margin-top:10px; padding:8px 16px; cursor:pointer; border:1px solid #ccc; background:#fff; border-radius:4px;">${window.t('global.retry')}</button>
            </div>
        `;
    }
}