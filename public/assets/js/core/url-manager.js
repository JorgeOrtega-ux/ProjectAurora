/**
 * url-manager.js
 * Gestiona la navegación SPA.
 */

import { I18n } from './i18n-manager.js';

export function initUrlManager() {
    console.log("SPA Router: Iniciado");

    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            loadContent(event.state.section);
            updateActiveMenu(event.state.section);
        } else {
            location.reload();
        }
    });

    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('[data-nav]');
        if (link) {
            e.preventDefault();
            const section = link.dataset.nav;
            
            if (!link.classList.contains('active')) {
                navigateTo(section);
            }
            
            const activeModules = document.querySelectorAll('.module-content.active');
            activeModules.forEach(mod => {
                if (mod.dataset.module !== 'moduleSurface' || window.innerWidth < 725) {
                     mod.classList.remove('active');
                     mod.classList.add('disabled');
                }
            });
            
            const profileModule = document.querySelector('[data-module="moduleProfile"]');
            if(profileModule) {
                profileModule.classList.remove('active');
                profileModule.classList.add('disabled');
            }
        }
    });
    
    const path = window.location.pathname.replace(window.BASE_PATH, '');
    const cleanPath = path.replace(/^\/+|\/+$/g, ''); 
    const currentSection = cleanPath || 'main';
    updateActiveMenu(currentSection);
}

export function navigateTo(section) {
    const basePath = window.BASE_PATH || '/ProjectAurora/';
    const url = (section === 'main') ? basePath : basePath + section;
    
    history.pushState({ section: section }, '', url);
    loadContent(section);
    updateActiveMenu(section);
}

async function loadContent(section) {
    const container = document.querySelector('.general-content-scrolleable');
    if (!container) return;

    container.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;"><div class="spinner"></div></div>';
    
    await new Promise(resolve => setTimeout(resolve, 200));

    try {
        const response = await fetch(`${window.BASE_PATH}public/loader.php?section=${section}`);
        const html = await response.text();
        
        container.innerHTML = html;
        container.scrollTop = 0; 
        
        const event = new CustomEvent('spa:view_loaded', { detail: { section } });
        document.dispatchEvent(event);
        
    } catch (error) {
        console.error("Error cargando sección:", error);
        container.innerHTML = `<div style="padding: 20px;"><p>${I18n.t('js.core.loading_error')}</p></div>`;
    }
}

function updateActiveMenu(section) {
    document.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
    
    let links = document.querySelectorAll(`.menu-link[data-nav="${section}"]`);
    links.forEach(l => l.classList.add('active'));

    const navMain = document.getElementById('nav-main');
    const navSettings = document.getElementById('nav-settings');

    if (navMain && navSettings) {
        if (section.startsWith('settings/')) {
            navMain.style.display = 'none';
            navSettings.style.display = 'flex';
            navSettings.style.flexDirection = 'column';
            navSettings.style.gap = '4px';
        } else {
            navSettings.style.display = 'none';
            navMain.style.display = 'flex';
            navMain.style.flexDirection = 'column';
            navMain.style.gap = '4px';
        }
    }
}