import { ToastManager } from '../../core/components/toast-manager.js';
import { ApiService } from '../../core/services/api-service.js';
import { ApiRoutes } from '../../core/services/api-routes.js';
import { I18nManager } from '../../core/utils/i18n-manager.js';
import { DialogManager } from '../../core/components/dialog-manager.js';

// ==========================================
// VARIABLES DE ESTADO (Módulo)
// ==========================================

// Variables del Reproductor
let _hls = null;
let _video = null;
let _controlsTimeout = null;
let _levels = []; 

// Variables para Scrubbing
let _spriteUrl = null;
let _vttUrl = null;
let _vttData = []; 
let _spriteImage = null; 

// Variables para Iluminación Cinematográfica
let _ambientCanvas = null;
let _ambientCtx = null;
let _isLightingEnabled = false;
let _animationFrameId = null;

// Variable para Modo Cine
let _isCinemaMode = false;

// Variables para Interacción (Likes, Subs, Views)
let _interactionState = {
    videoUuid: null,
    channelUuid: null,
    viewRegistered: false,
    isLoading: false
};

// ==========================================
// CONTROLADOR PRINCIPAL
// ==========================================

export const WatchController = {
    init: () => {
        const container = document.querySelector('[data-section="watch"]');
        if (!container) return;

        console.log("WatchController: Inicializado (Player + Interacciones)");

        _video = document.getElementById('main-player');
        const hlsSourceInput = document.getElementById('watch-hls-source');

        // --- 1. CONFIGURACIÓN DEL REPRODUCTOR (HLS, Cine, Ambient) ---
        
        // Inicializar Canvas Ambient
        _ambientCanvas = document.getElementById('ambient-canvas');
        if (_ambientCanvas) {
            _ambientCtx = _ambientCanvas.getContext('2d', { alpha: false }); 
        }

        // Preferencia de Iluminación
        const storedPref = localStorage.getItem('aurora_cinematic_mode');
        _isLightingEnabled = storedPref === 'on';

        // Inicializar Modo Cine
        const layout = document.querySelector('.component-watch-layout');
        const cinemaBtn = document.getElementById('cinema-mode-btn');
        const cinemaIcon = cinemaBtn ? cinemaBtn.querySelector('span') : null;

        if (layout && layout.classList.contains('component-watch-mode-cinema')) {
             _isCinemaMode = true;
             if(cinemaIcon) cinemaIcon.innerText = 'crop_free';
        } else {
            const storedCinema = localStorage.getItem('aurora_cinema_mode');
            if (storedCinema === 'on') {
                setCinemaMode(true);
            }
        }

        // Inputs para Scrubbing
        const spriteInput = document.getElementById('watch-sprite-source');
        const vttInput = document.getElementById('watch-vtt-source');

        if (_video && hlsSourceInput) {
            loadPlayer(_video, hlsSourceInput.value);
            initCustomControls(_video);

            if (spriteInput && vttInput && spriteInput.value && vttInput.value) {
                _spriteUrl = spriteInput.value;
                _vttUrl = vttInput.value;
                initScrubbing();
            }

            initAmbientLightLogic();
        }

        // --- 2. CONFIGURACIÓN DE INTERACCIONES (Likes, Subs, Views, Share) ---
        // Buscamos el contexto inyectado por PHP
        const metaContext = document.querySelector('.js-video-context');
        if (metaContext) {
            _interactionState.videoUuid = metaContext.dataset.videoUuid;
            _interactionState.channelUuid = metaContext.dataset.channelUuid;
            
            // Iniciar listeners
            initInteractionControls();
            initViewTracker();
        } else {
            console.warn("WatchController: No se encontraron metadatos de interacción (.js-video-context)");
        }
    },

    dispose: () => {
        if (_hls) {
            _hls.destroy();
            _hls = null;
        }
        if (_animationFrameId) {
            cancelAnimationFrame(_animationFrameId);
        }
        _video = null;
        _vttData = [];
        _spriteImage = null;
        _ambientCanvas = null;
        _ambientCtx = null;
        
        // Reset state
        _interactionState = {
            videoUuid: null,
            channelUuid: null,
            viewRegistered: false,
            isLoading: false
        };
    }
};

