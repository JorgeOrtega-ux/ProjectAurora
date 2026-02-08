/**
 * public/assets/js/core/url-manager.js
 * Gestiona la navegación SPA y la visibilidad de los menús laterales.
 */

import { I18nManager } from './i18n-manager.js';

export function initUrlManager() {
    console.log("SPA Router: Iniciado");

    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            loadContent(event.state.section);
            updateActiveMenu(event.state.section);
        } else {
            // Si no hay estado (reload manual), intentamos recuperar la sección de la URL
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
            
            if (!link.classList.contains('active')) {
                navigateTo(section);
            }
            
            // Cierre automático de módulos (Móvil o Desktop)
            const activeModules = document.querySelectorAll('.module-content.active');
            activeModules.forEach(mod => {
                if (mod.dataset.module !== 'moduleSurface' || window.innerWidth < 725) {
                     mod.classList.remove('active');
                     mod.classList.add('disabled');
                }
            });
        }
    });
    
    // Inicialización al cargar la página
    const path = window.location.pathname.replace(window.BASE_PATH, '');
    const cleanPath = path.replace(/^\/+|\/+$/g, ''); 
    const currentSection = cleanPath || 'main';
    updateActiveMenu(currentSection);
}

/**
 * Navega a una sección usando PushState (Sin recargar)
 * @param {string} section - La ruta amigable (ej: 'admin/user-details')
 * @param {string|object} params - Query params (ej: '?id=1' o {id: 1})
 */
export function navigateTo(section, params = null) {
    const basePath = window.BASE_PATH || '/ProjectAurora/';
    let queryString = '';

    // Convertir objeto a string si es necesario
    if (params) {
        if (typeof params === 'object') {
            const urlParams = new URLSearchParams(params);
            queryString = '?' + urlParams.toString();
        } else if (typeof params === 'string') {
            queryString = params.startsWith('?') ? params : '?' + params;
        }
    }

    const url = (section === 'main') ? basePath : basePath + section + queryString;
    
    // 1. Cambiamos la URL en el navegador sin recargar
    history.pushState({ section: section }, '', url);
    
    // 2. Cargamos el contenido vía AJAX
    loadContent(section);
    updateActiveMenu(section);
}

async function loadContent(section) {
    const container = document.querySelector('.general-content-scrolleable');
    if (!container) return;

    // Spinner de carga
    container.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;"><div class="spinner"></div></div>';
    
    await new Promise(resolve => setTimeout(resolve, 200));

    try {
        // Leemos los parámetros actuales de la URL para pasarlos al loader PHP
        const currentParams = window.location.search; 
        const fetchUrl = `${window.BASE_PATH}public/loader.php?section=${section}${currentParams.replace('?', '&')}`;

        const response = await fetch(fetchUrl);
        
        // Permitimos el estado 409 (Conflict) para que se renderice la UI de error del Honeypot
        if (!response.ok && response.status !== 404 && response.status !== 409) {
             throw new Error(`HTTP error! status: ${response.status}`);
        }

        const html = await response.text();
        
        container.innerHTML = html;
        container.scrollTop = 0; 
        
        const event = new CustomEvent('spa:view_loaded', { detail: { section } });
        document.dispatchEvent(event);
        
    } catch (error) {
        console.error("Error cargando sección:", error);
        container.innerHTML = `<div style="padding: 20px;"><p>${I18nManager.t('js.core.loading_error')}</p></div>`;
    }
}

/**
 * Actualiza el menú lateral y el menú de perfil.
 */
function updateActiveMenu(section) {
    document.querySelectorAll('.menu-link[data-nav]').forEach(l => l.classList.remove('active'));
    
    let links = document.querySelectorAll(`.menu-link[data-nav="${section}"]`);
    links.forEach(l => l.classList.add('active'));

    const menus = {
        main: document.getElementById('nav-main'),
        settings: document.getElementById('nav-settings'),
        help: document.getElementById('nav-help'),
        admin: document.getElementById('nav-admin'),
        adminBottom: document.getElementById('nav-admin-bottom')
    };

    // Ocultar todos
    Object.values(menus).forEach(el => {
        if (el) el.style.display = 'none';
    });

    // Mostrar el correspondiente
    if (section.startsWith('settings/')) {
        showMenu(menus.settings);
    } 
    else if (section.startsWith('site-policy')) { 
        showMenu(menus.help);
    }
    else if (section.startsWith('admin/')) { 
        if (menus.admin) {
            showMenu(menus.admin);
            showMenu(menus.adminBottom);
        } else {
            showMenu(menus.main); // Fallback
        }
    }
    else {
        showMenu(menus.main);
    }
}

function showMenu(element) {
    if (element) {
        element.style.display = 'flex';
        element.style.flexDirection = 'column';
        element.style.gap = '4px';
    }
}