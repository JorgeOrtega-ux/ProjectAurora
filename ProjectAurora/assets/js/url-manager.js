document.addEventListener('DOMContentLoaded', () => {
    window.addEventListener('popstate', handleUrlChange);
});

const authZone = ['login', 'register'];
const mainZone = ['main']; 
const basePath = '/ProjectAurora/'; 


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

function handleUrlChange() {
    window.location.reload();
}

window.navigateTo = (sectionName) => {
    const currentSectionName = getSectionFromUrl();

    if (currentSectionName === sectionName) {
        return; 
    }

    const isCurrentAuth = authZone.includes(currentSectionName);
    const isTargetAuth = authZone.includes(sectionName);
    const isCurrentMain = mainZone.includes(currentSectionName);
    const isTargetMain = mainZone.includes(sectionName);

    if ((isCurrentAuth && isTargetMain) || (isCurrentMain && isTargetAuth)) {
        const newUrl = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
        window.location.href = newUrl;
    } else {
        showSection(sectionName, true); 
    }
}

async function showSection(sectionName, pushState = true) {
    const container = document.getElementById('section-container');
    const loaderParent = document.querySelector('.general-content'); 
    let loader; 

    if (!container || !loaderParent) {
        console.error('Loader parent or Section Container not found!');
        return;
    }

    try {
        const loaderHTML = `
            <div class="loader-wrapper" id="dynamic-loader" style="display: flex;">
                <div class="loader-spinner"></div>
            </div>`;
        loaderParent.insertAdjacentHTML('afterbegin', loaderHTML);
        loader = document.getElementById('dynamic-loader'); 


        const response = await fetch(`includes/sections/${sectionName}.php`);
        
        if (!response.ok) {
            throw new Error(`Error ${response.status}: No se pudo cargar la sección ${sectionName}.`);
        }

        const htmlContent = await response.text();

        container.innerHTML = htmlContent;

        if (pushState) {
            const newUrl = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
            history.pushState({ section: sectionName }, '', newUrl);
        }

    } catch (error) {
        console.error('Error al cargar la sección:', error);
        window.location.href = basePath; 
    } finally {
        if (loader) {
            loader.remove(); 
        }
    }
}