// ==========================================
// LÓGICA DE INTERACCIONES (Likes, Share, Subs)
// ==========================================

function initInteractionControls() {
    const btnLike = document.querySelector('.js-btn-like');
    const btnDislike = document.querySelector('.js-btn-dislike');
    const btnSubscribe = document.querySelector('.js-btn-subscribe');
    // [NUEVO] Botón compartir
    const btnShare = document.querySelector('.js-btn-share');

    if (btnLike) {
        btnLike.addEventListener('click', () => handleInteraction('like'));
    }
    if (btnDislike) {
        btnDislike.addEventListener('click', () => handleInteraction('dislike'));
    }
    if (btnSubscribe) {
        btnSubscribe.addEventListener('click', () => handleSubscribe());
    }
    if (btnShare) {
        btnShare.addEventListener('click', () => handleShare());
    }
}

// [NUEVO] Manejo de Compartir con Lógica Estandarizada
async function handleShare() {
    // 1. Construir URL del video
    const shareUrl = window.location.origin + '/ProjectAurora/watch?v=' + _interactionState.videoUuid;

    // 2. Abrir Diálogo
    DialogManager.confirm({
        title: 'Compartir Video',
        type: 'share', 
        url: shareUrl, 
        confirmText: 'Cerrar', 
        cancelText: null, 
        onReady: (modal) => {
            const btnCopy = modal.querySelector('#btn-copy-link');
            const input = modal.querySelector('#share-url-input');

            if (btnCopy && input) {
                btnCopy.onclick = () => {
                    input.select();
                    input.setSelectionRange(0, 99999); 

                    navigator.clipboard.writeText(shareUrl).then(() => {
                        ToastManager.show('Enlace copiado al portapapeles', 'success');
                        
                        const originalHtml = btnCopy.innerHTML;
                        btnCopy.innerHTML = '<span class="material-symbols-rounded" style="font-size: 18px; margin-right: 4px;">check</span> Copiado';
                        btnCopy.classList.add('success');
                        
                        setTimeout(() => {
                            btnCopy.innerHTML = originalHtml;
                            btnCopy.classList.remove('success');
                        }, 2000);
                    }).catch(err => {
                        console.error('Error al copiar: ', err);
                        ToastManager.show('No se pudo copiar el enlace', 'error');
                    });
                };
            }
        }
    });

    // 3. Registrar analítica (Usando ApiRoutes estandarizado)
    try {
        // [CORRECCIÓN] Usamos el objeto definido en api-routes.js, no un string suelto.
        ApiService.post(ApiRoutes.Interaction.Share, {
            video_uuid: _interactionState.videoUuid
        });
    } catch (e) {
        console.warn('Error registrando share:', e);
    }
}

async function handleInteraction(type) {
    if (_interactionState.isLoading) return;
    _interactionState.isLoading = true;

    try {
        const response = await ApiService.post(ApiRoutes.Interaction.ToggleLike, {
            video_uuid: _interactionState.videoUuid,
            type: type
        });

        if (response.success) {
            updateInteractionUI(response);
        } else if (response.require_login) {
            ToastManager.show(I18nManager.t('auth.login_required') || 'Inicia sesión para interactuar', 'info');
        } else {
            ToastManager.show(response.message || 'Error', 'error');
        }
    } catch (error) {
        console.error('Interaction error:', error);
    } finally {
        _interactionState.isLoading = false;
    }
}

function updateInteractionUI(data) {
    const { action, likes, dislikes, type } = data;
    
    const btnLike = document.querySelector('.js-btn-like');
    const btnDislike = document.querySelector('.js-btn-dislike');
    const countLike = document.querySelector('.js-count-like');
    const countDislike = document.querySelector('.js-count-dislike');

    // Resetear estados
    if (btnLike) btnLike.classList.remove('active');
    if (btnDislike) btnDislike.classList.remove('active');

    // Aplicar nuevo estado
    if (action !== 'removed') {
        if (type === 'like' && btnLike) btnLike.classList.add('active');
        if (type === 'dislike' && btnDislike) btnDislike.classList.add('active');
    }

    // Actualizar contadores
    if (countLike) countLike.textContent = formatNumber(likes);
    if (countDislike) countDislike.textContent = formatNumber(dislikes);
}

