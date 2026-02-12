import { ApiService } from '../../core/services/api-service.js';
import { ToastManager } from '../../core/components/toast-manager.js';

let _container = null;
let _isLoading = false;
let _currentPage = 1;
let _limit = 20;
let _hasMore = true;

// Variables para el control de preview
let _hoverTimeout = null;
let _activeHls = null;
let _activeVideoElement = null;
let _activeCard = null;
let _previewInterval = null;

export const HomeController = {
    init: () => {
        _container = document.getElementById('home-feed-grid');
        if (!_container) return;

        console.log("HomeController: Inicializado");
        
        _currentPage = 1;
        _hasMore = true;
        _isLoading = false;
        
        loadFeed();
        
        // Infinite scroll simple
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
        
        initPreviewEvents();
    }
};

function initPreviewEvents() {
    // Delegación de eventos para mouseenter y mouseleave en las tarjetas
    _container.addEventListener('mouseenter', (e) => {
        const card = e.target.closest('.video-card');
        if (card) handleCardHover(card);
    }, true);

    _container.addEventListener('mouseleave', (e) => {
        const card = e.target.closest('.video-card');
        if (card) handleCardLeave(card);
    }, true);
}

function handleCardHover(card) {
    // Limpiar timeout anterior si existe
    if (_hoverTimeout) clearTimeout(_hoverTimeout);

    // Debounce: Esperar 600ms antes de iniciar la reproducción
    _hoverTimeout = setTimeout(() => {
        startVideoPreview(card);
    }, 600);
}

function handleCardLeave(card) {
    if (_hoverTimeout) {
        clearTimeout(_hoverTimeout);
        _hoverTimeout = null;
    }
    stopVideoPreview(card);
}

function startVideoPreview(card) {
    // Si ya hay otro video activo, detenerlo
    if (_activeCard && _activeCard !== card) {
        stopVideoPreview(_activeCard);
    }

    const hlsUrl = card.dataset.hls;
    if (!hlsUrl) return;

    // Contenedor superior donde se inyectará el video
    const topContainer = card.querySelector('.video-top');
    if (!topContainer) return;

    // Crear elemento video dinámicamente
    const video = document.createElement('video');
    video.className = 'video-preview active';
    video.muted = true;
    video.autoplay = true;
    video.playsInline = true;
    video.style.opacity = '0'; // Inicio invisible para evitar parpadeo

    // [NUEVO] DETECTAR CUANDO EL VIDEO TERMINA
    // Esto hace que al acabar el video, se cierre el preview y vuelva la miniatura
    // aunque el mouse siga encima.
    video.addEventListener('ended', () => {
        stopVideoPreview(card);
    });

    topContainer.appendChild(video);
    _activeVideoElement = video;
    _activeCard = card;

    // Iniciar Hls.js
    if (Hls.isSupported()) {
        const hls = new Hls();
        hls.loadSource(window.BASE_PATH + hlsUrl);
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, () => {
            const playPromise = video.play();
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    video.style.opacity = '1'; // Mostrar cuando empiece a reproducir
                    startCountdownTimer(card, video);
                }).catch(error => {
                    console.log("Autoplay prevent handled");
                    stopVideoPreview(card); // Si falla el autoplay, limpiamos
                });
            }
        });
        
        // Manejo de errores HLS
        hls.on(Hls.Events.ERROR, function (event, data) {
            if (data.fatal) {
                stopVideoPreview(card);
            }
        });

        _activeHls = hls;
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        // Soporte nativo (Safari)
        video.src = window.BASE_PATH + hlsUrl;
        video.addEventListener('loadedmetadata', () => {
            video.play();
            video.style.opacity = '1';
            startCountdownTimer(card, video);
        });
    }
}

function stopVideoPreview(card) {
    // Detener intervalo de cuenta regresiva (si hubiera uno basado en interval)
    if (_previewInterval) {
        clearInterval(_previewInterval);
        _previewInterval = null;
    }

    // Resetear timer visual al original guardado en dataset
    const badge = card.querySelector('.video-duration');
    if (badge && card.dataset.durationFormatted) {
        badge.textContent = card.dataset.durationFormatted;
    }

    // Destruir HLS
    if (_activeHls) {
        _activeHls.destroy();
        _activeHls = null;
    }

    // Remover elemento video
    if (_activeVideoElement) {
        // Pausar y vaciar src para liberar memoria antes de remover
        _activeVideoElement.pause();
        _activeVideoElement.removeAttribute('src');
        _activeVideoElement.load();
        _activeVideoElement.remove();
        _activeVideoElement = null;
    }

    _activeCard = null;
}

function startCountdownTimer(card, video) {
    const badge = card.querySelector('.video-duration');
    const totalDuration = parseFloat(card.dataset.duration) || 0;

    if (!badge || totalDuration <= 0) return;

    // Actualizar cada vez que el tiempo del video cambia
    video.addEventListener('timeupdate', () => {
        // Calcular restante
        const remaining = Math.max(0, totalDuration - video.currentTime);
        
        // Si el remaining es muy cercano a 0 (ej. 0.1s), forzamos 00:00 para estética
        if (remaining < 0.5) {
             badge.textContent = "00:00";
             return;
        }

        const minutes = Math.floor(remaining / 60);
        const seconds = Math.floor(remaining % 60);
        const formatted = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        
        badge.textContent = formatted;
    });
}

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
        
        // --- DATA ATTRIBUTES PARA PREVIEW ---
        card.dataset.hls = v.hls_path || '';
        card.dataset.duration = v.duration || 0;
        card.dataset.durationFormatted = v.duration_formatted; // Guardar texto original para reset
        
        const hoverColor = v.dominant_color || 'var(--bg-surface-alt)';
        card.style.setProperty('--card-hover-color', hoverColor);
        
        // Thumbnail URL handling
        let thumbUrl = v.thumbnail_url ? window.BASE_PATH + v.thumbnail_url : '';
        let thumbHtml = thumbUrl 
            ? `<img src="${thumbUrl}" loading="lazy" alt="${v.title}" class="video-thumb-img">` 
            : `<div style="width:100%;height:100%;background:#111;display:flex;align-items:center;justify-content:center;"><span class="material-symbols-rounded" style="color:#333;font-size:32px;">movie</span></div>`;

        // Avatar handling
        let avatarUrl = v.author_avatar_url;
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
        
        card.addEventListener('click', () => {
            console.log("Go to video:", v.uuid);
            // navigateTo('watch/' + v.uuid); 
        });

        _container.appendChild(card);
    });
}