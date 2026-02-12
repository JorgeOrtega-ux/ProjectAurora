import { ApiService } from '../../core/services/api-service.js';
import { ToastManager } from '../../core/components/toast-manager.js';

let _container = null;
let _isLoading = false;
let _currentPage = 1;
let _limit = 20;
let _hasMore = true;

export const HomeController = {
    init: () => {
        _container = document.getElementById('home-feed-grid');
        if (!_container) return;

        console.log("HomeController: Inicializado");
        
        _currentPage = 1;
        _hasMore = true;
        _isLoading = false;
        
        loadFeed();
        
        // Infinite scroll simple (opcional)
        const scrollContainer = document.querySelector('.general-content-scrolleable');
        if(scrollContainer) {
            scrollContainer.addEventListener('scroll', () => {
                if (scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 100) {
                    if (_hasMore && !_isLoading) {
                        _currentPage++;
                        loadFeed(true);
                    }
                }
            });
        }
    }
};

async function loadFeed(append = false) {
    if (_isLoading) return;
    _isLoading = true;

    const loader = document.getElementById('home-feed-loading');
    const emptyState = document.getElementById('home-feed-empty');

    if (!append && loader) loader.classList.remove('d-none');
    if (!append && emptyState) emptyState.classList.add('d-none');

    const formData = new FormData();
    formData.append('page', _currentPage);
    formData.append('limit', _limit);

    try {
        const route = ApiService.Routes.Studio.GetPublicFeed || { route: 'studio.get_public_feed' };
        // Usamos postRaw para evitar problemas si la ruta no está definida en el enum estático antiguo
        const res = await ApiService.post(route, formData, { signal: window.PAGE_SIGNAL });

        if (loader) loader.classList.add('d-none');

        if (res.success) {
            if (!append) _container.innerHTML = '';
            
            if (res.videos.length === 0) {
                _hasMore = false;
                if (!append && emptyState) emptyState.classList.remove('d-none');
            } else {
                renderVideos(res.videos);
                if (res.videos.length < _limit) _hasMore = false;
            }
        } else {
            if (!append) ToastManager.show(res.message, 'error');
        }
    } catch (e) {
        if (e.isAborted) return;
        console.error(e);
        if (loader) loader.classList.add('d-none');
        if (!append) ToastManager.show('Error al cargar videos', 'error');
    } finally {
        _isLoading = false;
    }
}

function renderVideos(videos) {
    videos.forEach(v => {
        const card = document.createElement('div');
        card.className = 'video-card';
        card.dataset.uuid = v.uuid;
        
        // --- MODIFICACIÓN: Inyección de variable CSS para el color ---
        // Usamos el color dominante si existe, si no, un fallback (gris superficie)
        const hoverColor = v.dominant_color || 'var(--bg-surface-alt)';
        card.style.setProperty('--card-hover-color', hoverColor);
        // -------------------------------------------------------------
        
        // Thumbnail URL handling
        let thumbUrl = v.thumbnail_url ? window.BASE_PATH + v.thumbnail_url : '';
        let thumbHtml = thumbUrl 
            ? `<img src="${thumbUrl}" loading="lazy" alt="${v.title}">` 
            : `<div style="width:100%;height:100%;background:#111;display:flex;align-items:center;justify-content:center;"><span class="material-symbols-rounded" style="color:#333;font-size:32px;">movie</span></div>`;

        // Avatar handling
        let avatarUrl = v.author_avatar_url;
        // Si no es URL absoluta (http), añadir base path
        if (avatarUrl && !avatarUrl.startsWith('http')) {
            avatarUrl = window.BASE_PATH + avatarUrl;
        }

        const html = `
            <div class="video-top">
                ${thumbHtml}
                <div class="video-duration">${v.duration_formatted}</div>
            </div>
            <div class="video-bottom">
                <img src="${avatarUrl}" class="video-avatar" loading="lazy" alt="${v.username}">
                <div class="video-meta">
                    <h3 class="video-title" title="${v.title}">${v.title}</h3>
                    <div class="video-info">
                        <span class="video-author">${v.username}</span>
                        <div class="video-stats">
                            <span>${v.views_formatted}</span>
                            <span>${v.time_ago}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        card.innerHTML = html;
        
        // Click event placeholder (future: go to video player)
        card.addEventListener('click', () => {
            console.log("Go to video:", v.uuid);
            // navigateTo('watch/' + v.uuid); 
        });

        _container.appendChild(card);
    });
}