async function handleSubscribe() {
    if (_interactionState.isLoading) return;
    if (!_interactionState.channelUuid) return;

    _interactionState.isLoading = true;
    const btn = document.querySelector('.js-btn-subscribe');
    if (!btn) return;
    
    const originalText = btn.innerHTML;
    btn.classList.add('loading'); 

    try {
        const response = await ApiService.post(ApiRoutes.Interaction.ToggleSub, {
            channel_uuid: _interactionState.channelUuid
        });

        if (response.success) {
            const isSubscribed = response.subscribed;
            const countSubs = document.querySelector('.js-count-subs');

            if (isSubscribed) {
                btn.classList.add('subscribed');
                btn.textContent = I18nManager.t('app.subscribed') || 'Suscrito';
                ToastManager.show(I18nManager.t('app.sub_success') || 'Suscripción añadida', 'success');
            } else {
                btn.classList.remove('subscribed');
                btn.textContent = I18nManager.t('app.subscribe') || 'Suscribirse';
                ToastManager.show(I18nManager.t('app.unsub_success') || 'Suscripción eliminada', 'success');
            }

            if (countSubs) {
                countSubs.textContent = formatNumber(response.subscribers_count);
            }
        } else if (response.require_login) {
            ToastManager.show('Inicia sesión para suscribirte', 'info');
        } else {
            ToastManager.show(response.message, 'error');
        }
    } catch (error) {
        console.error('Subscribe error:', error);
        btn.innerHTML = originalText;
    } finally {
        _interactionState.isLoading = false;
        btn.classList.remove('loading');
    }
}

function initViewTracker() {
    if (!_video) return;

    _video.addEventListener('timeupdate', () => {
        if (_interactionState.viewRegistered) return;

        if (!_video.paused && !_video.seeking) {
            // Contar visita después de 5 segundos de reproducción real
            if (_video.currentTime > 5) {
                registerView();
            }
        }
    });
}

async function registerView() {
    if (_interactionState.viewRegistered) return;
    _interactionState.viewRegistered = true;

    try {
        const response = await ApiService.post(ApiRoutes.Interaction.RegisterView, {
            video_uuid: _interactionState.videoUuid
        });
        
        if (response.success && response.status === 'registered') {
            // console.log(`View registered. Total: ${response.views}`);
        }
    } catch (e) {
        console.warn('View registration failed', e);
    }
}

// Helper para formato de números (1.2K, 1M)
function formatNumber(num) {
    if (num === null || num === undefined) return '0';
    num = parseInt(num);
    if (isNaN(num)) return '0';

    if (num >= 1000000) {
        return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
    }
    return num.toString();
}

// ==========================================
// LÓGICA DE REPRODUCTOR (HLS)
// ==========================================

function loadPlayer(video, source) {
    if (Hls.isSupported()) {
        if (_hls) {
            _hls.destroy();
        }

        _hls = new Hls({
            capLevelToPlayerSize: true, 
            autoStartLoad: true
        });

        _hls.loadSource(source);
        _hls.attachMedia(video);

        _hls.on(Hls.Events.MANIFEST_PARSED, (event, data) => {
            console.log(`HLS Manifest cargado. ${data.levels.length} niveles encontrados.`);
            _levels = data.levels;
            renderQualityOptions(_levels);
            
            video.play().catch(e => console.log("Autoplay bloqueado por navegador:", e));
            updatePlayPauseIcon(false);
        });

        _hls.on(Hls.Events.ERROR, (event, data) => {
            if (data.fatal) {
                switch (data.type) {
                    case Hls.ErrorTypes.NETWORK_ERROR:
                        console.warn("HLS Network Error, intentando recuperar...");
                        _hls.startLoad();
                        break;
                    case Hls.ErrorTypes.MEDIA_ERROR:
                        console.warn("HLS Media Error, intentando recuperar...");
                        _hls.recoverMediaError();
                        break;
                    default:
                        console.error("HLS Error Fatal:", data);
                        _hls.destroy();
                        ToastManager.show('Error crítico de reproducción.', 'error');
                        break;
                }
            }
        });

        _hls.on(Hls.Events.LEVEL_SWITCHED, (event, data) => {
             // const level = _levels[data.level];
             // Auto-level debug logic here
        });

    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        // Safari Nativo
        video.src = source;
        video.addEventListener('loadedmetadata', () => {
            video.play();
            updatePlayPauseIcon(false);
            document.getElementById('quality-status-text').innerText = 'Auto (Nativo)';
        });
    } else {
        ToastManager.show('Tu navegador no soporta reproducción HLS.', 'error');
    }
}

