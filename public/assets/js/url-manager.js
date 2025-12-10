import { ContentService } from './api-services.js';

export function initUrlManager() {
    console.log("SPA Router: Iniciado");

    // 1. Listener de navegación
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('[data-nav]');
        if (link) {
            e.preventDefault();
            const section = link.dataset.nav;
            
            // Cerrar menú de perfil si está abierto al navegar
            const profileMenu = document.querySelector('.module-profile');
            if (profileMenu && profileMenu.classList.contains('active')) {
                profileMenu.classList.remove('active');
                profileMenu.classList.add('disabled');
            }

            navigateTo(section);
            
            // Cerrar menú móvil si corresponde
            const surface = document.querySelector('[data-module="moduleSurface"]');
            if(surface && surface.classList.contains('active')) {
                surface.classList.remove('active');
                surface.classList.add('disabled');
            }
        }
    });

    // 2. Listener para botones Atrás/Adelante
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            loadContent(event.state.section);
            updateActiveMenu(event.state.section);
            updateSidebarContext(event.state.section); // Actualizar sidebar al volver atrás
        } else {
            location.reload(); 
        }
    });
}

export function navigateTo(section) {
    const basePath = window.BASE_PATH || '/ProjectAurora/';
    const url = (section === 'main') ? basePath : basePath + section;
    
    // Cambiar URL y Menú visualmente
    history.pushState({ section: section }, '', url);
    
    // 1. Actualizar qué menú se muestra (Principal vs Configuración)
    updateSidebarContext(section);

    // 2. Actualizar clase 'active' en el menú visible
    updateActiveMenu(section);
    
    // 3. Iniciar carga
    loadContent(section);
}

/**
 * Alterna entre el menú principal y el de configuración
 * basado en si la sección comienza con 'settings/'
 */
function updateSidebarContext(section) {
    const navMain = document.getElementById('nav-main');
    const navSettings = document.getElementById('nav-settings');
    
    if (!navMain || !navSettings) return;

    const isSettings = section.startsWith('settings/');

    if (isSettings) {
        navMain.classList.remove('active'); // Por si acaso usa flex
        navMain.classList.add('disabled');
        
        navSettings.classList.remove('disabled');
        navSettings.classList.add('active'); // Opcional si usas display block por defecto
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

    // Busca el link que corresponda a la sección exacta
    document.querySelectorAll(`.menu-link[data-nav="${section}"]`).forEach(link => {
        link.classList.add('active');
    });
}

async function loadContent(section) {
    const container = document.querySelector('[data-container="main-section"]');
    if (!container) return;

    // --- PASO 1: LIMPIEZA INMEDIATA ---
    container.innerHTML = `
        <div class="loader-container">
            <div class="spinner"></div>
        </div>
    `;

    try {
        // --- PASO 2: PARALELISMO (Service + Delay) ---
        const [htmlContent] = await Promise.all([
            ContentService.fetchSection(section),
            new Promise(resolve => setTimeout(resolve, 200)) // Espera mínima visual
        ]);
        
        // --- PASO 3: INYECCIÓN ---
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