/**
 * public/assets/js/core/utils/url-manager.js
 * Orquestador de Navegación SPA.
 * Gestiona el historial, la carga de vistas y el ciclo de vida de las peticiones (AbortController).
 */

import { I18nManager } from './i18n-manager.js';

// Variable privada para el controlador de aborto de la página actual
let currentAbortController = null;

export function initUrlManager() {
    console.log("SPA Router: Iniciado (Smart Traffic Control)");

    // Exponer el signal globalmente para fácil acceso en controladores sin inyección de dependencias compleja
    // Los controladores pueden usar: window.PAGE_SIGNAL
    Object.defineProperty(window, 'PAGE_SIGNAL', {
        get: () => currentAbortController ? currentAbortController.signal : null
    });

    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            loadContent(event.state.section);
            updateActiveMenu(event.state.section);
        } else {
            // Recuperación de estado en reload/navegación manual
            const path = window.location.pathname.replace(window.BASE_PATH, '');
            const cleanPath = path.replace(/^\/+|\/+$/g, '') || 'main';
            loadContent(cleanPath); 
            updateActiveMenu(cleanPath);
        }
    });

    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('[data-nav]');
        if (link) {
            e.preventDefault();
            const section = link.dataset.nav;
            
            // Navegar siempre, incluso si es el mismo link, para refrescar
            if (!link.classList.contains('disabled')) {
                navigateTo(section);
            }
            
            // Cierre automático de menús en móvil
            const activeModules = document.querySelectorAll('.module-content.active');
            activeModules.forEach(mod => {
                if (mod.dataset.module !== 'moduleSurface' || window.innerWidth < 725) {
                     mod.classList.remove('active');
                     mod.classList.add('disabled');
                }
            });
        }
    });
    
    // Carga inicial
    const path = window.location.pathname.replace(window.BASE_PATH, '');
    const cleanPath = path.replace(/^\/+|\/+$/g, ''); 
    const currentSection = cleanPath || 'main';
    updateActiveMenu(currentSection);
}

/**
 * Navega a una sección gestionando el historial y las peticiones pendientes.
 */
export function navigateTo(section, params = null) {
    const basePath = window.BASE_PATH || '/ProjectAurora/';
    let queryString = '';

    if (params) {
        if (typeof params === 'object') {
            const urlParams = new URLSearchParams(params);
            queryString = '?' + urlParams.toString();
        } else if (typeof params === 'string') {
            queryString = params.startsWith('?') ? params : '?' + params;
        }
    }

    const url = (section === 'main') ? basePath : basePath + section + queryString;
    
    history.pushState({ section: section }, '', url);
    
    loadContent(section);
    updateActiveMenu(section);
}

/**
 * Carga el contenido HTML vía AJAX y gestiona el ciclo de vida de la petición.
 */
async function loadContent(section) {
    const container = document.querySelector('.general-content-scrolleable');
    if (!container) return;

    // 1. GESTIÓN DE CANCELACIÓN (AbortController)
    // Si hay una navegación en curso o peticiones pendientes de la vista anterior, las cancelamos.
    if (currentAbortController) {
        currentAbortController.abort();
        console.log("🛑 Navegación detectada: Peticiones de la vista anterior abortadas.");
    }
    // Creamos un nuevo controlador para la nueva vista
    currentAbortController = new AbortController();

    // UI de Carga
    container.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;"><div class="spinner"></div></div>';
    
    // Pequeño delay artificial para suavidad visual (opcional, evita parpadeos en cargas instantáneas)
    await new Promise(resolve => setTimeout(resolve, 150));

    try {
        const currentParams = window.location.search; 
        const fetchUrl = `${window.BASE_PATH}public/loader.php?section=${section}${currentParams.replace('?', '&')}`;

        // Pasamos el signal al fetch del loader (si el usuario cambia rápido, el fetch del HTML también se cancela)
        const response = await fetch(fetchUrl, {
            signal: currentAbortController.signal
        });
        
        if (!response.ok && response.status !== 404 && response.status !== 409) {
             throw new Error(`HTTP error! status: ${response.status}`);
        }

        const html = await response.text();
        
        container.innerHTML = html;
        container.scrollTop = 0; 
        
        // Evento global para que los controladores sepan que la vista cargó
        // y puedan iniciar su lógica (pasando implícitamente el window.PAGE_SIGNAL)
        const event = new CustomEvent('spa:view_loaded', { detail: { section } });
        document.dispatchEvent(event);
        
    } catch (error) {
        if (error.name === 'AbortError') {
            console.log("Ignorando carga de vista interrumpida.");
            return;
        }
        console.error("Error cargando sección:", error);
        container.innerHTML = `<div style="padding: 20px; text-align: center;">
            <h3>Error</h3>
            <p>${I18nManager.t('js.core.loading_error') || 'No se pudo cargar el contenido.'}</p>
            <button class="component-button" onclick="location.reload()">Reintentar</button>
        </div>`;
    }
}

function updateActiveMenu(section) {
    document.querySelectorAll('.menu-link[data-nav]').forEach(l => l.classList.remove('active'));
    
    // Match exacto o prefijo para subsecciones
    let links = document.querySelectorAll(`.menu-link[data-nav="${section}"]`);
    if (links.length === 0 && section.includes('/')) {
        // Fallback para sub-rutas (ej: admin/users/detail -> activa admin/users)
        const parentSection = section.split('/').slice(0, 2).join('/');
        links = document.querySelectorAll(`.menu-link[data-nav^="${parentSection}"]`);
    }
    
    links.forEach(l => l.classList.add('active'));

    // Lógica de visualización de menús laterales (Sidebar)
    const menus = {
        main: document.getElementById('surface-main'),
        settings: document.getElementById('surface-settings'),
        help: document.getElementById('surface-help'),
        admin: document.getElementById('surface-admin')
    };
    
    Object.values(menus).forEach(el => { if(el) el.style.display = 'none'; });

    if (section.startsWith('settings/')) {
        if(menus.settings) menus.settings.style.display = 'flex';
    } 
    else if (section.startsWith('site-policy')) { 
        if(menus.help) menus.help.style.display = 'flex';
    }
    else if (section.startsWith('admin/')) { 
        if(menus.admin) menus.admin.style.display = 'flex';
    }
    else {
        if(menus.main) menus.main.style.display = 'flex';
    }
}