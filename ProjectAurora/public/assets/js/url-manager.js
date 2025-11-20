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
    
    // ANTES: Si estaba vacío o NO estaba en la lista, devolvía 'main'
    // return (path === '' || !allowedSections.includes(path)) ? 'main' : path;

    // AHORA:
    // 1. Si el path está vacío (root), es 'main'.
    if (path === '') return 'main';
    
    // 2. Si el path NO está en allowedSections, devolvemos '404' (o null).
    // Al devolver '404', la función updateActiveMenu buscará un link data-nav="404",
    // no lo encontrará y NO marcará nada como activo.
    if (!allowedSections.includes(path)) return '404';

    // 3. Si es válido, devolvemos el path.
    return path;
}

// public/assets/js/url-manager.js

async function showSection(sectionName, pushState = true) {
    const container = document.querySelector('[data-container="main-section"]');
    if (!container) { window.location.reload(); return; }
    
    // 1. Separar la sección de la búsqueda (?q=...)
    const [baseSection, query] = sectionName.split('?');
    let cleanSection = baseSection;
    
    // 2. Traducir nombres de URL a nombres clave para el loader.php
    // Esto debe coincidir con las claves del array $fileMap en loader.php
    let loaderKey = cleanSection;
    
    if (cleanSection === 'search') loaderKey = 'search';
    if (cleanSection === 'main') loaderKey = 'main';
    if (cleanSection === 'explorer') loaderKey = 'explorer';
    if (cleanSection === 'settings/your-profile') loaderKey = 'settings-your-profile';
    // ... puedes añadir más mapeos si es necesario

    // 3. Construir la URL hacia el LOADER
    let fetchUrl = `${basePath}loader.php?section=${loaderKey}&t=${Date.now()}`;
    
    if (query) {
        fetchUrl += `&${query}`; // Añadimos ?q=algo
    }

    try {
        const resp = await fetch(fetchUrl);
        if (!resp.ok) throw new Error('Error de carga');
        
        const html = await resp.text();
        
        // Detectar si nos devolvió el login por error (sesión expirada)
        if (html.includes('<!DOCTYPE html>')) {
            window.location.reload(); // Recargar para que el router maneje el login
            return;
        }

        container.innerHTML = html;

        updateSidebarState(cleanSection);
        updateActiveMenu(cleanSection);

        if (pushState) {
            const newUrl = (cleanSection === 'main') ? basePath : `${basePath}${sectionName}`;
            history.pushState({ section: sectionName }, '', newUrl);
        }
    } catch (error) {
        console.error(error);
        container.innerHTML = '<div style="padding:20px; text-align:center">Error cargando la sección.</div>';
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