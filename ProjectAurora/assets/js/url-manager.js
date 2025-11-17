const allowedSections = [
    'main', 'login', 'register', 'explorer',
    // Agregamos las nuevas rutas a la lista blanca de JS
    'register/additional-data',
    'register/verification-account',
    // NUEVO
    'forgot-password'
];
const authZone = ['login', 'register', 'register/additional-data', 'register/verification-account', 'forgot-password'];
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
    
    // Opcional: Asegurar que el menú esté correcto al cargar (por si el HTML viniera desincronizado)
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
    const container = document.getElementById('section-container');
    if (!container) { window.location.reload(); return; }

    // --- LOGICA DE FETCH INTELIGENTE ---
    // Si piden algo de register, siempre llamamos a 'register.php' pero con parámetro step
    let fileToFetch = sectionName.replace('/', '-'); 
    let queryParams = `?t=${Date.now()}`;

    if (sectionName === 'register/additional-data') {
        fileToFetch = 'register';
        queryParams += '&step=2';
    } else if (sectionName === 'register/verification-account') {
        fileToFetch = 'register';
        queryParams += '&step=3';
    } else if (sectionName === 'register') {
        fileToFetch = 'register';
        queryParams += '&step=1';
    }
    // -----------------------------------

    try {
        const resp = await fetch(`${basePath}includes/sections/${fileToFetch}.php${queryParams}`);
        if (!resp.ok) throw new Error('Error de carga');
        container.innerHTML = await resp.text();

        // [CORRECCIÓN] Actualizamos el menú visualmente aquí
        updateActiveMenu(sectionName);

        if (pushState) {
            const newUrl = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
            history.pushState({ section: sectionName }, '', newUrl);
        }
    } catch (error) {
        console.error(error);
    }
}

// --- [NUEVA FUNCIÓN] ---
// Se encarga de mover la clase 'active' al link correcto
function updateActiveMenu(sectionName) {
    // 1. Buscar todos los links del menú y quitarles la clase active
    const allLinks = document.querySelectorAll('.menu-link[data-nav]');
    allLinks.forEach(link => {
        link.classList.remove('active');
    });

    // 2. Buscar el link que coincida con la sección actual y ponerle active
    //    Nota: Si sectionName es 'main', busca [data-nav="main"]
    const activeLink = document.querySelector(`.menu-link[data-nav="${sectionName}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
}