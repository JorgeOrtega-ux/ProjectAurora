const allowedSections = [
    'main', 'login', 'register', 'explorer',
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
    if (sectionName === 'settings') sectionName = 'settings/your-profile';
    if (sectionName === 'admin') sectionName = 'admin/dashboard';

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

    } else if (sectionName.startsWith('settings/')) {
        fileToFetch = sectionName; 

    } else if (sectionName.startsWith('admin/')) {
        fileToFetch = sectionName;
    
    } else {
        fileToFetch = 'system/404';
    }
    
    try {
        const resp = await fetch(`${basePath}includes/sections/${fileToFetch}.php${queryParams}`);
        if (!resp.ok) throw new Error('Error de carga');
        container.innerHTML = await resp.text();

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

function updateSidebarState(sectionName) {
    const appMenu = document.getElementById('sidebar-menu-app');
    const settingsMenu = document.getElementById('sidebar-menu-settings');
    const adminMenu = document.getElementById('sidebar-menu-admin'); // Será null si no tengo permisos

    // 1. Ocultamos todo preventivamente
    if (appMenu) appMenu.style.display = 'none';
    if (settingsMenu) settingsMenu.style.display = 'none';
    if (adminMenu) adminMenu.style.display = 'none';

    // 2. Decidimos qué mostrar con red de seguridad
    if (sectionName.startsWith('settings/') && settingsMenu) {
        // Si es sección settings Y existe el menú settings
        settingsMenu.style.display = 'flex';

    } else if (sectionName.startsWith('admin/') && adminMenu) {
        // Si es sección admin Y (importante) EXISTE el menú admin (soy admin real)
        adminMenu.style.display = 'flex';

    } else {
        // FALLBACK:
        // Si no es ninguna de las anteriores, O SI INTENTÉ ENTRAR A ADMIN Y NO EXISTE EL MENÚ
        // mostramos el menú principal (App). Así parece una página normal.
        if (appMenu) appMenu.style.display = 'flex';
    }
}

function updateActiveMenu(sectionName) {
    const allLinks = document.querySelectorAll('.menu-link[data-nav]');
    allLinks.forEach(link => {
        link.classList.remove('active');
    });

    // Intentamos activar el link exacto
    const activeLink = document.querySelector(`.menu-link[data-nav="${sectionName}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
}