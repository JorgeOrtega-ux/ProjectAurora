const allowedSections = [
    'main', 'login', 'register', 'explorer',
    'register/additional-data',
    'register/verification-account',
    'forgot-password',
    'status-page',
    'login/verification-additional',
    // [NUEVO]
    'settings',
    'settings/your-profile',
    'settings/login-security',
    'settings/accessibility'
];

const authZone = [
    'login', 
    'register', 
    'register/additional-data', 
    'register/verification-account', 
    'forgot-password', 
    'status-page'
];

const basePath = window.BASE_PATH || '/ProjectAurora/';

export function initUrlManager() {
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            showSection(event.state.section, false);
        } else {
            window.location.reload();
        }
    });

    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('.menu-link[data-nav]');
        if (link) {
            e.preventDefault();
            const section = link.dataset.nav;
            if (section !== getSectionFromUrl()) navigateTo(section);
        }
    });
    
    // Inicializar menú correcto al cargar
    const current = getSectionFromUrl();
    updateSidebarState(current);
    updateActiveMenu(current);
}

window.navigateTo = function(sectionName) {
    // [NUEVO] Redirección frontend de settings base
    if (sectionName === 'settings') sectionName = 'settings/your-profile';

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
    return (path === '' || !allowedSections.includes(path)) ? 'main' : path;
}

async function showSection(sectionName, pushState = true) {
    const container = document.querySelector('[data-container="main-section"]');
    if (!container) { window.location.reload(); return; }
    
    let fileToFetch;
    let queryParams = `?t=${Date.now()}`;
    
    const appSections = ['main', 'explorer'];
    const systemSections = ['status-page', '404', 'error-missing-data'];

    if (sectionName.startsWith('login')) {
        fileToFetch = 'auth/login';
    } else if (sectionName.startsWith('register')) {
        fileToFetch = 'auth/register';
        if (sectionName === 'register/additional-data') queryParams += '&step=2';
        else if (sectionName === 'register/verification-account') queryParams += '&step=3';
        else queryParams += '&step=1';
    
    } else if (sectionName === 'forgot-password') {
        fileToFetch = 'auth/forgot-password';
    
    } else if (appSections.includes(sectionName)) {
        fileToFetch = `app/${sectionName}`;
    
    } else if (systemSections.includes(sectionName)) {
        fileToFetch = `system/${sectionName}`;

    // [NUEVO] Manejo de Settings
    } else if (sectionName.startsWith('settings/')) {
        // Se asume que el archivo php está en includes/sections/settings/nombre.php
        // sectionName ya viene como 'settings/your-profile'
        fileToFetch = sectionName; 
    
    } else {
        fileToFetch = 'system/404';
    }
    
    try {
        const resp = await fetch(`${basePath}includes/sections/${fileToFetch}.php${queryParams}`);
        if (!resp.ok) throw new Error('Error de carga');
        container.innerHTML = await resp.text();

        // [NUEVO] Actualizar visualización del Sidebar
        updateSidebarState(sectionName);
        updateActiveMenu(sectionName);

        if (pushState) {
            const newUrl = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
            history.pushState({ section: sectionName }, '', newUrl);
        }
    } catch (error) {
        console.error(error);
    }
}

// [NUEVO] Función para alternar entre grupos de menús
function updateSidebarState(sectionName) {
    const appMenu = document.getElementById('sidebar-menu-app');
    const settingsMenu = document.getElementById('sidebar-menu-settings');

    if (!appMenu || !settingsMenu) return;

    if (sectionName.startsWith('settings/')) {
        appMenu.style.display = 'none';
        settingsMenu.style.display = 'flex';
    } else {
        appMenu.style.display = 'flex';
        settingsMenu.style.display = 'none';
    }
}

function updateActiveMenu(sectionName) {
    const allLinks = document.querySelectorAll('.menu-link[data-nav]');
    allLinks.forEach(link => {
        link.classList.remove('active');
    });

    const activeLink = document.querySelector(`.menu-link[data-nav="${sectionName}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
}