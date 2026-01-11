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

            // --- NUEVO: Evitar recarga si ya estamos en la sección ---
            if (link.classList.contains('active')) {
                console.log('Navegación evitada: Ya estás en esta sección.');
                return;
            }
            // ---------------------------------------------------------
            
            const section = link.dataset.nav;
            
            // Navegación normal
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

    // Spinner de carga
    container.innerHTML = `
        <div class="loader-container">
            <div class="spinner"></div>
        </div>
    `;
    container.style.opacity = '1'; 

    try {
        const minDelay = new Promise(resolve => setTimeout(resolve, 200));
        const basePath = window.BASE_PATH || '/';
        const loaderUrl = `${basePath}loader.php?section=${section}&_t=${Date.now()}`;
        
        const fetchRequest = fetch(loaderUrl);

        const [_, response] = await Promise.all([minDelay, fetchRequest]);
        
        if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);

        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            throw new Error("Respuesta inválida (No JSON)");
        }

        const data = await response.json();
        
        // 1. Actualizar Contenido Principal
        container.innerHTML = data.content;
        
        // 2. Actualizar Menú Lateral (Si el servidor envió uno nuevo)
        if (data.menuHTML) {
            const surface = document.querySelector('.module-surface');
            if (surface) {
                surface.innerHTML = data.menuHTML;
            }
        }
        
        // 3. Actualizar Título
        if(data.title) document.title = data.title;

        window.scrollTo(0, 0);

        // 4. Reactivar enlaces (Estilo visual)
        updateActiveLinks(section);

    } catch (error) {
        console.error("Error SPA:", error);
        container.innerHTML = `
            <div style="padding:40px; text-align:center; color: #ff4444;">
                <span class="material-symbols-rounded" style="font-size: 48px;">link_off</span>
                <h3>Error de carga</h3>
                <p>No se pudo cargar la sección.</p>
            </div>
        `;
    }
}

function updateActiveLinks(section) {
    // Quitamos 'active' de todos los links
    document.querySelectorAll('[data-nav]').forEach(el => el.classList.remove('active'));
    
    // Ponemos 'active' solo al link exacto de la sección actual
    document.querySelectorAll(`[data-nav="${section}"]`).forEach(el => el.classList.add('active'));
}