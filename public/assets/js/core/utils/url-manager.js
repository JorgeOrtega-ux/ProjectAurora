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
            // Nota: Al volver atrás, siempre recargamos la vista principal (full reload)
            // para asegurar consistencia, a menos que guardemos metadatos complejos en el state.
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
            
            // 1. Obtener parámetros del enlace
            const visibleUrl = link.dataset.nav; // URL que se verá en el navegador
            const fetchSource = link.dataset.fetch || visibleUrl; // Archivo real a pedir (opcional)
            const targetSelector = link.dataset.target || '.general-content-scrolleable'; // Contenedor destino
            
            // 2. Evitar recarga si es la misma sección (Opcional, a veces queremos refrescar)
            // Aquí lo permitimos si es una navegación parcial para dar feedback visual
            
            if (!link.classList.contains('disabled')) {
                // Actualizar menú visualmente antes de navegar
                updateActiveMenu(visibleUrl, link);
                
                // Ejecutar navegación
                navigateTo(visibleUrl, null, targetSelector, fetchSource);
            }
            
            // Cerrar menú móvil si corresponde
            const activeModules = document.querySelectorAll('.module-content.active');
            activeModules.forEach(mod => {
                if (mod.dataset.module !== 'moduleSurface' || window.innerWidth < 725) {
                     mod.classList.remove('active');
                     mod.classList.add('disabled');
                }
            });
        }
    });
    
    // Inicialización del estado activo al cargar la página
    const path = window.location.pathname.replace(window.BASE_PATH, '');
    const cleanPath = path.replace(/^\/+|\/+$/g, ''); 
    const currentSection = cleanPath || 'main';
    updateActiveMenu(currentSection);
}

/**
 * Navega a una sección URL
 * @param {string} visiblePath - Lo que se muestra en la barra de direcciones
 * @param {object} params - Query params opcionales
 * @param {string} targetSelector - Selector CSS del contenedor donde inyectar el HTML
 * @param {string} fetchPath - (Opcional) Ruta interna real para el loader.php si difiere de visiblePath
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

    // Construir la URL completa para el navegador
    const browserUrl = (visiblePath === 'main') ? basePath : basePath + visiblePath + queryString;
    
    // Guardamos en el historial
    // Nota: Guardamos 'fetchPath' en el estado por si quisiéramos restaurar vistas parciales en el futuro (avanzado)
    history.pushState({ section: visiblePath, fetchSource: fetchPath }, '', browserUrl);
    
    // Cargar el contenido
    loadContent(fetchPath || visiblePath, targetSelector);
}

async function loadContent(section, targetSelector = '.general-content-scrolleable') {
    const container = document.querySelector(targetSelector);
    if (!container) {
        console.error(`UrlManager: Target container '${targetSelector}' not found.`);
        return;
    }

    // Cancelar petición anterior si existe
    if (currentAbortController) {
        currentAbortController.abort();
    }
    currentAbortController = new AbortController();

    // Feedback visual de carga (Spinner)
    // Guardamos la opacidad original para restaurarla (útil para cargas parciales sutiles)
    const originalOpacity = container.style.opacity;
    
    // Si es el contenedor principal, usamos el spinner centrado grande.
    // Si es un contenedor parcial (ej. Studio), usamos una transición de opacidad elegante.
    if (targetSelector === '.general-content-scrolleable') {
        container.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;"><div class="spinner"></div></div>';
    } else {
        container.style.transition = 'opacity 0.2s';
        container.style.opacity = '0.5';
    }
    
    // Pequeño delay artificial para evitar parpadeos en cargas muy rápidas (opcional)
    // await new Promise(resolve => setTimeout(resolve, 50));

    try {
        const currentParams = window.location.search; 
        // Construimos la URL para el loader
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
        
        // Restaurar estilos visuales
        if (targetSelector !== '.general-content-scrolleable') {
            container.style.opacity = '1';
        }
        
        // Disparar evento global de que una vista se cargó
        const event = new CustomEvent('spa:view_loaded', { detail: { section, target: targetSelector } });
        document.dispatchEvent(event);
        
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }
        console.error(error);
        
        // Restaurar estilos en error
        if (targetSelector !== '.general-content-scrolleable') {
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
    // 1. Si tenemos el elemento específico clicado, usémoslo para activar clases locales (ej. sidebar del Studio)
    if (specificLinkElement) {
        // Buscar el contenedor padre más cercano que sea un menú (sidebar o módulo)
        const parentMenu = specificLinkElement.closest('.menu-list') || specificLinkElement.closest('.studio-sidebar');
        if (parentMenu) {
            parentMenu.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
            specificLinkElement.classList.add('active');
        }
    }

    // 2. Lógica global del Sidebar Principal (Module Surface)
    // Esto asegura que si navegamos a 'settings/profile', el sidebar principal marque 'settings'
    const mainSidebar = document.querySelector('.module-surface');
    if (mainSidebar) {
        mainSidebar.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
        
        let activeLink = null;
        
        // Mapeo de secciones padre
        if (section.startsWith('settings/')) {
            switchVisibleMenu('surface-settings');
            // Intentar encontrar link exacto
            activeLink = mainSidebar.querySelector(`.menu-link[data-nav="${section}"]`);
        } 
        else if (section.startsWith('site-policy')) { 
            switchVisibleMenu('surface-help');
            activeLink = mainSidebar.querySelector(`.menu-link[data-nav="${section}"]`);
        }
        else if (section.startsWith('admin/')) { 
            switchVisibleMenu('surface-admin');
            activeLink = mainSidebar.querySelector(`.menu-link[data-nav="${section}"]`);
        }
        else if (section.startsWith('s/channel/') || section.startsWith('studio/')) {
            // El Studio NO tiene menú en el sidebar principal (es pantalla completa o layout propio)
            // Así que ocultamos todos los menús del sidebar principal
            switchVisibleMenu(null); 
        }
        else {
            switchVisibleMenu('surface-main');
            activeLink = mainSidebar.querySelector(`.menu-link[data-nav="${section}"]`) || 
                         mainSidebar.querySelector(`.menu-link[data-nav="main"]`);
        }

        if (activeLink) activeLink.classList.add('active');
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