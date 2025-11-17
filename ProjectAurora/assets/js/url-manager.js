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

    // 2. Manejar clics en el Menú Lateral (Delegación de eventos)
    // Busca elementos con la clase .menu-link que tengan el atributo data-nav
    const menuList = document.querySelector('.menu-list');
    if (menuList) {
        menuList.addEventListener('click', (e) => {
            const link = e.target.closest('.menu-link[data-nav]');
            if (link) {
                e.preventDefault();
                const section = link.dataset.nav;
                navigateTo(section);
            }
        });
    }

    // 3. Manejar enlaces de texto de "Regístrate" o "Iniciar sesión" en los formularios
    // (Estos suelen ser <a href="#" onclick="..."> en el HTML antiguo)
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
    if (path.startsWith(basePath)) {
        path = path.substring(basePath.length);
    }
    path = path.replace(/\/$/, '').split('?')[0];
    
    if (path === '') return 'main';
    if (allowedSections.includes(path)) return path;
    return 'main'; 
}

function handleUrlChange() {
    window.location.reload();
}

async function showSection(sectionName, pushState = true) {
    const container = document.getElementById('section-container');
    const loaderParent = document.querySelector('.general-content'); 
    let loader; 
    
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
        if(sectionName !== 'login') window.location.href = basePath; 
    } finally {
        if (loader) loader.remove(); 
    }
}

function updateActiveMenu(sectionName) {
    document.querySelectorAll('.menu-link').forEach(link => {
        link.classList.remove('active');
    });
    const activeLink = document.querySelector(`.menu-link[data-nav="${sectionName}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
}