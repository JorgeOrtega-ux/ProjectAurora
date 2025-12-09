import { ContentService } from './api-services.js';

export function initUrlManager() {
    console.log("SPA Router: Iniciado");

    // 1. Listener de navegación
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('[data-nav]');
        if (link) {
            e.preventDefault();
            const section = link.dataset.nav;
            navigateTo(section);
            
            // Cerrar menú móvil si corresponde
            const surface = document.querySelector('[data-module="moduleSurface"]');
            if(surface && surface.classList.contains('active')) {
                surface.classList.remove('active');
                surface.classList.add('disabled');
            }
        }
    });

    // 2. Listener para botones Atrás/Adelante
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            loadContent(event.state.section);
            updateActiveMenu(event.state.section);
        } else {
            location.reload(); 
        }
    });
}

export function navigateTo(section) {
    const basePath = window.BASE_PATH || '/ProjectAurora/';
    const url = (section === 'main') ? basePath : basePath + section;
    
    // Cambiar URL y Menú visualmente
    history.pushState({ section: section }, '', url);
    updateActiveMenu(section);
    
    // Iniciar carga
    loadContent(section);
}

function updateActiveMenu(section) {
    document.querySelectorAll('.menu-link').forEach(link => {
        link.classList.remove('active');
    });

    document.querySelectorAll(`.menu-link[data-nav="${section}"]`).forEach(link => {
        link.classList.add('active');
    });
}

async function loadContent(section) {
    const container = document.querySelector('[data-container="main-section"]');
    if (!container) return;

    // --- PASO 1: LIMPIEZA INMEDIATA ---
    container.innerHTML = `
        <div class="loader-container">
            <div class="spinner"></div>
        </div>
    `;

    try {
        // --- PASO 2: PARALELISMO (Service + Delay) ---
        // Usamos ContentService en lugar de fetch directo
        const [htmlContent] = await Promise.all([
            ContentService.fetchSection(section),
            new Promise(resolve => setTimeout(resolve, 200)) // Espera mínima visual
        ]);
        
        // --- PASO 3: INYECCIÓN ---
        container.innerHTML = htmlContent;
        container.scrollTop = 0; 

    } catch (error) {
        console.error("Error cargando sección:", error);
        container.innerHTML = `
            <div style="text-align:center; padding: 40px; color: #d32f2f;">
                <span class="material-symbols-rounded" style="font-size: 48px;">error</span>
                <p>No se pudo cargar el contenido.</p>
                <button onclick="location.reload()" style="margin-top:10px; padding:8px 16px; cursor:pointer; border:1px solid #ccc; background:#fff; border-radius:4px;">Reintentar</button>
            </div>
        `;
    }
}