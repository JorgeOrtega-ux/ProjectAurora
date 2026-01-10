/**
 * public/assets/js/core/url-manager.js
 */

export function initUrlManager() {
    console.log("SPA Router: Iniciado");

    // 1. Manejar navegación con botones Atrás/Adelante del navegador
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            loadContent(event.state.section, false);
        } else {
            // Si no hay estado (ej. carga inicial), recargamos para asegurar consistencia
            location.reload(); 
        }
    });

    // 2. Interceptar Clics en enlaces con data-nav
    document.body.addEventListener('click', (e) => {
        // Buscamos el link más cercano con el atributo data-nav
        const link = e.target.closest('[data-nav]');
        
        if (link) {
            e.preventDefault();
            const section = link.dataset.nav;
            
            // Evitar recargar si ya estamos ahí
            const currentPath = window.location.pathname.split('/').pop() || 'main';
            if (section === currentPath && section !== 'main') return;

            navigateTo(section);
        }
    });
}

// Función pública para navegar
export function navigateTo(section) {
    // Ajusta BASE_PATH si tu proyecto está en subcarpeta (ej: '/mi-web/')
    const basePath = '/'; 
    
    // Construir URL visual
    const url = (section === 'main') ? basePath : basePath + section;

    // Guardar en historial
    history.pushState({ section: section }, '', url);
    
    // Cargar contenido
    loadContent(section, true);
}

async function loadContent(section, updateHistory) {
    const container = document.getElementById('app-content');
    if (!container) return;

    // 1. UI de Carga (Spinner)
    container.style.opacity = '0.5';
    container.innerHTML = '<div style="padding:50px; text-align:center;">Cargando...</div>';

    try {
        // 2. Fetch al loader PHP
        // Agregamos timestamp (_t) para evitar caché agresivo del navegador
        const response = await fetch(`public/loader.php?section=${section}&_t=${Date.now()}`);
        
        if (!response.ok) throw new Error('Error en red');

        const data = await response.json();

        // 3. Inyectar HTML
        container.style.opacity = '1';
        container.innerHTML = data.content;
        
        // 4. Actualizar Título
        if(data.title) document.title = data.title;

        // 5. Scroll arriba
        window.scrollTo(0, 0);

        // 6. Actualizar clases activas en el menú
        updateActiveLinks(section);

    } catch (error) {
        console.error("Error SPA:", error);
        container.innerHTML = '<p>Error al cargar el contenido.</p>';
        container.style.opacity = '1';
    }
}

function updateActiveLinks(section) {
    document.querySelectorAll('[data-nav]').forEach(el => {
        el.classList.toggle('active', el.dataset.nav === section);
    });
}