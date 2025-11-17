const allowedSections = ['main', 'login', 'register', 'explorer'];
const authZone = ['login', 'register'];
const basePath = window.BASE_PATH || '/ProjectAurora/';

export function initUrlManager() {
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.section) {
            showSection(event.state.section, false);
        } else {
            window.location.reload();
        }
    });

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


function navigateTo(sectionName) {
    const currentSectionName = getSectionFromUrl();
    const isCurrentAuth = authZone.includes(currentSectionName);
    const isTargetAuth = authZone.includes(sectionName);

    if ((isCurrentAuth && !isTargetAuth) || (!isCurrentAuth && isTargetAuth)) {
        window.location.href = (sectionName === 'main') ? basePath : `${basePath}${sectionName}`;
    } else {
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
    const loaderParent = document.querySelector('.general-content-bottom');
    if (!container || !loaderParent) return;

    document.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
    const activeLink = document.querySelector(`.menu-link[data-nav="${sectionName}"]`);
    if (activeLink) activeLink.classList.add('active');

    let loader = document.getElementById('dynamic-loader');
    if (!loader) {
        loaderParent.insertAdjacentHTML('afterbegin',
            `<div class="loader-wrapper" id="dynamic-loader" style="display: flex;"><div class="loader-spinner"></div></div>`
        );
        loader = document.getElementById('dynamic-loader');
    }

    try {
        const response = await fetch(`${basePath}includes/sections/${sectionName}.php?t=${Date.now()}`, {
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
        window.location.href = basePath;
    } finally {
        if (loader) loader.remove();
    }
}