// ==========================================
// LÓGICA DE SCRUBBING
// ==========================================

async function initScrubbing() {
    _spriteImage = new Image();
    _spriteImage.src = _spriteUrl;
    
    try {
        const response = await fetch(_vttUrl);
        if (!response.ok) throw new Error("Error cargando VTT");
        const text = await response.text();
        _vttData = parseVTT(text);
        console.log(`Scrubbing listo: ${_vttData.length} frames indexados.`);
    } catch (e) {
        console.warn("Fallo al inicializar scrubbing:", e);
    }
}

function parseVTT(vttText) {
    const lines = vttText.split('\n');
    const data = [];
    let currentStart = null;
    let currentEnd = null;

    const timeRegex = /(\d{2}:\d{2}:\d{2}\.\d{3}) --> (\d{2}:\d{2}:\d{2}\.\d{3})/;
    const coordsRegex = /#xywh=(\d+),(\d+),(\d+),(\d+)/;

    for (let line of lines) {
        line = line.trim();
        if (!line) continue;
        if (line.startsWith('WEBVTT')) continue;

        const timeMatch = timeRegex.exec(line);
        if (timeMatch) {
            currentStart = parseTime(timeMatch[1]);
            currentEnd = parseTime(timeMatch[2]);
            continue;
        }

        if (currentStart !== null) {
            const coordsMatch = coordsRegex.exec(line);
            if (coordsMatch) {
                data.push({
                    start: currentStart,
                    end: currentEnd,
                    x: parseInt(coordsMatch[1]),
                    y: parseInt(coordsMatch[2]),
                    w: parseInt(coordsMatch[3]),
                    h: parseInt(coordsMatch[4])
                });
                currentStart = null; 
            }
        }
    }
    return data;
}

function parseTime(timeStr) {
    const parts = timeStr.split(':');
    const h = parseInt(parts[0]);
    const m = parseInt(parts[1]);
    const s = parseFloat(parts[2]);
    return (h * 3600) + (m * 60) + s;
}

// ==========================================
// LÓGICA DE MODO CINE
// ==========================================

function setCinemaMode(enable) {
    _isCinemaMode = enable;
    
    document.cookie = `aurora_cinema_mode=${enable ? 'on' : 'off'}; path=/; max-age=31536000`; 
    localStorage.setItem('aurora_cinema_mode', enable ? 'on' : 'off');

    const layout = document.querySelector('.component-watch-layout');
    const btn = document.getElementById('cinema-mode-btn');
    const icon = btn ? btn.querySelector('span') : null;

    if (enable) {
        layout.classList.add('component-watch-mode-cinema');
        if(icon) icon.innerText = 'crop_free';
    } else {
        layout.classList.remove('component-watch-mode-cinema');
        if(icon) icon.innerText = 'crop_landscape';
    }

    if (_isLightingEnabled) {
        drawAmbientFrame();
    }
}

// ==========================================
// LÓGICA DE ILUMINACIÓN
// ==========================================

function initAmbientLightLogic() {
    if (!_ambientCanvas || !_video) return;

    _ambientCanvas.width = 100;
    _ambientCanvas.height = 56; 

    updateLightingUI(_isLightingEnabled);

    _video.addEventListener('play', () => {
        if (_isLightingEnabled) {
            _ambientCanvas.style.opacity = '1';
            startAmbientLoop();
        }
    });

    _video.addEventListener('pause', () => {
        stopAmbientLoop();
        if (_isLightingEnabled) _ambientCanvas.style.opacity = '1';
    });

    _video.addEventListener('ended', () => {
        stopAmbientLoop();
        _ambientCanvas.style.opacity = '0';
    });

    _video.addEventListener('seeked', () => {
        if (_isLightingEnabled && _video.paused) {
            drawAmbientFrame();
        }
    });

    const lightingOptions = document.querySelectorAll('#settings-lighting .component-watch-settings-option');
    lightingOptions.forEach(opt => {
        opt.addEventListener('click', (e) => {
            const val = opt.dataset.value; 
            const isEnabled = val === 'on';
            
            setLightingState(isEnabled);
            
            lightingOptions.forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
            
            document.getElementById('lighting-status-text').innerText = isEnabled ? 'Activo' : 'Desactivado';
            
            document.querySelectorAll('.component-watch-settings-panel').forEach(p => p.classList.remove('active'));
            document.getElementById('settings-main').classList.add('active');
        });
    });
}

