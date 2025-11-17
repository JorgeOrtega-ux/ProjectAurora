// assets/js/url-manager.js

const allowedSections = ['main', 'login', 'register', 'explorer']; 
const authZone = ['login', 'register'];
const basePath = window.BASE_PATH || '/ProjectAurora/'; 

export function initUrlManager() {
    // 1. Botones Atrás/Adelante: Actualización parcial si es posible
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            showSection(event.state.section, false);
        } else {
            // Si no hay estado (carga inicial), no hacemos nada o recargamos por seguridad
             window.location.reload(); 
        }
    });

    // 2. Clics en el menú: Interceptamos para SPA
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('.menu-link[data-nav]');
        if (link) {
            e.preventDefault();
            const section = link.dataset.nav;
            const currentSection = getSectionFromUrl();
            
            if (section !== currentSection) {
                navigateTo(section);
            }
        }
    });

    // 3. Links internos de formularios
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('.form-footer-link a');
        if (link) {
            e.preventDefault();
            const text = link.innerText.toLowerCase();
            if (text.includes('regístrate')) navigateTo('register');
            else if (text.includes('iniciar sesión')) navigateTo('login');
        }
    });
}

/* --- Lógica de Navegación --- */

function navigateTo(sectionName) {
    const currentSectionName = getSectionFromUrl();
    const isCurrentAuth = authZone.includes(currentSectionName);
    const isTargetAuth = authZone.includes(sectionName);

    // Si cambiamos de zona (Login <-> App), recarga completa obligatoria
    if ((isCurrentAuth && !isTargetAuth) || (!isCurrentAuth && isTargetAuth)) {
        window.location.href = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
    } else {
        // Si estamos dentro de la App, actualización parcial
        showSection(sectionName, true); 
    }
}

function getSectionFromUrl() {
    let path = window.location.pathname;
    if (path.startsWith(basePath)) path = path.substring(basePath.length);
    path = path.replace(/\/$/, '').split('?')[0];
    if (path === '' || !allowedSections.includes(path)) return 'main';
    return path; 
}

async function showSection(sectionName, pushState = true) {
    const container = document.getElementById('section-container');
    // Loader sobre el padre para no borrarlo al reemplazar contenido
    const loaderParent = document.querySelector('.general-content'); 

    if (!container || !loaderParent) return;

    // 1. Actualizar menú visualmente (rápido)
    document.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
    const activeLink = document.querySelector(`.menu-link[data-nav="${sectionName}"]`);
    if (activeLink) activeLink.classList.add('active');

    // 2. Mostrar Spinner
    let loader = document.getElementById('dynamic-loader');
    if (!loader) {
        // Lo insertamos con display flex para centrar
        loaderParent.insertAdjacentHTML('afterbegin', 
            `<div class="loader-wrapper" id="dynamic-loader" style="display: flex;"><div class="loader-spinner"></div></div>`
        );
        loader = document.getElementById('dynamic-loader');
    }

    try {
        // 3. Fetch del contenido (sin caché)
        const response = await fetch(`${basePath}includes/sections/${sectionName}.php?t=${Date.now()}`, {
            credentials: 'include'
        });

        if (!response.ok) throw new Error('Error de carga');

        const htmlContent = await response.text();

        // 4. REEMPLAZO EXACTO (Sin transiciones)
        // Esto elimina lo viejo y pone lo nuevo en un solo frame de renderizado.
        container.innerHTML = htmlContent;

        // 5. Actualizar URL
        if (pushState) {
            const newUrl = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
            history.pushState({ section: sectionName }, '', newUrl);
        }

    } catch (error) {
        console.error(error);
        // Si falla, redirigir es lo más seguro
        window.location.href = basePath; 
    } finally {
        // 6. Quitar Spinner
        if (loader) loader.remove();
    }
}