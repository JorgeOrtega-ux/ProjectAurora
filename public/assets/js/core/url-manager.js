/**
 * public/assets/js/core/url-manager.js
 */

export function initUrlManager() {
    console.log("SPA Router: Iniciado");

    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            loadContent(event.state.section, false);
        } else {
            location.reload(); 
        }
    });

    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('[data-nav]');
        
        if (link) {
            e.preventDefault();
            const section = link.dataset.nav;
            const currentPath = window.location.pathname.split('/').pop() || 'main';
            
            // Comentado para permitir recarga si es necesario en desarrollo
            // if (section === currentPath && section !== 'main') return;

            navigateTo(section);
        }
    });
}

export function navigateTo(section) {
    const basePath = window.BASE_PATH || '/';
    // Si la sección es 'main', vamos a la raíz, si no, concatenamos
    const url = (section === 'main') ? basePath : basePath + section;

    history.pushState({ section: section }, '', url);
    loadContent(section, true);
}

async function loadContent(section, updateHistory) {
    const container = document.getElementById('app-content');
    if (!container) return;

    container.innerHTML = `
        <div class="loader-container">
            <div class="spinner"></div>
        </div>
    `;
    container.style.opacity = '1'; 

    try {
        const minDelay = new Promise(resolve => setTimeout(resolve, 200));
        
        // --- FIX: Usar ruta absoluta para el loader ---
        const basePath = window.BASE_PATH || '/';
        const loaderUrl = `${basePath}loader.php?section=${section}&_t=${Date.now()}`;
        
        const fetchRequest = fetch(loaderUrl);

        const [_, response] = await Promise.all([minDelay, fetchRequest]);
        
        if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);

        // Verificamos que sea JSON antes de parsear
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            throw new Error("La respuesta del servidor no es JSON válido (posible error 404 o PHP)");
        }

        const data = await response.json();
        container.innerHTML = data.content;
        
        if(data.title) document.title = data.title;

        window.scrollTo(0, 0);

        updateSidebarContext(section);
        updateActiveLinks(section);

    } catch (error) {
        console.error("Error SPA:", error);
        container.innerHTML = `
            <div style="padding:40px; text-align:center; color: #ff4444;">
                <span class="material-symbols-rounded" style="font-size: 48px;">link_off</span>
                <h3>Error de carga</h3>
                <p>No se pudo cargar la sección. Verifica la consola.</p>
                <p style="font-size:12px; color:#666;">Intentando acceder a: ${section}</p>
            </div>
        `;
    }
}

function updateSidebarContext(section) {
    const helpSections = window.HELP_SECTIONS || [];
    const settingsSections = window.SETTINGS_SECTIONS || [];

    const isHelp = helpSections.includes(section);
    const isSettings = settingsSections.includes(section);

    const appGroup = document.getElementById('nav-group-app');
    const helpGroup = document.getElementById('nav-group-help');
    const settingsGroup = document.getElementById('nav-group-settings');

    // Helper para ocultar/mostrar
    const toggle = (el, show) => { if(el) el.style.display = show ? 'block' : 'none'; };

    // Reset general (ocultar todo primero)
    toggle(appGroup, false);
    toggle(helpGroup, false);
    toggle(settingsGroup, false);

    if (isHelp) {
        toggle(helpGroup, true);
    } else if (isSettings) {
        toggle(settingsGroup, true);
    } else {
        toggle(appGroup, true);
    }
}

function updateActiveLinks(section) {
    document.querySelectorAll('[data-nav]').forEach(el => el.classList.remove('active'));
    // El selector debe coincidir exactamente con el data-nav
    document.querySelectorAll(`[data-nav="${section}"]`).forEach(el => el.classList.add('active'));
}