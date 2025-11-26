// public/assets/js/core/url-manager.js

import { closeAllModules } from '../ui/main-controller.js';

// ... (resto de imports y variables igual) ...
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
    'admin/user-manage',
    'admin/user-role', // Asegúrate de que esté aquí
    'admin/user-history',
    'admin/user-notification'
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
let isNavigating = false;

export function initUrlManager() {
    console.log("[UrlManager] Inicializado");
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            showSection(event.state.section, false);
        }
    });

    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('a[href]');
        if (link) {
            const href = link.getAttribute('href');
            if (window.OPEN_NEW_TAB === 1 && href && !href.startsWith('#') && !href.startsWith('javascript:')) {
                if (!link.target) link.target = "_blank";
            }
        }

        if (isNavigating) return;
        const target = e.target.closest('[data-nav]');
        
        if (target) {
            e.preventDefault();
            const section = target.dataset.nav;
            if (section !== getSectionFromUrl()) {
                console.log("[UrlManager] Click detectado, navegando a:", section);
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
    if (typeof closeAllModules === 'function') closeAllModules();

    if (sectionName === 'settings') sectionName = 'settings/your-profile';
    if (sectionName === 'admin') sectionName = 'admin/dashboard';

    console.log("[UrlManager] navigateTo:", sectionName);

    const current = getSectionFromUrl();
    const isCurAuth = authZone.some(z => current.startsWith(z) || z === current);
    const isTarAuth = authZone.some(z => sectionName.startsWith(z) || z === sectionName);

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
    console.log("[UrlManager] Cargando sección:", sectionName);

    const container = document.querySelector('[data-container="main-section"]');
    const loader = document.querySelector('.loader-wrapper');

    if (!container) { window.location.reload(); return; }

    const [baseSection, query] = sectionName.split('?');
    let loaderKey = baseSection;
    let fetchUrl = `${basePath}public/loader.php?section=${loaderKey}&t=${Date.now()}`;

    if (query) fetchUrl += `&${query}`;

    updateSidebarState(baseSection);
    updateActiveMenu(baseSection);

    container.innerHTML = '';
    if (loader) loader.style.display = 'flex';

    try {
        const minDelay = new Promise(resolve => setTimeout(resolve, 200)); 
        const fetchRequest = fetch(fetchUrl);
        const [resp] = await Promise.all([fetchRequest, minDelay]);

        if (!resp.ok) throw new Error(`Error ${resp.status}`);

        const html = await resp.text();
        if (html.includes('<!DOCTYPE html>')) { window.location.reload(); return; }

        container.innerHTML = html;
        container.scrollTop = 0;
        console.log("[UrlManager] HTML insertado en el DOM");

        executeScripts(container);

        if (pushState) {
            const newUrl = (baseSection === 'main') ? basePath : `${basePath}${sectionName}`;
            history.pushState({ section: sectionName }, '', newUrl);
        }

        if (window.initTooltipManager) window.initTooltipManager();
        if (window.initSettingsManager) window.initSettingsManager();
        if (window.translateDocument) window.translateDocument(container);
        
        // LOG CRÍTICO
        console.log("[UrlManager] Llamando a loadDynamicModules...");
        if (window.loadDynamicModules) {
            await window.loadDynamicModules();
            console.log("[UrlManager] loadDynamicModules finalizado.");
        } else {
            console.error("[UrlManager] window.loadDynamicModules NO existe.");
        }

    } catch (error) {
        console.error("[UrlManager] Error:", error);
        container.innerHTML = `<div style="padding:20px;">Error al cargar.</div>`;
    } finally {
        if (loader) loader.style.display = 'none';
        isNavigating = false;
    }
}

function executeScripts(container) {
    const scripts = container.querySelectorAll('script');
    scripts.forEach(oldScript => {
        const newScript = document.createElement('script');
        Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
        newScript.textContent = oldScript.textContent;
        oldScript.parentNode.replaceChild(newScript, oldScript);
    });
}

// ... (resto de funciones updateSidebarState, updateActiveMenu igual) ...
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
    const activeLinks = document.querySelectorAll(`[data-module="moduleSurface"] .menu-link[data-nav="${sectionName}"]`);
    activeLinks.forEach(link => link.classList.add('active'));
}