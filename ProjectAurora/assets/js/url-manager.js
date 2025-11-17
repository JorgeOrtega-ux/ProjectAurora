const allowedSections = [
    'main', 
    'login', 
    'register', 
    'register/additional-data', 
    'register/verification-account', 
    'explorer'
];

const authZone = [
    'login', 
    'register', 
    'register/additional-data', 
    'register/verification-account'
];

const basePath = window.BASE_PATH || '/ProjectAurora/';

export function initUrlManager() {
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            showSection(event.state.section, false);
        } else {
            // Si no hay estado (primera carga o refresh), recarga segura
            window.location.reload();
        }
    });

    // Navegación menú lateral
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('.menu-link[data-nav]');
        if (link) {
            e.preventDefault();
            const section = link.dataset.nav;
            const currentSection = getSectionFromUrl();

            if (section !== currentSection) {
                navigateTo(section);
            }
        }
    });

    // Navegación links footer (Login/Register switch)
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('.form-footer-link a');
        if (link) {
            // Evitamos interferir si el link tiene onclick explícito en HTML
            // Pero como tu código usa onclick="... navigateTo", 
            // podemos dejar que el HTML lo maneje o interceptarlo aquí.
            // Para este caso, dejaremos que el HTML inline funcione ya que llama a window.navigateTo si existiera,
            // pero como navigateTo no es global, mejor interceptamos aquí si el href es "#"
            
            // NOTA: Tus vistas usan onclick="... navigateTo(...)". 
            // Como 'navigateTo' NO está expuesta globalmente (es interna de este módulo),
            // necesitamos exponerla o manejarlo por delegación de eventos completa.
            
            // Solución rápida: Exponer navigateTo al objeto window para que los onclick del HTML funcionen
        }
    });
}

// Exponer navigateTo globalmente para que los onclick del HTML funcionen
window.navigateTo = function(sectionName) {
    const currentSectionName = getSectionFromUrl();
    
    // Verificar si estamos saltando entre zonas de auth vs app interna
    const isCurrentAuth = authZone.includes(currentSectionName);
    const isTargetAuth = authZone.includes(sectionName);

    // Si cambiamos de contexto (Login -> Main o viceversa), recarga completa
    if ((isCurrentAuth && !isTargetAuth) || (!isCurrentAuth && isTargetAuth)) {
        window.location.href = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
    } else {
        // Navegación SPA (AJAX)
        showSection(sectionName, true);
    }
};

function getSectionFromUrl() {
    let path = window.location.pathname;
    if (path.startsWith(basePath)) path = path.substring(basePath.length);
    path = path.replace(/\/$/, '').split('?')[0];
    if (path === '' || !allowedSections.includes(path)) return 'main';
    return path;
}

async function showSection(sectionName, pushState = true) {
    const container = document.getElementById('section-container');
    // En pantallas login/register el layout es distinto, quizás no exista 'general-content-bottom'.
    // Pero según tu estructura, Register sí está dentro del layout general.
    
    if (!container) {
        // Si no hay contenedor (ej. estructura HTML diferente), forzar recarga
        window.location.href = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
        return;
    }

    // Transformar nombre de sección con slash a guion para buscar archivo (Frontend side logic optional, 
    // pero el backend PHP lo hace. Aquí pedimos la URL con slash tal cual).
    // PHP router: register/additional-data -> include register-additional-data.php
    // Fetch JS: pedimos la URL limpia.
    
    // Loader
    let loader = document.getElementById('dynamic-loader');
    if (!loader) {
        // Intentar ponerlo sobre el contenedor de sección
        container.insertAdjacentHTML('afterbegin',
            `<div class="loader-wrapper" id="dynamic-loader" style="display: flex; position:absolute; background:rgba(255,255,255,0.8);"><div class="loader-spinner"></div></div>`
        );
        loader = document.getElementById('dynamic-loader');
    }

    try {
        // Convertir slash a guion SOLO para el nombre del archivo si fueramos a cargar estático,
        // PERO aquí estamos pidiendo al Router PHP que renderice la sección.
        // Tu router PHP incluye el archivo basado en la URL.
        // Así que fetch('register/additional-data') debería devolver el HTML de esa sección.
        
        const response = await fetch(`${basePath}includes/sections/${sectionName.replace('/', '-')}.php?t=${Date.now()}`, {
            credentials: 'include'
        });

        // NOTA CRÍTICA: Tu router original cargaba includes/sections/{$sectionName}.php
        // Si sectionName tiene slash "register/data", la ruta de archivo fallaría en JS fetch directo a archivo.
        // Por eso en PHP hicimos str_replace.
        // Aquí en JS, al hacer fetch directo al archivo .php, debemos usar el nombre real del archivo con guion.
        
        // URL a fetchear: includes/sections/register-additional-data.php
        const fileToFetch = sectionName.replace('/', '-');
        
        const fetchUrl = `${basePath}includes/sections/${fileToFetch}.php?t=${Date.now()}`;

        const resp = await fetch(fetchUrl);
        if (!resp.ok) throw new Error('Error de carga');

        const htmlContent = await resp.text();
        container.innerHTML = htmlContent;

        if (pushState) {
            const newUrl = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
            history.pushState({ section: sectionName }, '', newUrl);
        }
        
        // Actualizar clases activas en menú si aplica
        document.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
        const activeLink = document.querySelector(`.menu-link[data-nav="${sectionName}"]`);
        if (activeLink) activeLink.classList.add('active');

    } catch (error) {
        console.error(error);
        // Fallback
        window.location.href = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
    } finally {
        if (loader) loader.remove();
    }
}