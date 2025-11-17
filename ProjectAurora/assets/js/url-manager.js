document.addEventListener('DOMContentLoaded', () => {
    // Escucha los eventos 'popstate' (botones de atrás/adelante del navegador)
    window.addEventListener('popstate', handleUrlChange);
});

// --- Definimos las "zonas" de tu aplicación ---
const authZone = ['login', 'register'];
const mainZone = ['main']; // Añade aquí futuras páginas 'autenticadas' ej: 'profile', 'settings'
const basePath = '/ProjectAurora/'; // Path base de tu proyecto


/**
 * Obtiene la sección actual de la URL
 */
function getSectionFromUrl() {
    let path = window.location.pathname;

    if (path.startsWith(basePath)) {
        path = path.substring(basePath.length);
    }

    path = path.replace(/\/$/, '').split('?')[0];
    const allowedSections = ['main', 'login', 'register'];

    if (allowedSections.includes(path)) {
        return path;
    }
    return 'main'; 
}

/**
 * Maneja el cambio de URL (popstate)
 */
function handleUrlChange() {
    // Forzamos recarga para que el PHP re-evalue si debe mostrar el header
    window.location.reload();
}

/**
 * Función global para navegar a una sección (ej. llamada desde un onclick)
 * @param {string} sectionName - El data-section de la sección a mostrar
 */
window.navigateTo = (sectionName) => {
    const currentSectionName = getSectionFromUrl();

    if (currentSectionName === sectionName) {
        return; 
    }

    // --- Lógica de Zonas ---
    const isCurrentAuth = authZone.includes(currentSectionName);
    const isTargetAuth = authZone.includes(sectionName);
    const isCurrentMain = mainZone.includes(currentSectionName);
    const isTargetMain = mainZone.includes(sectionName);

    // Si las zonas son DIFERENTES, recarga la página.
    if ((isCurrentAuth && isTargetMain) || (isCurrentMain && isTargetAuth)) {
        const newUrl = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
        window.location.href = newUrl;
    } else {
        // Si las zonas son IGUALES, usa fetch (rápido).
        showSection(sectionName, true); 
    }
}

/**
 * Muestra una sección específica, ocultando las demás y manejando el loader
 * ¡AHORA USA FETCH PARA CARGA DINÁMICA!
 * (Esta función ahora SÓLO se usa para navegación DENTRO de una zona)
 * * * @param {string} sectionName - El data-section de la sección
 * @param {boolean} pushState - Si es true, actualiza el historial del navegador
 */
async function showSection(sectionName, pushState = true) {
    const container = document.getElementById('section-container');
    // --- MODIFICACIÓN (1/3): Encontrar el 'padre' del loader ---
    const loaderParent = document.querySelector('.general-content'); 
    let loader; // Variable para guardar la referencia al loader creado

    if (!container || !loaderParent) {
        console.error('Loader parent or Section Container not found!');
        return;
    }

    try {
        // --- MODIFICACIÓN (2/3): Crear y mostrar el loader ---
        const loaderHTML = `
            <div class="loader-wrapper" id="dynamic-loader" style="display: flex;">
                <div class="loader-spinner"></div>
            </div>`;
        // Lo insertamos como primer hijo de .general-content
        loaderParent.insertAdjacentHTML('afterbegin', loaderHTML);
        loader = document.getElementById('dynamic-loader'); // Obtenemos la referencia
        // --- Fin Modificación 2/3 ---


        // 2. Solicitar el nuevo contenido .php
        const response = await fetch(`includes/sections/${sectionName}.php`);
        
        if (!response.ok) {
            throw new Error(`Error ${response.status}: No se pudo cargar la sección ${sectionName}.`);
        }

        const htmlContent = await response.text();

        // 3. Reemplazar el contenido del contenedor
        container.innerHTML = htmlContent;

        // 4. Actualizar la URL en el navegador (si se solicitó)
        if (pushState) {
            const newUrl = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
            history.pushState({ section: sectionName }, '', newUrl);
        }

    } catch (error) {
        console.error('Error al cargar la sección:', error);
        window.location.href = basePath; // Recargar a 'main' en caso de error
    } finally {
        // --- MODIFICACIÓN (3/3): Ocultar Y ELIMINAR el loader ---
        // Siempre se ejecuta, incluso si hay error
        if (loader) {
            loader.remove(); // Elimina el elemento del DOM
        }
        // --- Fin Modificación 3/3 ---
    }
}