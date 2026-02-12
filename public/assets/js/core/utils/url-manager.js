import { I18nManager } from './i18n-manager.js';

let currentAbortController = null;

function initUrlManager() {
    // Exponer señal para cancelar peticiones al cambiar de página
    Object.defineProperty(window, 'PAGE_SIGNAL', {
        get: () => currentAbortController ? currentAbortController.signal : null
    });

    // Manejo del botón "Atrás" del navegador
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            loadContent(event.state.section);
            updateActiveMenu(event.state.section);
        } else {
            const path = window.location.pathname.replace(window.BASE_PATH, '');
            const cleanPath = path.replace(/^\/+|\/+$/g, '') || 'main';
            loadContent(cleanPath); 
            updateActiveMenu(cleanPath);
        }
    });

    // Delegación de eventos global para clicks
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('[data-nav]');
        if (link) {
            e.preventDefault();
            
            const visibleUrl = link.dataset.nav; // URL para la barra de direcciones
            const fetchSource = link.dataset.fetch || visibleUrl; // Archivo real a pedir
            const targetSelector = link.dataset.target || '.general-content-scrolleable'; // Contenedor destino
            
            // Normalizamos la URL actual y destino para evitar recargas innecesarias
            const currentPathRaw = window.location.pathname.replace(window.BASE_PATH, '');
            const currentSection = currentPathRaw.replace(/^\/+|\/+$/g, '') || 'main';
            const targetSection = visibleUrl.replace(/^\/+|\/+$/g, '');

            if (currentSection === targetSection) {
                return;
            }

            if (!link.classList.contains('disabled')) {
                updateActiveMenu(visibleUrl, link);
                navigateTo(visibleUrl, null, targetSelector, fetchSource);
            }
            
            const activeModules = document.querySelectorAll('.module-content.active');
            activeModules.forEach(mod => {
                if (mod.dataset.module !== 'moduleSurface' || window.innerWidth < 725) {
                     mod.classList.remove('active');
                     mod.classList.add('disabled');
                }
            });
        }
    });
    
    const path = window.location.pathname.replace(window.BASE_PATH, '');
    const cleanPath = path.replace(/^\/+|\/+$/g, ''); 
    const currentSection = cleanPath || 'main';
    updateActiveMenu(currentSection);
}

/**
 * Navega a una sección URL
 */
function navigateTo(visiblePath, params = null, targetSelector = '.general-content-scrolleable', fetchPath = null) {
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

    const browserUrl = (visiblePath === 'main') ? basePath : basePath + visiblePath + queryString;
    
    history.pushState({ section: visiblePath, fetchSource: fetchPath }, '', browserUrl);
    
    loadContent(fetchPath || visiblePath, targetSelector);
}

async function loadContent(section, targetSelector = '.general-content-scrolleable') {
    const container = document.querySelector(targetSelector);
    if (!container) {
        console.error(`UrlManager: Target container '${targetSelector}' not found.`);
        return;
    }

    if (currentAbortController) {
        currentAbortController.abort();
    }
    currentAbortController = new AbortController();

    // Feedback visual de carga (Spinner)
    const isMainArea = targetSelector === '.general-content-scrolleable';

    if (isMainArea) {
        // Inyectar spinner centrado
        container.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;width:100%;"><div class="spinner"></div></div>';
    } else {
        // Para otras áreas parciales pequeñas, usar opacidad sutil
        container.style.transition = 'opacity 0.2s';
        container.style.opacity = '0.5';
    }
    
    // Delay artificial de 200ms para suavizar la transición y evitar parpadeos rápidos
    await new Promise(resolve => setTimeout(resolve, 200));

    try {
        const currentParams = window.location.search; 
        const fetchUrl = `${window.BASE_PATH}public/loader.php?section=${section}${currentParams.replace('?', '&')}`;

        const response = await fetch(fetchUrl, {
            signal: currentAbortController.signal
        });
        
        if (!response.ok && response.status !== 404 && response.status !== 409) {
             throw new Error(`HTTP error! status: ${response.status}`);
        }

        const html = await response.text();
        
        container.innerHTML = html;
        container.scrollTop = 0; 
        
        // Restaurar estilos visuales si usamos opacidad
        if (!isMainArea) {
            container.style.opacity = '1';
        }
        
        const event = new CustomEvent('spa:view_loaded', { detail: { section, target: targetSelector } });
        document.dispatchEvent(event);
        
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }
        console.error(error);
        
        if (!isMainArea) {
            container.style.opacity = '1';
        }

        container.innerHTML = `<div style="padding: 20px; text-align: center;">
            <h3>Error</h3>
            <p>${I18nManager.t('js.core.loading_error') || 'No se pudo cargar el contenido.'}</p>
            <button class="component-button" onclick="location.reload()">Reintentar</button>
        </div>`;
    }
}

function updateActiveMenu(section, specificLinkElement = null) {
    // 1. LIMPIEZA GLOBAL DE CLASES ACTIVE
    // Limpiamos la Sidebar (Surface)
    const mainSidebar = document.querySelector('.module-surface');
    if (mainSidebar) {
        mainSidebar.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
    }

    // Limpiamos el Menú de Perfil (Module Profile)
    const profileModule = document.querySelector('.module-profile');
    if (profileModule) {
        profileModule.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
    }

    // 2. MARCAR EL NUEVO ENLACE COMO ACTIVO
    // Si el usuario hizo click directo, lo marcamos primero
    if (specificLinkElement) {
        const parentMenu = specificLinkElement.closest('.menu-list');
        if (parentMenu) {
            parentMenu.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
            specificLinkElement.classList.add('active');
        }
    }

    // 3. SINCRONIZACIÓN AUTOMÁTICA POR SECCIÓN
    let activeLink = null;

    // Buscar el enlace correspondiente en TODA la interfaz (Sidebar o Profile)
    activeLink = document.querySelector(`.menu-link[data-nav="${section}"]`);

    // Caso especial para "Inicio" si la sección viene vacía o como "main"
    if (!activeLink && (section === 'main' || section === '')) {
        activeLink = document.querySelector('.menu-link[data-nav="main"]');
    }

    if (activeLink) {
        activeLink.classList.add('active');
    }

    // 4. CAMBIO DE VISIBILIDAD DE MENÚS EN SURFACE
    if (mainSidebar) {
        if (section.startsWith('settings/')) {
            switchVisibleMenu('surface-settings');
        } 
        else if (section.startsWith('site-policy')) { 
            switchVisibleMenu('surface-help');
        }
        else if (section.startsWith('admin/')) { 
            switchVisibleMenu('surface-admin');
        }
        else {
            switchVisibleMenu('surface-main');
        }
    }
}

function switchVisibleMenu(menuId) {
    const menus = ['surface-main', 'surface-settings', 'surface-help', 'surface-admin'];
    menus.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = (id === menuId) ? 'flex' : 'none';
    });
}

export { initUrlManager, navigateTo };