document.addEventListener('DOMContentLoaded', () => {
    // Escucha los eventos 'popstate' (botones de atrás/adelante del navegador)
    window.addEventListener('popstate', handleUrlChange);
});

/**
 * Obtiene la sección actual de la URL
 */
function getSectionFromUrl() {
    const basePath = '/ProjectAurora/';
    let path = window.location.pathname;

    if (path.startsWith(basePath)) {
        path = path.substring(basePath.length);
    }

    path = path.replace(/\/$/, '').split('?')[0];
    const allowedSections = ['main', 'login', 'register'];

    if (path === '' || path === 'main') {
        return 'main';
    }
    if (allowedSections.includes(path)) {
        return path;
    }
    return 'main'; // Default
}

/**
 * Maneja el cambio de URL (popstate)
 */
function handleUrlChange() {
    const sectionName = getSectionFromUrl();
    showSection(sectionName, false); // false = no empujar al historial
}

/**
 * Función global para navegar a una sección (ej. llamada desde un onclick)
 * @param {string} sectionName - El data-section de la sección a mostrar
 */
window.navigateTo = (sectionName) => {
    // Revisa cuál es la sección activa AHORA MISMO en el DOM
    const currentActive = document.querySelector('.section-content.active');
    const currentSectionName = currentActive ? currentActive.dataset.section : null;

    if (currentSectionName !== sectionName) {
        showSection(sectionName, true); // true = empujar al historial
    }
}

/**
 * Muestra una sección específica, ocultando las demás y manejando el loader
 * ¡AHORA USA FETCH PARA CARGA DINÁMICA!
 * * @param {string} sectionName - El data-section de la sección
 * @param {boolean} pushState - Si es true, actualiza el historial del navegador
 */
async function showSection(sectionName, pushState = true) {
    const loader = document.querySelector('.loader-wrapper');
    const container = document.getElementById('section-container');
    const header = document.getElementById('main-header'); // <-- MODIFICADO: Seleccionamos el header

    if (!loader || !container) {
        console.error('Loader or Section Container not found!');
        return;
    }

    // --- INICIO DE LA MODIFICACIÓN ---
    // Gestiona la visibilidad del header dinámicamente
    if (header) { // 'header' puede no existir si se cargó /login de inicio
        if (sectionName === 'login' || sectionName === 'register') {
            header.style.display = 'none';
        } else {
            header.style.display = 'block'; // 'block' es seguro, 'flex' también si lo prefieres
        }
    }
    // --- FIN DE LA MODIFICACIÓN ---

    // 1. Mostrar el loader
    loader.style.display = 'flex';

  try {
        // 2. Solicitar el nuevo contenido .php
        const response = await fetch(`includes/sections/${sectionName}.php`); // <-- RUTA CORREGIDA
        
        if (!response.ok) {
            throw new Error(`Error ${response.status}: No se pudo cargar la sección ${sectionName}.`);
        }

        const htmlContent = await response.text();

        // 3. Reemplazar el contenido del contenedor
        // Usamos innerHTML para 'parsear' el string de texto a elementos HTML
        container.innerHTML = htmlContent;

        // 4. Actualizar la URL en el navegador (si se solicitó)
        if (pushState) {
            const basePath = '/ProjectAurora/';
            const newUrl = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
            history.pushState({ section: sectionName }, '', newUrl);
        }

    } catch (error) {
        console.error('Error al cargar la sección:', error);
        // Opcional: cargar una sección de error o volver a 'main'
        if (sectionName !== 'main') {
            showSection('main', pushState); // Intenta cargar 'main' como fallback
        }
    } finally {
        // 5. Ocultar el loader (siempre se ejecuta, incluso si hay error)
        loader.style.display = 'none';
    }
}