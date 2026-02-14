import { VideoPlayer } from '../watch/VideoPlayer.js';
import { AmbientLight } from '../watch/AmbientLight.js';
import { ScrubbingSystem } from '../watch/ScrubbingSystem.js';
import { InteractionManager } from '../watch/InteractionManager.js';
import { CommentsSection } from '../watch/CommentsSection.js';
import { ControlsManager } from '../watch/ControlsManager.js';
import { PlayerSettings } from '../watch/PlayerSettings.js';

export const WatchController = {
    _player: null,
    _ambient: null,
    _scrubbing: null,
    _interactions: null,
    _comments: null,
    _controls: null,
    _settings: null,

    init: () => {
        const container = document.querySelector('[data-section="watch"]');
        if (!container) return;

        console.log("WatchController: Inicializado (Modular)");

        const videoElement = document.getElementById('main-player');
        const hlsSourceInput = document.getElementById('watch-hls-source');
        
        // 1. Inicializar Player Base
        if (videoElement && hlsSourceInput) {
            WatchController._player = new VideoPlayer(videoElement);
            WatchController._player.load(hlsSourceInput.value);
        }

        // 2. Inicializar Ambient Light
        const ambientCanvas = document.getElementById('ambient-canvas');
        WatchController._ambient = new AmbientLight(videoElement, ambientCanvas);

        // 3. Inicializar Scrubbing
        const spriteInput = document.getElementById('watch-sprite-source');
        const vttInput = document.getElementById('watch-vtt-source');
        if (spriteInput && vttInput) {
            WatchController._scrubbing = new ScrubbingSystem(videoElement, spriteInput.value, vttInput.value);
        }

        // 4. Inicializar UI Controles
        if (WatchController._player) {
            WatchController._controls = new ControlsManager(
                videoElement, 
                WatchController._player, 
                WatchController._ambient
            );
            
            WatchController._settings = new PlayerSettings(
                WatchController._player,
                WatchController._ambient
            );
        }

        // 5. Inicializar Interacciones y Comentarios
        const metaContext = document.querySelector('.js-video-context');
        if (metaContext) {
            const videoUuid = metaContext.dataset.videoUuid;
            const channelUuid = metaContext.dataset.channelUuid;
            const userAvatar = metaContext.dataset.userAvatar || null;

            WatchController._interactions = new InteractionManager(videoElement, videoUuid, channelUuid);
            WatchController._comments = new CommentsSection(videoUuid, userAvatar);
            
            // 6. UI Helpers
            WatchController._initDescriptionToggle();
            WatchController._initCommentInputBehavior();
        }
    },

    _initDescriptionToggle: () => {
        const btn = document.getElementById('btn-toggle-description');
        const textContainer = document.getElementById('video-description-text');

        if (btn && textContainer) {
            btn.addEventListener('click', () => {
                const action = btn.dataset.action;
                const fullText = textContainer.dataset.fullText;
                const truncatedText = textContainer.dataset.truncatedText;

                if (action === 'expand') {
                    textContainer.innerHTML = fullText.replace(/\n/g, '<br>');
                    btn.textContent = 'Leer menos';
                    btn.dataset.action = 'collapse';
                } else {
                    textContainer.innerHTML = truncatedText.replace(/\n/g, '<br>');
                    btn.textContent = 'Leer más';
                    btn.dataset.action = 'expand';
                }
            });
        }
    },

    // --- SOLUCIÓN DEL BUG DE INPUT ---
    _initCommentInputBehavior: () => {
        const inputMain = document.getElementById('comment-input-main');
        const wrapperBox = document.getElementById('comment-wrapper-box');
        
        if (!inputMain || !wrapperBox) return;

        // Crear elemento "Fantasma" para calcular altura real sin oscilaciones
        const ghost = document.createElement('div');
        const computedStyle = window.getComputedStyle(inputMain);
        
        // Estilos críticos para que el fantasma mida igual que el input
        ghost.style.position = 'absolute';
        ghost.style.top = '-9999px';
        ghost.style.left = '-9999px';
        ghost.style.visibility = 'hidden';
        ghost.style.whiteSpace = 'pre-wrap';
        ghost.style.wordBreak = 'break-word';
        ghost.style.overflowWrap = 'anywhere'; // Importante para coincidencias exactas
        ghost.style.fontFamily = computedStyle.fontFamily;
        ghost.style.fontSize = computedStyle.fontSize;
        ghost.style.fontWeight = computedStyle.fontWeight;
        ghost.style.lineHeight = computedStyle.lineHeight;
        ghost.style.padding = computedStyle.padding;
        ghost.style.border = computedStyle.border;
        ghost.style.boxSizing = computedStyle.boxSizing;
        
        document.body.appendChild(ghost);

        const handleInput = () => {
            // 1. Auto-resize del input real
            inputMain.style.height = 'auto'; 
            inputMain.style.height = inputMain.scrollHeight + 'px';

            // 2. Lógica de expansión usando el Fantasma
            // Forzamos al fantasma a tener SIEMPRE el ancho del modo "Row" (colapsado)
            // Ancho Wrapper - Padding Wrapper (24px) - Botón (32px) - Gap (8px) - Buffer seguro (2px)
            const wrapperRect = wrapperBox.getBoundingClientRect();
            // 66px = 12px(pad-left) + 12px(pad-right) + 32px(btn) + 8px(gap) + 2px(border/buffer)
            const collapsedWidth = wrapperRect.width - 66; 
            
            ghost.style.width = `${collapsedWidth}px`;
            
            // Copiar texto (agregando caracter invisible para detectar saltos de linea finales)
            ghost.textContent = inputMain.value + '\u200b'; 

            // Altura de una línea aproximada (line-height + padding vertical)
            // 22px line-height + 8px padding = ~30px.
            // Usamos 35px como umbral seguro.
            const singleLineHeightThreshold = 35; 

            if (ghost.offsetHeight > singleLineHeightThreshold || inputMain.value.includes('\n')) {
                wrapperBox.classList.add('is-expanded');
            } else {
                wrapperBox.classList.remove('is-expanded');
            }
        };

        inputMain.addEventListener('input', handleInput);
        
        // Observer para redimensionar si cambia el tamaño de la ventana
        new ResizeObserver(handleInput).observe(wrapperBox);
        
        // Ejecutar inicial
        handleInput();
    },

    dispose: () => {
        if (WatchController._player) WatchController._player.destroy();
        // Limpieza de controllers...
        WatchController._player = null;
        WatchController._ambient = null;
        WatchController._scrubbing = null;
        WatchController._interactions = null;
        WatchController._comments = null;
        WatchController._controls = null;
        WatchController._settings = null;
    }
};