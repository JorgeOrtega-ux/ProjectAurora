/**
 * public/assets/js/modules/app/watch-controller.js
 * Controlador de reproducción de video HLS con UI personalizada estilo YouTube.
 * Incluye lógica de Scrubbing (Previsualización con Sprites).
 * [FIX] Delegación de eventos para menú de configuración.
 */

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

export const WatchController = {
    init: () => {
        const container = document.querySelector('[data-section="watch"]');
        if (!container) return;

        console.log("WatchController: Inicializado (Custom UI + Settings + Scrubbing)");

        _video = document.getElementById('main-player');
        const hlsSourceInput = document.getElementById('watch-hls-source');

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
        }
    },

    dispose: () => {
        if (_hls) {
            _hls.destroy();
            _hls = null;
        }
        _video = null;
        _vttData = [];
        _spriteImage = null;
    }
};

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
            console.log("HLS Manifest loaded.", data.levels);
            _levels = data.levels;
            renderQualityOptions(_levels);
            
            video.play().catch(e => console.log("Autoplay bloqueado:", e));
            updatePlayPauseIcon(false);
        });

        _hls.on(Hls.Events.ERROR, (event, data) => {
            if (data.fatal) {
                switch (data.type) {
                    case Hls.ErrorTypes.NETWORK_ERROR:
                        console.error("HLS Network Error, recovering...");
                        _hls.startLoad();
                        break;
                    case Hls.ErrorTypes.MEDIA_ERROR:
                        console.error("HLS Media Error, recovering...");
                        _hls.recoverMediaError();
                        break;
                    default:
                        _hls.destroy();
                        ToastManager.show('Error crítico de reproducción.', 'error');
                        break;
                }
            }
        });

    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        // Soporte nativo (Safari)
        video.src = source;
        video.addEventListener('loadedmetadata', () => {
            video.play();
            updatePlayPauseIcon(false);
            document.getElementById('quality-status-text').innerText = 'Auto';
        });
    } else {
        ToastManager.show('Tu navegador no soporta reproducción HLS.', 'error');
    }
}

/**
 * Lógica de Scrubbing (Previsualización)
 */
async function initScrubbing() {
    console.log("Iniciando Scrubbing...");
    
    // 1. Precargar Imagen del Sprite
    _spriteImage = new Image();
    _spriteImage.src = _spriteUrl;
    
    // 2. Descargar y Parsear VTT
    try {
        const response = await fetch(_vttUrl);
        if (!response.ok) throw new Error("Error cargando VTT");
        const text = await response.text();
        _vttData = parseVTT(text);
        console.log(`Scrubbing listo: ${_vttData.length} frames indexados.`);
    } catch (e) {
        console.error("Fallo al inicializar scrubbing:", e);
    }
}

/**
 * Parsea el contenido de un archivo WebVTT de sprites
 */
