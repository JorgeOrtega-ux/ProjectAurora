const allowedSections = [
    'main', 'login', 'register', 'explorer',
    'register/additional-data',
    'register/verification-account',
    'forgot-password',
    'status-page',
    'login/verification-additional' // [CORRECCIÓN] Añadido el 2FA de login que faltaba
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
    
    updateActiveMenu(getSectionFromUrl());
}

window.navigateTo = function(sectionName) {
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
    // CAMBIO: Selección por data-container en vez de ID
    const container = document.querySelector('[data-container="main-section"]');
    if (!container) { window.location.reload(); return; }

    
    // --- [INICIA MODIFICACIÓN] ---
    // Nueva lógica para mapear sectionName a la ruta de carpeta correcta
    
    let fileToFetch;
    let queryParams = `?t=${Date.now()}`;
    
    const appSections = ['main', 'explorer'];
    const systemSections = ['status-page', '404', 'error-missing-data'];

    if (sectionName.startsWith('login')) {
        // Todas las URLs de login (ej. 'login' y 'login/verification-additional')
        // cargan el mismo archivo 'auth/login.php'
        fileToFetch = 'auth/login';
    
    } else if (sectionName.startsWith('register')) {
        // Todas las URLs de registro cargan 'auth/register.php'
        fileToFetch = 'auth/register';
        
        // Pero pasamos el 'step' por query params para que JS sepa qué mostrar
        if (sectionName === 'register/additional-data') {
            queryParams += '&step=2';
        } else if (sectionName === 'register/verification-account') {
            queryParams += '&step=3';
        } else {
            queryParams += '&step=1';
        }
    
    } else if (sectionName === 'forgot-password') {
        fileToFetch = 'auth/forgot-password';
    
    } else if (appSections.includes(sectionName)) {
        fileToFetch = `app/${sectionName}`;
    
    } else if (systemSections.includes(sectionName)) {
        fileToFetch = `system/${sectionName}`;
    
    } else {
        // Fallback por si acaso
        fileToFetch = 'system/404';
    }
    // --- [TERMINA MODIFICACIÓN] ---
    
    try {
        // Usamos la variable 'fileToFetch' que ahora incluye la carpeta
        const resp = await fetch(`${basePath}includes/sections/${fileToFetch}.php${queryParams}`);
        if (!resp.ok) throw new Error('Error de carga');
        container.innerHTML = await resp.text();

        updateActiveMenu(sectionName);

        if (pushState) {
            // [CORRECCIÓN APLICADA AQUÍ]
            // Se cambió 'baseFPath' por 'basePath'
            const newUrl = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
            history.pushState({ section: sectionName }, '', newUrl);
        }
    } catch (error) {
        console.error(error);
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