function setLightingState(enabled) {
    _isLightingEnabled = enabled;
    localStorage.setItem('aurora_cinematic_mode', enabled ? 'on' : 'off');
    
    if (enabled) {
        _ambientCanvas.style.display = 'block';
        requestAnimationFrame(() => {
            _ambientCanvas.style.opacity = _video.paused ? '0.5' : '1';
        });
        if (!_video.paused) {
            startAmbientLoop();
        } else {
            drawAmbientFrame(); 
        }
    } else {
        _ambientCanvas.style.opacity = '0';
        stopAmbientLoop();
    }
}

function updateLightingUI(isEnabled) {
    const textStatus = document.getElementById('lighting-status-text');
    if (textStatus) {
        textStatus.innerText = isEnabled ? 'Activo' : 'Desactivado';
    }

    const options = document.querySelectorAll('#settings-lighting .component-watch-settings-option');
    options.forEach(opt => {
        if ((opt.dataset.value === 'on' && isEnabled) || (opt.dataset.value === 'off' && !isEnabled)) {
            opt.classList.add('selected');
        } else {
            opt.classList.remove('selected');
        }
    });
}

function startAmbientLoop() {
    if (_animationFrameId) cancelAnimationFrame(_animationFrameId);
    
    const loop = () => {
        if (!_video.paused && !_video.ended && _isLightingEnabled) {
            drawAmbientFrame();
            _animationFrameId = requestAnimationFrame(loop);
        }
    };
    _animationFrameId = requestAnimationFrame(loop);
}

function stopAmbientLoop() {
    if (_animationFrameId) {
        cancelAnimationFrame(_animationFrameId);
        _animationFrameId = null;
    }
}

function drawAmbientFrame() {
    if (!_ambientCtx || !_video) return;
    _ambientCtx.drawImage(_video, 0, 0, _ambientCanvas.width, _ambientCanvas.height);
}

// ==========================================
// CONTROLES UI
// ==========================================

