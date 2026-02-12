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

// =========================================================
// 1. PALETA PROFESIONAL (5 Tonos por color)
// =========================================================
// Basado en escala 300-700 para cubrir iluminaciones variadas
const EXTENDED_PALETTE = [
    // Slate (Neutros)
    { hex: '#cbd5e1' }, { hex: '#94a3b8' }, { hex: '#64748b' }, { hex: '#475569' }, { hex: '#334155' },
    // Red
    { hex: '#fca5a5' }, { hex: '#f87171' }, { hex: '#ef4444' }, { hex: '#dc2626' }, { hex: '#b91c1c' },
    // Orange
    { hex: '#fdba74' }, { hex: '#fb923c' }, { hex: '#f97316' }, { hex: '#ea580c' }, { hex: '#c2410c' },
    // Amber
    { hex: '#fcd34d' }, { hex: '#fbbf24' }, { hex: '#f59e0b' }, { hex: '#d97706' }, { hex: '#b45309' },
    // Green
    { hex: '#86efac' }, { hex: '#4ade80' }, { hex: '#22c55e' }, { hex: '#16a34a' }, { hex: '#15803d' },
    // Emerald
    { hex: '#6ee7b7' }, { hex: '#34d399' }, { hex: '#10b981' }, { hex: '#059669' }, { hex: '#047857' },
    // Teal
    { hex: '#5eead4' }, { hex: '#2dd4bf' }, { hex: '#14b8a6' }, { hex: '#0d9488' }, { hex: '#0f766e' },
    // Cyan
    { hex: '#67e8f9' }, { hex: '#22d3ee' }, { hex: '#06b6d4' }, { hex: '#0891b2' }, { hex: '#0e7490' },
    // Blue
    { hex: '#93c5fd' }, { hex: '#60a5fa' }, { hex: '#3b82f6' }, { hex: '#2563eb' }, { hex: '#1d4ed8' },
    // Indigo
    { hex: '#a5b4fc' }, { hex: '#818cf8' }, { hex: '#6366f1' }, { hex: '#4f46e5' }, { hex: '#4338ca' },
    // Violet
    { hex: '#c4b5fd' }, { hex: '#a78bfa' }, { hex: '#8b5cf6' }, { hex: '#7c3aed' }, { hex: '#6d28d9' },
    // Purple
    { hex: '#d8b4fe' }, { hex: '#c084fc' }, { hex: '#a855f7' }, { hex: '#9333ea' }, { hex: '#7e22ce' },
    // Fuchsia
    { hex: '#f0abfc' }, { hex: '#e879f9' }, { hex: '#d946ef' }, { hex: '#c026d3' }, { hex: '#a21caf' },
    // Pink
    { hex: '#f9a8d4' }, { hex: '#f472b6' }, { hex: '#ec4899' }, { hex: '#db2777' }, { hex: '#be185d' },
    // Rose
    { hex: '#fda4af' }, { hex: '#fb7185' }, { hex: '#f43f5e' }, { hex: '#e11d48' }, { hex: '#be123c' }
];

// Algoritmo de distancia de color (Euclidiano RGB)
function getNearestSafeColor(rawHex) {
    if (!rawHex || rawHex === '#000000') return '#202020'; // Fallback

    // Convertir Hex a RGB
    let r = 0, g = 0, b = 0;
    if (rawHex.length === 4) {
        r = parseInt("0x" + rawHex[1] + rawHex[1]);
        g = parseInt("0x" + rawHex[2] + rawHex[2]);
        b = parseInt("0x" + rawHex[3] + rawHex[3]);
    } else if (rawHex.length === 7) {
        r = parseInt("0x" + rawHex[1] + rawHex[2]);
        g = parseInt("0x" + rawHex[3] + rawHex[4]);
        b = parseInt("0x" + rawHex[5] + rawHex[6]);
    }

    let minDistance = Infinity;
    let closestHex = EXTENDED_PALETTE[2].hex; // Default (un tono medio)

    // Buscar el color de la paleta con menor distancia matemática
    for (const color of EXTENDED_PALETTE) {
        const targetR = parseInt(color.hex.substring(1, 3), 16);
        const targetG = parseInt(color.hex.substring(3, 5), 16);
        const targetB = parseInt(color.hex.substring(5, 7), 16);

        // Distancia euclidiana (sin raíz cuadrada para optimizar)
        const distance = Math.pow(targetR - r, 2) + 
                         Math.pow(targetG - g, 2) + 
                         Math.pow(targetB - b, 2);

        if (distance < minDistance) {
            minDistance = distance;
            closestHex = color.hex;
        }
    }
    return closestHex;
}