function parseVTT(vttText) {
    const lines = vttText.split('\n');
    const data = [];
    let currentStart = null;
    let currentEnd = null;

    // Regex para tiempo: 00:00:00.000
    const timeRegex = /(\d{2}:\d{2}:\d{2}\.\d{3}) --> (\d{2}:\d{2}:\d{2}\.\d{3})/;
    // Regex para coords: #xywh=x,y,w,h
    const coordsRegex = /#xywh=(\d+),(\d+),(\d+),(\d+)/;

    for (let line of lines) {
        line = line.trim();
        if (!line) continue;
        if (line.startsWith('WEBVTT')) continue;

        // Buscar Tiempos
        const timeMatch = timeRegex.exec(line);
        if (timeMatch) {
            currentStart = parseTime(timeMatch[1]);
            currentEnd = parseTime(timeMatch[2]);
            continue;
        }

        // Buscar Coordenadas
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

// Convierte HH:MM:SS.ms a segundos
function parseTime(timeStr) {
    const parts = timeStr.split(':');
    const h = parseInt(parts[0]);
    const m = parseInt(parts[1]);
    const s = parseFloat(parts[2]);
    return (h * 3600) + (m * 60) + s;
}

/**
 * Inicializa la lógica de los controles personalizados
 */
function initCustomControls(video) {
    const playPauseBtn = document.getElementById('play-pause-btn');
    const muteBtn = document.getElementById('mute-btn');
    const volumeBar = document.getElementById('volume-bar');
    const seekBar = document.getElementById('seek-bar');
    const progressContainer = document.querySelector('.progress-container');
    const currentTimeEl = document.getElementById('current-time');
    const durationEl = document.getElementById('duration');
    const fullscreenBtn = document.getElementById('fullscreen-btn');
    const settingsBtn = document.getElementById('settings-btn');
    
    const controlsContainer = document.getElementById('custom-controls');
    const videoContainer = document.getElementById('video-container');
    const settingsPopover = document.getElementById('settings-popover');

    // Elementos de Scrubbing
    const tooltip = document.getElementById('scrub-tooltip');
    const tooltipImg = tooltip ? tooltip.querySelector('.scrub-preview-img') : null;
    const tooltipTime = tooltip ? tooltip.querySelector('.scrub-time') : null;

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
        if (e.target.closest('.settings-popover')) return; // IMPORTANTE: No pausar si click es en settings
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

    // Input (Arrastrar la bola)
    seekBar.addEventListener('input', () => {
        video.currentTime = seekBar.value;
        updateSeekBarBackground(seekBar);
        currentTimeEl.innerText = formatTime(seekBar.value);
    });

    // --- LOGICA DE SCRUBBING (Mouse Move) ---
    if (progressContainer && tooltip && _vttData) {
        
        progressContainer.addEventListener('mousemove', (e) => {
            if (!_vttData.length) return;

            // Calcular posición relativa dentro de la barra
            const rect = progressContainer.getBoundingClientRect();
            const offsetX = e.clientX - rect.left;
            let percent = offsetX / rect.width;
            
            // Limites 0-1
            if (percent < 0) percent = 0;
            if (percent > 1) percent = 1;

            // Calcular tiempo objetivo
            const hoverTime = percent * video.duration;

            // Actualizar Texto Tiempo (en la píldora)
            if (tooltipTime) {
                tooltipTime.innerText = formatTime(hoverTime);
            }

            // Buscar Frame en VTT
            const frame = _vttData.find(f => hoverTime >= f.start && hoverTime < f.end);
            
            if (frame && tooltipImg) {
                // Mostrar Tooltip
                tooltip.style.display = 'flex';
                
                // Mover Tooltip (centrado en el mouse, con límites)
                let leftPos = offsetX; 
                if(leftPos < 80) leftPos = 80; 
                if(leftPos > rect.width - 80) leftPos = rect.width - 80;

                tooltip.style.left = `${leftPos}px`;

                // Renderizar Imagen (Sprite)
                tooltipImg.style.backgroundImage = `url('${_spriteUrl}')`;
                tooltipImg.style.backgroundPosition = `-${frame.x}px -${frame.y}px`;
                
                // Aplicar dimensiones exactas del recorte
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

    // [FIX] Iniciamos la navegación de settings con delegación
    initSettingsNavigationDelegated();

    // 6. Ocultar controles automáticamente
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
    document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('settings-main').classList.add('active');
}

/**
 * [FIX] Delegación de Eventos para el Menú
 * Detecta clics en cualquier parte del popover y actúa según el elemento clickeado.
 */
function initSettingsNavigationDelegated() {
    const popover = document.getElementById('settings-popover');
    if(!popover) return;

    popover.addEventListener('click', (e) => {
        // 1. Click en Item de Menú (para ir a submenú)
        const item = e.target.closest('.settings-item');
        if (item) {
            const targetId = item.getAttribute('data-target');
            const targetPanel = document.getElementById(`settings-${targetId}`);
            if (targetPanel) {
                document.getElementById('settings-main').classList.remove('active');
                targetPanel.classList.add('active');
            }
            return;
        }

        // 2. Click en Header (botón "Atrás")
        const header = e.target.closest('.settings-header');
        if (header) {
            const backTarget = header.getAttribute('data-back'); 
            header.closest('.settings-panel').classList.remove('active');
            document.getElementById(`settings-${backTarget}`).classList.add('active');
            return;
        }

        // 3. Click en Opción de Iluminación
        const lightingOpt = e.target.closest('#settings-lighting .settings-option');
        if (lightingOpt) {
            document.querySelectorAll('#settings-lighting .settings-option').forEach(o => o.classList.remove('selected'));
            lightingOpt.classList.add('selected');
            const text = lightingOpt.querySelector('span').innerText;
            document.getElementById('lighting-status-text').innerText = text;
            resetSettingsMenu();
            toggleSettingsMenu(); // Cerrar menú al seleccionar
        }
    });
}

/**
 * Renderiza las opciones de calidad basadas en HLS levels
 */
function renderQualityOptions(levels) {
    const container = document.getElementById('quality-options-container');
    container.innerHTML = '';

    // Opción Automática
    const autoOption = createQualityOption('-1', 'Automática', true);
    autoOption.addEventListener('click', () => handleQualityChange(-1, 'Automática', autoOption));
    container.appendChild(autoOption);

    // Niveles disponibles
    [...levels].reverse().forEach((level, index) => {
        const originalIndex = levels.indexOf(level); 
        const height = level.height;
        
        const option = createQualityOption(originalIndex, `${height}p`, false);
        
        option.addEventListener('click', () => handleQualityChange(originalIndex, `${height}p`, option));
        container.appendChild(option);
    });
}

function createQualityOption(val, text, isSelected) {
    const div = document.createElement('div');
    div.className = `settings-option ${isSelected ? 'selected' : ''}`;
    div.setAttribute('data-quality', val);
    div.innerHTML = `<span>${text}</span><span class="material-symbols-rounded check-icon">check</span>`;
    return div;
}

function handleQualityChange(levelIndex, label, element) {
    if (_hls) {
        _hls.currentLevel = levelIndex; 
    }

    const allOpts = document.querySelectorAll('#quality-options-container .settings-option');
    allOpts.forEach(o => o.classList.remove('selected'));
    element.classList.add('selected');

    const statusText = levelIndex === -1 ? 'Auto' : label;
    document.getElementById('quality-status-text').innerText = statusText;

    resetSettingsMenu();
    toggleSettingsMenu(); // Cerrar al seleccionar
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