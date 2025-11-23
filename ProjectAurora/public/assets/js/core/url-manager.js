// public/assets/js/core/url-manager.js

import { closeAllModules } from '../ui/main-controller.js';

const allowedSections = [
    'main', 'login', 'register', 'explorer', 'search',
    'register/additional-data',
    'register/verification-account',
    'forgot-password',
    'status-page',
    'login/verification-additional',
    // Settings
    'settings',
    'settings/your-profile',
    'settings/login-security',
    'settings/accessibility',
    'settings/change-password',
    'settings/2fa-setup',
    'settings/sessions',
    'settings/delete-account',
    // Admin
    'admin',
    'admin/dashboard',
    'admin/users',
    'admin/backups',
    'admin/server',
    'admin/user-status',
    'admin/user-manage'
];

const authZone = [
    'login',
    'register',
    'register/additional-data',
    'register/verification-account',
    'forgot-password',
    'status-page',
    'login/verification-additional'
];

const basePath = window.BASE_PATH || '/ProjectAurora/';

// Variable para evitar clics múltiples mientras carga
let isNavigating = false;

export function initUrlManager() {
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            showSection(event.state.section, false);
        }
    });

    // [MODIFICADO] Listener genérico para cualquier elemento con data-nav
    document.body.addEventListener('click', (e) => {
        if (isNavigating) return;

        // Busca el elemento más cercano con data-nav (puede ser un div, a, button, span, etc.)
        const target = e.target.closest('[data-nav]');
        
        if (target) {
            e.preventDefault(); // Prevenir comportamiento de enlace normal si es un <a>
            const section = target.dataset.nav;
            
            // Solo navegar si la sección es diferente a la actual (opcional, pero recomendado)
            if (section !== getSectionFromUrl()) {
                navigateTo(section);
            }
        }
    });

    const current = getSectionFromUrl();
    updateSidebarState(current);
    updateActiveMenu(current);
}

window.navigateTo = function (sectionName) {
    if (isNavigating) return;

    // [NUEVO] Cerrar cualquier módulo/menú abierto al iniciar navegación
    if (typeof closeAllModules === 'function') {
        closeAllModules();
    }

    if (sectionName === 'settings') sectionName = 'settings/your-profile';
    if (sectionName === 'admin') sectionName = 'admin/dashboard';

    const current = getSectionFromUrl();
    const isCurAuth = authZone.some(z => current.startsWith(z) || z === current);
    const isTarAuth = authZone.some(z => sectionName.startsWith(z) || z === sectionName);

    // Si cambiamos entre zona pública y privada, recargar página completa
    if ((isCurAuth && !isTarAuth) || (!isCurAuth && isTarAuth)) {
        window.location.href = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
    } else {
        showSection(sectionName, true);
    }
};

function getSectionFromUrl() {
    let path = window.location.pathname;
    if (path.startsWith(basePath)) path = path.substring(basePath.length);
    path = path.replace(/\/$/, '').split('?')[0];

    if (path === '') return 'main';
    if (allowedSections.includes(path) || path.startsWith('admin/') || path.startsWith('settings/')) {
        return path;
    }
    return '404';
}

async function showSection(sectionName, pushState = true) {
    isNavigating = true;

    const container = document.querySelector('[data-container="main-section"]');
    const loader = document.querySelector('.loader-wrapper');

    if (!container) { window.location.reload(); return; }

    const [baseSection, query] = sectionName.split('?');
    let loaderKey = baseSection;
    let fetchUrl = `${basePath}public/loader.php?section=${loaderKey}&t=${Date.now()}`;

    if (query) {
        fetchUrl += `&${query}`;
    }

    updateSidebarState(baseSection);
    updateActiveMenu(baseSection);

    container.innerHTML = '';
    if (loader) loader.style.display = 'flex';

    try {
        const minDelay = new Promise(resolve => setTimeout(resolve, 200)); 
        const fetchRequest = fetch(fetchUrl);

        const [resp] = await Promise.all([fetchRequest, minDelay]);

        if (!resp.ok) {
            throw new Error(`Error ${resp.status}: ${resp.statusText}`);
        }

        const html = await resp.text();

        if (html.includes('<!DOCTYPE html>')) {
            window.location.reload();
            return;
        }

        container.innerHTML = html;
        container.scrollTop = 0;

        executeScripts(container);

        if (pushState) {
            const newUrl = (baseSection === 'main') ? basePath : `${basePath}${sectionName}`;
            history.pushState({ section: sectionName }, '', newUrl);
        }

        // Reinicializar módulos necesarios
        if (window.initTooltipManager) window.initTooltipManager();
        if (window.initSettingsManager) window.initSettingsManager();
        if (window.translateDocument) window.translateDocument(container);

    } catch (error) {
        console.error(error);
        container.innerHTML = `
            <div style="padding:40px; text-align:center; color:#666;">
                <span class="material-symbols-rounded" style="font-size:48px; margin-bottom:10px;">wifi_off</span><br>
                No se pudo cargar la sección.<br>
                <small>${error.message}</small>
            </div>`;
    } finally {
        if (loader) loader.style.display = 'none';
        isNavigating = false;
    }
}

function executeScripts(container) {
    const scripts = container.querySelectorAll('script');
    scripts.forEach(oldScript => {
        const newScript = document.createElement('script');
        Array.from(oldScript.attributes).forEach(attr => {
            newScript.setAttribute(attr.name, attr.value);
        });
        newScript.textContent = oldScript.textContent;
        oldScript.parentNode.replaceChild(newScript, oldScript);
    });
}

function updateSidebarState(sectionName) {
    const appMenu = document.getElementById('sidebar-menu-app');
    const settingsMenu = document.getElementById('sidebar-menu-settings');
    const adminMenu = document.getElementById('sidebar-menu-admin');

    if (appMenu) appMenu.style.display = 'none';
    if (settingsMenu) settingsMenu.style.display = 'none';
    if (adminMenu) adminMenu.style.display = 'none';

    if (sectionName.startsWith('settings/') && settingsMenu) {
        settingsMenu.style.display = 'flex';
    } else if (sectionName.startsWith('admin/') && adminMenu) {
        adminMenu.style.display = 'flex';
    } else {
        if (appMenu) appMenu.style.display = 'flex';
    }
}

function updateActiveMenu(sectionName) {
    const allLinks = document.querySelectorAll('.menu-link[data-nav]');
    allLinks.forEach(link => link.classList.remove('active'));
    
    const activeLink = document.querySelector(`.menu-link[data-nav="${sectionName}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
}