function initCustomControls(video) {
    const playPauseBtn = document.getElementById('play-pause-btn');
    const muteBtn = document.getElementById('mute-btn');
    const volumeBar = document.getElementById('volume-bar');
    const seekBar = document.getElementById('seek-bar');
    const progressContainer = document.querySelector('.component-watch-progress-container');
    const currentTimeEl = document.getElementById('current-time');
    const durationEl = document.getElementById('duration');
    const fullscreenBtn = document.getElementById('fullscreen-btn');
    const settingsBtn = document.getElementById('settings-btn');
    const cinemaBtn = document.getElementById('cinema-mode-btn');
    
    const controlsContainer = document.getElementById('custom-controls');
    const videoContainer = document.getElementById('video-container');
    const settingsPopover = document.getElementById('settings-popover');

    const tooltip = document.getElementById('scrub-tooltip');
    const tooltipImg = tooltip ? tooltip.querySelector('.component-watch-scrub-preview') : null;
    const tooltipTime = tooltip ? tooltip.querySelector('.component-watch-scrub-time') : null;

    // 1. Play / Pause
    const togglePlay = () => {
        if (video.paused || video.ended) {
            video.play();
        } else {
            video.pause();
        }
    };

    playPauseBtn.addEventListener('click', togglePlay);
    video.addEventListener('click', (e) => {
        if (e.target.closest('.component-watch-settings-popover')) return; 
        togglePlay();
    });

    video.addEventListener('play', () => updatePlayPauseIcon(false));
    video.addEventListener('pause', () => updatePlayPauseIcon(true));

    // 2. Barra de Progreso
    video.addEventListener('loadedmetadata', () => {
        seekBar.max = video.duration;
        durationEl.innerText = formatTime(video.duration);
    });

    video.addEventListener('timeupdate', () => {
        if (!video.paused) {
            seekBar.value = video.currentTime;
            currentTimeEl.innerText = formatTime(video.currentTime);
            updateSeekBarBackground(seekBar);
        }
    });

    seekBar.addEventListener('input', () => {
        video.currentTime = seekBar.value;
        updateSeekBarBackground(seekBar);
        currentTimeEl.innerText = formatTime(seekBar.value);
    });

    // 3. Scrubbing UI
    if (progressContainer && tooltip && _vttData) {
        progressContainer.addEventListener('mousemove', (e) => {
            if (!_vttData.length) return;

            const rectBar = progressContainer.getBoundingClientRect();
            const offsetXBar = e.clientX - rectBar.left;
            let percent = offsetXBar / rectBar.width;
            
            if (percent < 0) percent = 0;
            if (percent > 1) percent = 1;

            const hoverTime = percent * video.duration;

            if (tooltipTime) {
                tooltipTime.innerText = formatTime(hoverTime);
            }

            const frame = _vttData.find(f => hoverTime >= f.start && hoverTime < f.end);
            
            if (frame && tooltipImg) {
                tooltip.style.display = 'flex';
                
                const tooltipParent = tooltip.offsetParent || videoContainer; 
                
                if (tooltipParent) {
                    const parentRect = tooltipParent.getBoundingClientRect();
                    let relativeX = e.clientX - parentRect.left;

                    const halfTooltip = 80; 
                    const maxLimit = parentRect.width - halfTooltip;

                    if (relativeX < halfTooltip) relativeX = halfTooltip;
                    if (relativeX > maxLimit) relativeX = maxLimit;

                    tooltip.style.left = `${relativeX}px`;
                }
                
                tooltipImg.style.backgroundImage = `url('${_spriteUrl}')`;
                tooltipImg.style.backgroundPosition = `-${frame.x}px -${frame.y}px`;
                tooltipImg.style.width = `${frame.w}px`;
                tooltipImg.style.height = `${frame.h}px`;
            }
        });

        progressContainer.addEventListener('mouseleave', () => {
            if (tooltip) tooltip.style.display = 'none';
        });
    }

    // 4. Volumen
    volumeBar.addEventListener('input', (e) => {
        video.volume = e.target.value;
        video.muted = e.target.value === 0;
        updateVolumeIcon(video.volume);
    });

    muteBtn.addEventListener('click', () => {
        video.muted = !video.muted;
        if (video.muted) {
            volumeBar.value = 0;
            updateVolumeIcon(0);
        } else {
            video.volume = 1;
            volumeBar.value = 1;
            updateVolumeIcon(1);
        }
    });

    // 5. Pantalla Completa
    fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
            if (videoContainer.requestFullscreen) {
                videoContainer.requestFullscreen();
            } else if (videoContainer.webkitRequestFullscreen) {
                videoContainer.webkitRequestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    });

    // 6. Configuración
    settingsBtn.addEventListener('click', (e) => {
        e.stopPropagation(); 
        toggleSettingsMenu();
    });

    document.addEventListener('click', (e) => {
        if (!settingsPopover.contains(e.target) && !settingsBtn.contains(e.target)) {
            settingsPopover.classList.remove('active');
            resetSettingsMenu();
        }
    });

    initSettingsNavigationDelegated();

    if (cinemaBtn) {
        cinemaBtn.addEventListener('click', () => {
            setCinemaMode(!_isCinemaMode);
        });
    }

    // 7. Auto-ocultar controles
    const showControls = () => {
        controlsContainer.classList.add('show');
        videoContainer.style.cursor = 'default';
        clearTimeout(_controlsTimeout);
        
        if (!video.paused) {
            _controlsTimeout = setTimeout(() => {
                if (!settingsPopover.classList.contains('active')) {
                    controlsContainer.classList.remove('show');
                    videoContainer.style.cursor = 'none';
                }
            }, 3000);
        }
    };

    videoContainer.addEventListener('mousemove', showControls);
    videoContainer.addEventListener('mouseleave', () => {
        if (!video.paused && !settingsPopover.classList.contains('active')) {
            controlsContainer.classList.remove('show');
        }
    });
}