export const HomeController = {
    init: () => {
        _container = document.getElementById('home-feed-grid');
        if (!_container) return;

        console.log("HomeController: Inicializado (Pro Color Mode)");
        
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
    if (_hoverTimeout) clearTimeout(_hoverTimeout);
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
    if (_activeCard && _activeCard !== card) {
        stopVideoPreview(_activeCard);
    }

    const hlsUrl = card.dataset.hls;
    if (!hlsUrl) return;

    const topContainer = card.querySelector('.video-top');
    if (!topContainer) return;

    const video = document.createElement('video');
    video.className = 'video-preview active';
    video.muted = true;
    video.autoplay = true;
    video.playsInline = true;
    video.style.opacity = '0';

    video.addEventListener('ended', () => {
        stopVideoPreview(card);
    });

    topContainer.appendChild(video);
    _activeVideoElement = video;
    _activeCard = card;

    if (Hls.isSupported()) {
        const hls = new Hls();
        hls.loadSource(window.BASE_PATH + hlsUrl);
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, () => {
            const playPromise = video.play();
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    video.style.opacity = '1';
                    startCountdownTimer(card, video);
                }).catch(error => {
                    stopVideoPreview(card);
                });
            }
        });
        hls.on(Hls.Events.ERROR, function (event, data) {
            if (data.fatal) {
                stopVideoPreview(card);
            }
        });
        _activeHls = hls;
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = window.BASE_PATH + hlsUrl;
        video.addEventListener('loadedmetadata', () => {
            video.play();
            video.style.opacity = '1';
            startCountdownTimer(card, video);
        });
    }
}

function stopVideoPreview(card) {
    if (_previewInterval) {
        clearInterval(_previewInterval);
        _previewInterval = null;
    }

    const badge = card.querySelector('.video-duration');
    if (badge && card.dataset.durationFormatted) {
        badge.textContent = card.dataset.durationFormatted;
    }

    if (_activeHls) {
        _activeHls.destroy();
        _activeHls = null;
    }

    if (_activeVideoElement) {
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

    video.addEventListener('timeupdate', () => {
        const remaining = Math.max(0, totalDuration - video.currentTime);
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
        
        // DATA ATTRIBUTES
        card.dataset.hls = v.hls_path || '';
        card.dataset.duration = v.duration || 0;
        card.dataset.durationFormatted = v.duration_formatted;
        
        // [NUEVO] LÓGICA DE COLOR SNAPPING
        // Toma el color crudo, busca el más cercano en la paleta profesional y lo asigna.
        const rawColor = v.dominant_color || '#202020';
        const unifiedColor = getNearestSafeColor(rawColor);
        card.style.setProperty('--dynamic-base', unifiedColor);
        
        // Miniatura
        let thumbUrl = v.thumbnail_url ? window.BASE_PATH + v.thumbnail_url : '';
        let thumbHtml = thumbUrl 
            ? `<img src="${thumbUrl}" loading="lazy" alt="${v.title}" class="video-thumb-img">` 
            : `<div style="width:100%;height:100%;background:#111;display:flex;align-items:center;justify-content:center;"><span class="material-symbols-rounded" style="color:#333;font-size:32px;">movie</span></div>`;

        // Avatar
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
            // Navegación futura
            // navigateTo('watch/' + v.uuid); 
        });

        _container.appendChild(card);
    });
}