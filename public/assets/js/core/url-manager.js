import { ContentService } from './api-services.js';
import { AdminController } from '../modules/admin/admin-controller.js';

export function initUrlManager() {
    console.log("SPA Router: Iniciado");

    // 1. Listener de navegación (Clics en enlaces)
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('[data-nav]');
        if (link) {
            e.preventDefault();
            const section = link.dataset.nav;

            // --- PASO 1: CERRAR MÓDULOS (UI FEEDBACK) ---
            
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

            if (section === currentPath) {
                return; 
            }

            // --- PASO 3: NAVEGAR ---
            navigateTo(section);
        }
    });

    // 2. Listener para botones Atrás/Adelante (Historial)
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            loadContent(event.state.section);
            updateActiveMenu(event.state.section);
            updateSidebarContext(event.state.section);
        } else {
            location.reload(); 
        }
    });

    // 3. (NUEVO) HIDRATACIÓN INICIAL: Detectar carga directa (F5/Reload)
    // Esto soluciona que los scripts de admin no carguen al refrescar la página
    const basePath = window.BASE_PATH || '/ProjectAurora/';
    let initialPath = window.location.pathname;

    if (initialPath.startsWith(basePath)) {
        initialPath = initialPath.substring(basePath.length);
    }
    initialPath = initialPath.replace(/\/$/, '').trim();
    
    // Si la ruta inicial es de admin, invocamos manualmente el controlador
    if (initialPath.startsWith('admin/')) {
        console.log("SPA: Detectada carga directa en sección Admin. Inicializando controlador...");
        AdminController.loadSection(initialPath);
    }
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
    // MODIFICADO: Uso de data-context en lugar de IDs
    const navMain = document.querySelector('[data-context="main"]');
    const navSettings = document.querySelector('[data-context="settings"]');
    const navAdmin = document.querySelector('[data-context="admin"]');
    const navAdminBottom = document.querySelector('[data-context="admin-bottom"]');
    const navHelp = document.querySelector('[data-context="help"]');

    // Funciones helper para limpiar código
    const activate = (el) => {
        if (el) {
            el.classList.remove('disabled');
            el.classList.add('active');
        }
    };
    
    const deactivate = (el) => {
        if (el) {
            el.classList.remove('active');
            el.classList.add('disabled');
        }
    };

    // 1. Resetear todos a estado desactivado
    deactivate(navMain);
    deactivate(navSettings);
    deactivate(navAdmin);
    deactivate(navAdminBottom);
    deactivate(navHelp);

    // 2. Activar el correcto según la sección
    if (section.startsWith('settings/')) {
        activate(navSettings);
    } else if (section.startsWith('admin/')) {
        activate(navAdmin);
        activate(navAdminBottom);
    } else if (section.startsWith('help/')) { 
        activate(navHelp);
    } else {
        // Por defecto (main, explorer, etc) mostramos el menú principal
        activate(navMain);
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
        
        // --- CONTROLADOR DE ADMIN ---
        if (section.startsWith('admin/')) {
            // Si entramos en admin, delegamos al controlador
            AdminController.loadSection(section);
        } else {
            // Si salimos de admin, forzamos limpieza pasando una ruta dummy
            // Esto asegura que se destruyan los listeners y variables de Admin
            AdminController.loadSection('admin/unload');
        }

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