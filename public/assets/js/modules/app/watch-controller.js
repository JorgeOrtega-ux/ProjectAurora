import { ToastManager } from '../../core/components/toast-manager.js';

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

export const WatchController = {
    init: () => {
        const container = document.querySelector('[data-section="watch"]');
        if (!container) return;

        console.log("WatchController: Inicializado (Multi-Bitrate + Scrubbing + Ambient + Cinema)");

        _video = document.getElementById('main-player');
        const hlsSourceInput = document.getElementById('watch-hls-source');

        // Inicializar Canvas
        _ambientCanvas = document.getElementById('ambient-canvas');
        if (_ambientCanvas) {
            _ambientCtx = _ambientCanvas.getContext('2d', { alpha: false }); // Optimización: sin canal alpha
        }

        // Leer preferencia de usuario (Iluminación)
        const storedPref = localStorage.getItem('aurora_cinematic_mode');
        _isLightingEnabled = storedPref === 'on';

        // [CORRECCIÓN] Inicializar Modo Cine (Verificando primero si PHP ya lo renderizó activo)
        const layout = document.querySelector('.component-watch-layout');
        const cinemaBtn = document.getElementById('cinema-mode-btn');
        const cinemaIcon = cinemaBtn ? cinemaBtn.querySelector('span') : null;

        if (layout && layout.classList.contains('component-watch-mode-cinema')) {
             _isCinemaMode = true;
             // Solo actualizamos el icono porque la clase ya está puesta
             if(cinemaIcon) cinemaIcon.innerText = 'crop_free';
        } else {
            // Fallback: Verificar localStorage por si es la primera carga sin cookie
            const storedCinema = localStorage.getItem('aurora_cinema_mode');
            if (storedCinema === 'on') {
                setCinemaMode(true);
            }
        }

        // Inputs para Scrubbing
        const spriteInput = document.getElementById('watch-sprite-source');
        const vttInput = document.getElementById('watch-vtt-source');

        if (_video && hlsSourceInput) {
            // 1. Inicializar reproductor
            loadPlayer(_video, hlsSourceInput.value);
            
            // 2. Inicializar controles UI
            initCustomControls(_video);

            // 3. Inicializar Scrubbing si hay datos
            if (spriteInput && vttInput && spriteInput.value && vttInput.value) {
                _spriteUrl = spriteInput.value;
                _vttUrl = vttInput.value;
                initScrubbing();
            }

            // Iniciar lógica de iluminación
            initAmbientLightLogic();
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
    }
};

function loadPlayer(video, source) {
    if (Hls.isSupported()) {
        if (_hls) {
            _hls.destroy();
        }

        _hls = new Hls({
            capLevelToPlayerSize: true, // Optimización automática
            autoStartLoad: true
        });

        _hls.loadSource(source);
        _hls.attachMedia(video);

        _hls.on(Hls.Events.MANIFEST_PARSED, (event, data) => {
            console.log(`HLS Manifest cargado. ${data.levels.length} niveles encontrados.`);
            _levels = data.levels;
            
            // Renderizar opciones de calidad
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
             const level = _levels[data.level];
             if (level && _hls.autoLevelEnabled) {
                 // console.log(`Auto-cambio a: ${level.height}p`);
             }
        });

    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        // Soporte nativo (Safari)
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

/**
 * Lógica de Scrubbing (Previsualización)
 */
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
        console.warn("Fallo al inicializar scrubbing (posiblemente aún procesando):", e);
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
    
    // [CORRECCIÓN] Guardar en Cookie para persistencia sin FOUC
    document.cookie = `aurora_cinema_mode=${enable ? 'on' : 'off'}; path=/; max-age=31536000`; // 1 año
    localStorage.setItem('aurora_cinema_mode', enable ? 'on' : 'off');

    const layout = document.querySelector('.component-watch-layout');
    const btn = document.getElementById('cinema-mode-btn');
    const icon = btn ? btn.querySelector('span') : null;

    if (enable) {
        layout.classList.add('component-watch-mode-cinema');
        if(icon) icon.innerText = 'crop_free'; // Icono para salir (pantalla normal)
    } else {
        layout.classList.remove('component-watch-mode-cinema');
        if(icon) icon.innerText = 'crop_landscape'; // Icono para entrar (modo cine)
    }

    // Forzar redibujado de ambient canvas si es necesario
    if (_isLightingEnabled) {
        drawAmbientFrame();
    }
}

// ==========================================
// LÓGICA DE ILUMINACIÓN CINEMATOGRÁFICA
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

/**
 * Inicializa la lógica de los controles personalizados
 */
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

    // Elementos de Scrubbing
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

    // 2. Barra de Progreso y Tiempo
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

    // --- LOGICA DE SCRUBBING (Mouse Move) [CORREGIDA] ---
    if (progressContainer && tooltip && _vttData) {
        progressContainer.addEventListener('mousemove', (e) => {
            if (!_vttData.length) return;

            // 1. CÁLCULO DEL TIEMPO (Relativo a la barra de progreso)
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
                
                // 2. CÁLCULO DE POSICIÓN VISUAL (Relativo al padre del tooltip)
                // [IMPORTANTE] Usamos offsetParent para que funcione en modo cine y normal
                const tooltipParent = tooltip.offsetParent || videoContainer; 
                
                if (tooltipParent) {
                    const parentRect = tooltipParent.getBoundingClientRect();
                    let relativeX = e.clientX - parentRect.left;

                    // Límites visuales
                    const halfTooltip = 80; // Aprox mitad de 160px
                    const maxLimit = parentRect.width - halfTooltip;

                    if (relativeX < halfTooltip) relativeX = halfTooltip;
                    if (relativeX > maxLimit) relativeX = maxLimit;

                    tooltip.style.left = `${relativeX}px`;
                }
                
                // Renderizado del sprite
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

    // 3. Volumen
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

    // 4. Pantalla Completa
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

    // 5. Configuración (Settings)
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

    // 6. Modo Cine
    if (cinemaBtn) {
        cinemaBtn.addEventListener('click', () => {
            setCinemaMode(!_isCinemaMode);
        });
    }

    // 7. Ocultar controles automáticamente
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

    // 1. Opción Automática
    const isAutoEnabled = _hls ? _hls.autoLevelEnabled : true;
    
    const autoOption = createQualityOption('-1', 'Automática', isAutoEnabled);
    autoOption.addEventListener('click', () => handleQualityChange(-1, 'Automática', autoOption));
    container.appendChild(autoOption);

    // 2. Niveles disponibles (Orden descendente)
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
        _hls.currentLevel = levelIndex; // -1 = Auto
    }

    const allOpts = document.querySelectorAll('#quality-options-container .component-watch-settings-option');
    allOpts.forEach(o => o.classList.remove('selected'));
    element.classList.add('selected');

    const statusText = levelIndex === -1 ? 'Auto' : label;
    document.getElementById('quality-status-text').innerText = statusText;

    resetSettingsMenu();
    toggleSettingsMenu(); 
}

// Helpers de UI

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