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
            location.reload(); 
        }
    });

    // 2. Interceptar Clics en enlaces con data-nav
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

// Función pública para navegar
export function navigateTo(section) {
    // CORRECCIÓN: Usar la variable global definida en index.php
    // Si no existe, usa '/' como fallback
    const basePath = window.BASE_PATH || '/';
    
    // Construir URL visual
    // Si es main, vamos a la raíz del proyecto (basePath). Si no, basePath + sección
    const url = (section === 'main') ? basePath : basePath + section;

    // Guardar en historial
    history.pushState({ section: section }, '', url);
    
    // Cargar contenido
    loadContent(section, true);
}

async function loadContent(section, updateHistory) {
    const container = document.getElementById('app-content');
    if (!container) return;

    // 1. Inyectar Spinner HTML
    // Usamos las clases que acabamos de crear en el CSS
    container.innerHTML = `
        <div class="loader-container">
            <div class="spinner"></div>
        </div>
    `;
    
    // Aseguramos opacidad al 100% por si se quedó modificada antes
    container.style.opacity = '1'; 

    try {
        // 2. Crear las Promesas
        // Promesa A: Esperar mínimo 200ms
        const minDelay = new Promise(resolve => setTimeout(resolve, 200));
        
        // Promesa B: Petición de datos real
        const fetchRequest = fetch(`loader.php?section=${section}&_t=${Date.now()}`);

        // 3. Ejecutar ambas en paralelo y esperar a que terminen
        // Promise.all devuelve un array con los resultados.
        // El primer resultado es del timeout (no nos importa), el segundo es la respuesta del fetch.
        const [_, response] = await Promise.all([minDelay, fetchRequest]);
        
        if (!response.ok) throw new Error('Error en red');

        const data = await response.json();

        // 4. Inyectar HTML recibido
        container.innerHTML = data.content;
        
        // 5. Actualizar Título
        if(data.title) document.title = data.title;

        // 6. Scroll arriba
        window.scrollTo(0, 0);

        // 7. Actualizar clases activas en el menú
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

function updateActiveLinks(section) {
    document.querySelectorAll('[data-nav]').forEach(el => {
        el.classList.toggle('active', el.dataset.nav === section);
    });
}