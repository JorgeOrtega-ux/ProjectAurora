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
            if (section === currentPath && section !== 'main') return;

            navigateTo(section);
        }
    });
}

export function navigateTo(section) {
    const basePath = window.BASE_PATH || '/';
    const url = (section === 'main') ? basePath : basePath + section;

    history.pushState({ section: section }, '', url);
    loadContent(section, true);
}

async function loadContent(section, updateHistory) {
    const container = document.getElementById('app-content');
    if (!container) return;

    // Spinner
    container.innerHTML = `
        <div class="loader-container">
            <div class="spinner"></div>
        </div>
    `;
    container.style.opacity = '1'; 

    try {
        const minDelay = new Promise(resolve => setTimeout(resolve, 200));
        const fetchRequest = fetch(`loader.php?section=${section}&_t=${Date.now()}`);

        const [_, response] = await Promise.all([minDelay, fetchRequest]);
        
        if (!response.ok) throw new Error('Error en red');

        const data = await response.json();

        container.innerHTML = data.content;
        
        if(data.title) document.title = data.title;

        window.scrollTo(0, 0);

        // --- NUEVO: Actualizar Contexto del Menú ---
        updateSidebarContext(section);
        updateActiveLinks(section);

    } catch (error) {
        console.error("Error SPA:", error);
        container.innerHTML = `
            <div style="padding:40px; text-align:center; color: #ff4444;">
                <h3>Error de carga</h3>
                <p>No se pudo cargar el contenido. Inténtalo de nuevo.</p>
            </div>
        `;
    }
}

/**
 * Decide qué grupo de menú mostrar en el sidebar
 */
function updateSidebarContext(section) {
    const helpSections = window.HELP_SECTIONS || [];
    const isHelp = helpSections.includes(section);

    const appGroup = document.getElementById('nav-group-app');
    const helpGroup = document.getElementById('nav-group-help');

    if (appGroup && helpGroup) {
        if (isHelp) {
            appGroup.style.display = 'none';
            helpGroup.style.display = 'block';
        } else {
            appGroup.style.display = 'block';
            helpGroup.style.display = 'none';
        }
    }
}

function updateActiveLinks(section) {
    // Quitar activo a todos
    document.querySelectorAll('[data-nav]').forEach(el => {
        el.classList.remove('active');
    });

    // Poner activo solo a los que coincidan con la sección actual
    document.querySelectorAll(`[data-nav="${section}"]`).forEach(el => {
        el.classList.add('active');
    });
}