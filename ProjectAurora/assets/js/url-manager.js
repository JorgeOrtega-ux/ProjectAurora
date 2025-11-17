// assets/js/url-manager.js

// Configuración
const allowedSections = ['main', 'login', 'register', 'explorer']; 
const authZone = ['login', 'register'];
// Usamos la variable global definida en el <head> del index.php
const basePath = window.BASE_PATH || '/ProjectAurora/'; 

/**
 * Inicializa la gestión de URLs y navegación.
 */
export function initUrlManager() {
    // 1. Manejar botones de Atrás/Adelante del navegador
    window.addEventListener('popstate', handleUrlChange);

    // 2. CORREGIDO: Manejar clics en navegación (Delegación de eventos en el body)
    // Antes fallaba porque querySelector('.menu-list') tomaba el menú del perfil (header) 
    // y no el menú lateral. Al usar body, capturamos cualquier click en cualquier menú.
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('.menu-link[data-nav]');
        
        if (link) {
            e.preventDefault();
            const section = link.dataset.nav;
            
            // Opcional: Si quieres que el menú se cierre al hacer click en móvil
            // podrías disparar un evento click en el botón de menú o manipular las clases aquí.
            
            navigateTo(section);
        }
    });

    // 3. Manejar enlaces de texto de "Regístrate" o "Iniciar sesión" en los formularios
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('.form-footer-link a');
        if (link) {
            e.preventDefault();
            const text = link.innerText.toLowerCase();
            
            if (text.includes('regístrate')) {
                navigateTo('register');
            } else if (text.includes('iniciar sesión')) {
                navigateTo('login');
            }
        }
    });
}

/* --- Funciones Internas (No expuestas globalmente) --- */

function navigateTo(sectionName) {
    const currentSectionName = getSectionFromUrl();

    if (currentSectionName === sectionName) {
        return; // Ya estamos aquí
    }

    const isCurrentAuth = authZone.includes(currentSectionName);
    const isTargetAuth = authZone.includes(sectionName);

    // Si cambiamos de zona (Auth <-> App), recargamos para que PHP maneje la sesión
    if ((isCurrentAuth && !isTargetAuth) || (!isCurrentAuth && isTargetAuth)) {
        const newUrl = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
        window.location.href = newUrl;
    } else {
        // Navegación fluida (SPA)
        showSection(sectionName, true); 
    }
}

function getSectionFromUrl() {
    let path = window.location.pathname;
    // Normalizar path eliminando el basePath
    if (path.startsWith(basePath)) {
        path = path.substring(basePath.length);
    }
    // Limpiar slashes finales y query strings
    path = path.replace(/\/$/, '').split('?')[0];
    
    if (path === '') return 'main';
    if (allowedSections.includes(path)) return path;
    return 'main'; 
}

function handleUrlChange() {
    // Al usar botones de navegador, lo más seguro es recargar 
    // para sincronizar estado PHP/JS, o podrías llamar a showSection(..., false).
    window.location.reload();
}

async function showSection(sectionName, pushState = true) {
    const container = document.getElementById('section-container');
    const loaderParent = document.querySelector('.general-content'); 
    let loader; 
    
    // Actualizamos visualmente el menú activo antes de cargar
    updateActiveMenu(sectionName);

    if (!container || !loaderParent) return;

    try {
        // Spinner
        const loaderHTML = `<div class="loader-wrapper" id="dynamic-loader" style="display: flex;"><div class="loader-spinner"></div></div>`;
        loaderParent.insertAdjacentHTML('afterbegin', loaderHTML);
        loader = document.getElementById('dynamic-loader'); 

        // Petición AJAX (Importante: credentials: 'include' para enviar cookies PHP)
        const response = await fetch(`includes/sections/${sectionName}.php`, {
            credentials: 'include' 
        });
        
        if (!response.ok) throw new Error('Error de carga');

        const htmlContent = await response.text();
        container.innerHTML = htmlContent;

        if (pushState) {
            const newUrl = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
            history.pushState({ section: sectionName }, '', newUrl);
        }

    } catch (error) {
        console.error(error);
        // Si falla (ej. sesión expirada 401), redirigir al home fuerza el router PHP
        if(sectionName !== 'login') window.location.href = basePath; 
    } finally {
        if (loader) loader.remove(); 
    }
}

function updateActiveMenu(sectionName) {
    document.querySelectorAll('.menu-link').forEach(link => {
        link.classList.remove('active');
    });
    
    // Buscamos el link específico que tenga data-nav igual a la sección
    const activeLink = document.querySelector(`.menu-link[data-nav="${sectionName}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
}