function toggleSettingsMenu() {
    const popover = document.getElementById('settings-popover');
    if (popover.classList.contains('active')) {
        popover.classList.remove('active');
        setTimeout(resetSettingsMenu, 200); 
    } else {
        popover.classList.add('active');
        resetSettingsMenu(); 
    }
}

function resetSettingsMenu() {
    document.querySelectorAll('.component-watch-settings-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('settings-main').classList.add('active');
}

function initSettingsNavigationDelegated() {
    const popover = document.getElementById('settings-popover');
    if(!popover) return;

    popover.addEventListener('click', (e) => {
        const item = e.target.closest('.component-watch-settings-item');
        if (item) {
            const targetId = item.getAttribute('data-target');
            const targetPanel = document.getElementById(`settings-${targetId}`);
            if (targetPanel) {
                document.getElementById('settings-main').classList.remove('active');
                targetPanel.classList.add('active');
            }
            return;
        }

        const header = e.target.closest('.component-watch-settings-header');
        if (header) {
            const backTarget = header.getAttribute('data-back'); 
            header.closest('.component-watch-settings-panel').classList.remove('active');
            document.getElementById(`settings-${backTarget}`).classList.add('active');
            return;
        }
    });
}

function renderQualityOptions(levels) {
    const container = document.getElementById('quality-options-container');
    container.innerHTML = '';

    const isAutoEnabled = _hls ? _hls.autoLevelEnabled : true;
    
    const autoOption = createQualityOption('-1', 'Automática', isAutoEnabled);
    autoOption.addEventListener('click', () => handleQualityChange(-1, 'Automática', autoOption));
    container.appendChild(autoOption);

    [...levels].reverse().forEach((level) => {
        const originalIndex = levels.indexOf(level); 
        const height = level.height;
        
        let label = `${height}p`;
        if (height >= 2160) label += ' 4K';
        else if (height >= 1440) label += ' 2K';
        else if (height >= 1080) label += ' FHD';
        else if (height >= 720) label += ' HD';

        const isSelected = !isAutoEnabled && (_hls && _hls.currentLevel === originalIndex);
        
        const option = createQualityOption(originalIndex, label, isSelected);
        option.addEventListener('click', () => handleQualityChange(originalIndex, label, option));
        container.appendChild(option);
    });
}

function createQualityOption(val, text, isSelected) {
    const div = document.createElement('div');
    div.className = `component-watch-settings-option ${isSelected ? 'selected' : ''}`;
    div.setAttribute('data-quality', val);
    div.innerHTML = `<span>${text}</span><span class="material-symbols-rounded check-icon">check</span>`;
    return div;
}

function handleQualityChange(levelIndex, label, element) {
    if (_hls) {
        _hls.currentLevel = levelIndex; 
    }

    const allOpts = document.querySelectorAll('#quality-options-container .component-watch-settings-option');
    allOpts.forEach(o => o.classList.remove('selected'));
    element.classList.add('selected');

    const statusText = levelIndex === -1 ? 'Auto' : label;
    document.getElementById('quality-status-text').innerText = statusText;

    resetSettingsMenu();
    toggleSettingsMenu(); 
}

function updatePlayPauseIcon(isPaused) {
    const btn = document.getElementById('play-pause-btn');
    if(!btn) return;
    const icon = btn.querySelector('span');
    if (isPaused) {
        icon.innerText = 'play_arrow';
    } else {
        icon.innerText = 'pause';
    }
}

function updateVolumeIcon(vol) {
    const btn = document.getElementById('mute-btn');
    if(!btn) return;
    const icon = btn.querySelector('span');
    
    if (vol === 0) {
        icon.innerText = 'volume_off';
    } else if (vol < 0.5) {
        icon.innerText = 'volume_down';
    } else {
        icon.innerText = 'volume_up';
    }
}

function formatTime(time) {
    if (!time || isNaN(time)) return "0:00";
    const minutes = Math.floor(time / 60);
    const seconds = Math.floor(time % 60);
    return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
}

function updateSeekBarBackground(input) {
    const min = input.min || 0;
    const max = input.max || 100;
    const val = input.value;
    const percentage = ((val - min) / (max - min)) * 100;
    input.style.backgroundSize = `${percentage}% 100%`;
}