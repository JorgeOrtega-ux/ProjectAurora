// public/assets/js/url-manager.js

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
    // Admin
    'admin',
    'admin/dashboard',
    'admin/users',
    'admin/backups',
    'admin/server'
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

export function initUrlManager() {
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            showSection(event.state.section, false);
        } else {
            // Si es un reload manual o inicial, no hacemos fetch, dejamos que router.php cargue
            // Opcional: Podrías forzar reload si la navegación se desincroniza
        }
    });

    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('.menu-link[data-nav], a[onclick*="navigateTo"]');
        // Nota: el selector a[onclick...] es para capturar enlaces legacy si los hubiera, 
        // pero tu sidebar usa .menu-link[data-nav]

        if (link && link.dataset.nav) {
            e.preventDefault();
            const section = link.dataset.nav;
            if (section !== getSectionFromUrl()) navigateTo(section);
        }
    });

    // Inicializar estado visual del sidebar al cargar
    const current = getSectionFromUrl();
    updateSidebarState(current);
    updateActiveMenu(current);
}

window.navigateTo = function (sectionName) {
    // Normalizaciones rápidas
    if (sectionName === 'settings') sectionName = 'settings/your-profile';
    if (sectionName === 'admin') sectionName = 'admin/dashboard';

    const current = getSectionFromUrl();
    const isCurAuth = authZone.some(z => current.startsWith(z) || z === current);
    const isTarAuth = authZone.some(z => sectionName.startsWith(z) || z === sectionName);

    // Si cambiamos entre Zona Auth (Login) y Zona App (Main), recargamos la página completa
    // para que el router.php maneje sesiones y redirecciones de seguridad.
    if ((isCurAuth && !isTarAuth) || (!isCurAuth && isTarAuth)) {
        window.location.href = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
    } else {
        // Navegación interna fluida (AJAX)
        showSection(sectionName, true);
    }
};

function getSectionFromUrl() {
    let path = window.location.pathname;
    if (path.startsWith(basePath)) path = path.substring(basePath.length);
    path = path.replace(/\/$/, '').split('?')[0];

    if (path === '') return 'main';
    // Si path está en allowedSections o empieza con admin/ settings/
    if (allowedSections.includes(path) || path.startsWith('admin/') || path.startsWith('settings/')) {
        return path;
    }
    return '404';
}

async function showSection(sectionName, pushState = true) {
    const container = document.querySelector('[data-container="main-section"]');
    if (!container) { window.location.reload(); return; }

    // 1. Separar la sección de la búsqueda (?q=...)
    const [baseSection, query] = sectionName.split('?');

    // 2. Definir la clave para loader.php
    // [CORRECCIÓN]: Usamos baseSection directamente (ej: 'settings/login-security')
    // Ya no hacemos reemplazos manuales raros, loader.php ahora entiende las rutas.
    let loaderKey = baseSection;

    // 3. Construir URL
    let fetchUrl = `${basePath}public/loader.php?section=${loaderKey}&t=${Date.now()}`;

    if (query) {
        fetchUrl += `&${query}`; // Añadimos ?q=algo si existe
    }

    // Feedback visual de carga inmediato
    // container.style.opacity = '0.5'; 

    try {
        const resp = await fetch(fetchUrl);

        if (!resp.ok) {
            throw new Error(`Error ${resp.status}: ${resp.statusText}`);
        }

        const html = await resp.text();

        // Detectar si nos devolvió una página completa de login por error (sesión expirada)
        if (html.includes('<!DOCTYPE html>')) {
            window.location.reload();
            return;
        }

        container.innerHTML = html;
        // container.style.opacity = '1';

        updateSidebarState(baseSection);
        updateActiveMenu(baseSection);

        if (pushState) {
            const newUrl = (baseSection === 'main') ? basePath : `${basePath}${sectionName}`;
            history.pushState({ section: sectionName }, '', newUrl);
        }

        // Reinicializar tooltips o scripts específicos si es necesario
        if (window.initTooltipManager) window.initTooltipManager();
        if (window.initSettingsManager) window.initSettingsManager();
    } catch (error) {
        console.error(error);
        container.innerHTML = `
            <div style="padding:40px; text-align:center; color:#666;">
                <span class="material-symbols-rounded" style="font-size:48px; margin-bottom:10px;">wifi_off</span><br>
                No se pudo cargar la sección.<br>
                <small>${error.message}</small>
            </div>`;
        // container.style.opacity = '1';
    }
}

function updateSidebarState(sectionName) {
    const appMenu = document.getElementById('sidebar-menu-app');
    const settingsMenu = document.getElementById('sidebar-menu-settings');
    const adminMenu = document.getElementById('sidebar-menu-admin');

    // Ocultar todos
    if (appMenu) appMenu.style.display = 'none';
    if (settingsMenu) settingsMenu.style.display = 'none';
    if (adminMenu) adminMenu.style.display = 'none';

    // Mostrar el pertinente
    if (sectionName.startsWith('settings/') && settingsMenu) {
        settingsMenu.style.display = 'flex';
    } else if (sectionName.startsWith('admin/') && adminMenu) {
        adminMenu.style.display = 'flex';
    } else {
        // Por defecto App
        if (appMenu) appMenu.style.display = 'flex';
    }
}

function updateActiveMenu(sectionName) {
    const allLinks = document.querySelectorAll('.menu-link[data-nav]');
    allLinks.forEach(link => link.classList.remove('active'));

    // Activar el exacto
    const activeLink = document.querySelector(`.menu-link[data-nav="${sectionName}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
}