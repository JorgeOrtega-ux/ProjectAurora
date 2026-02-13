/**
 * public/assets/js/modules/app/watch-controller.js
 * Controlador de reproducción de video HLS con UI personalizada estilo YouTube.
 */

import { ToastManager } from '../../core/components/toast-manager.js';

let _hls = null;
let _video = null;
let _controlsTimeout = null;
let _levels = []; // Almacena niveles de calidad disponibles

export const WatchController = {
    init: () => {
        const container = document.querySelector('[data-section="watch"]');
        if (!container) return;

        console.log("WatchController: Inicializado (Custom UI + Settings)");

        _video = document.getElementById('main-player');
        const hlsSourceInput = document.getElementById('watch-hls-source');

        if (_video && hlsSourceInput) {
            // Inicializar reproductor HLS
            loadPlayer(_video, hlsSourceInput.value);
            // Inicializar eventos de la UI personalizada
            initCustomControls(_video);
        }
    },

    dispose: () => {
        if (_hls) {
            _hls.destroy();
            _hls = null;
        }
        _video = null;
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
            _levels = data.levels; // Guardar niveles disponibles
            renderQualityOptions(_levels); // Renderizar menú de calidad
            
            // Intentar autoplay
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
            // Safari nativo maneja calidad automáticamente, no podemos forzar levels igual que HLS.js fácilmente
            document.getElementById('quality-status-text').innerText = 'Auto';
        });
    } else {
        ToastManager.show('Tu navegador no soporta reproducción HLS.', 'error');
    }
}

/**
 * Inicializa la lógica de los controles personalizados
 */
function initCustomControls(video) {
    const playPauseBtn = document.getElementById('play-pause-btn');
    const muteBtn = document.getElementById('mute-btn');
    const volumeBar = document.getElementById('volume-bar');
    const seekBar = document.getElementById('seek-bar');
    const currentTimeEl = document.getElementById('current-time');
    const durationEl = document.getElementById('duration');
    const fullscreenBtn = document.getElementById('fullscreen-btn');
    const settingsBtn = document.getElementById('settings-btn');
    
    const controlsContainer = document.getElementById('custom-controls');
    const videoContainer = document.getElementById('video-container');
    const settingsPopover = document.getElementById('settings-popover');

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
        // Si el click fue en el popover, no pausar
        if (e.target.closest('.settings-popover')) return;
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
        e.stopPropagation(); // Evitar que cierre inmediatamente si hay click outside
        toggleSettingsMenu();
    });

    // Cerrar menú al hacer clic fuera
    document.addEventListener('click', (e) => {
        if (!settingsPopover.contains(e.target) && !settingsBtn.contains(e.target)) {
            settingsPopover.classList.remove('active');
            resetSettingsMenu();
        }
    });

    // Manejo de navegación del menú settings
    initSettingsNavigation();

    // 6. Ocultar controles automáticamente
    const showControls = () => {
        controlsContainer.classList.add('show');
        videoContainer.style.cursor = 'default';
        clearTimeout(_controlsTimeout);
        
        if (!video.paused) {
            _controlsTimeout = setTimeout(() => {
                // No ocultar si el menú de settings está abierto
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

/**
 * Lógica del menú de configuración
 */
function toggleSettingsMenu() {
    const popover = document.getElementById('settings-popover');
    const isActive = popover.classList.contains('active');
    
    if (isActive) {
        popover.classList.remove('active');
        setTimeout(resetSettingsMenu, 200); // Resetear a main después de la animación de cierre
    } else {
        popover.classList.add('active');
        resetSettingsMenu(); // Asegurar que empieza en main
    }
}

function resetSettingsMenu() {
    document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('settings-main').classList.add('active');
}

function initSettingsNavigation() {
    // Click en items principales para ir a submenús
    document.querySelectorAll('.settings-item').forEach(item => {
        item.addEventListener('click', () => {
            const targetId = item.getAttribute('data-target');
            const targetPanel = document.getElementById(`settings-${targetId}`);
            if (targetPanel) {
                document.getElementById('settings-main').classList.remove('active');
                targetPanel.classList.add('active');
            }
        });
    });

    // Click en headers para volver atrás
    document.querySelectorAll('.settings-header').forEach(header => {
        header.addEventListener('click', () => {
            const backTarget = header.getAttribute('data-back'); // generalmente 'main'
            header.closest('.settings-panel').classList.remove('active');
            document.getElementById(`settings-${backTarget}`).classList.add('active');
        });
    });

    // Selección de opciones de iluminación (Dummy por ahora)
    const lightingOptions = document.querySelectorAll('#settings-lighting .settings-option');
    lightingOptions.forEach(opt => {
        opt.addEventListener('click', () => {
            lightingOptions.forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
            
            const val = opt.getAttribute('data-value');
            const text = opt.querySelector('span').innerText;
            
            // Actualizar texto en menú principal
            document.getElementById('lighting-status-text').innerText = text;
            
            // Volver al menú principal
            resetSettingsMenu();
            console.log("Iluminación cambiada a:", val);
        });
    });
}

/**
 * Renderiza las opciones de calidad basadas en HLS levels
 */
function renderQualityOptions(levels) {
    const container = document.getElementById('quality-options-container');
    container.innerHTML = '';

    // Opción AUTO
    const autoOption = document.createElement('div');
    autoOption.className = 'settings-option selected'; // Auto por defecto
    autoOption.setAttribute('data-quality', '-1'); // -1 es auto en hls.js
    autoOption.innerHTML = `<span>Automática</span><span class="material-symbols-rounded check-icon">check</span>`;
    
    autoOption.addEventListener('click', () => handleQualityChange(-1, 'Automática', autoOption));
    container.appendChild(autoOption);

    // Opciones numéricas (1080p, 720p, etc.)
    // levels viene ordenado de menor a mayor bitrate usualmente, lo invertimos para UI
    [...levels].reverse().forEach((level, index) => {
        // El index original es importante para hls.js
        const originalIndex = levels.indexOf(level); 
        const height = level.height;
        
        const option = document.createElement('div');
        option.className = 'settings-option';
        option.setAttribute('data-quality', originalIndex);
        option.innerHTML = `<span>${height}p</span><span class="material-symbols-rounded check-icon">check</span>`;
        
        option.addEventListener('click', () => handleQualityChange(originalIndex, `${height}p`, option));
        container.appendChild(option);
    });
}

function handleQualityChange(levelIndex, label, element) {
    if (_hls) {
        _hls.currentLevel = levelIndex; // -1 auto, >= 0 fixed
    }

    // Actualizar UI del submenú
    const allOpts = document.querySelectorAll('#quality-options-container .settings-option');
    allOpts.forEach(o => o.classList.remove('selected'));
    element.classList.add('selected');

    // Actualizar texto en menú principal
    const statusText = levelIndex === -1 ? 'Auto' : label;
    document.getElementById('quality-status-text').innerText = statusText;

    // Volver
    resetSettingsMenu();
    console.log("Calidad cambiada a:", label);
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