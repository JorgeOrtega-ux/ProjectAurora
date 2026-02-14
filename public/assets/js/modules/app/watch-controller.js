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

        console.log("WatchController: Inicializado");

        const videoElement = document.getElementById('main-player');
        const hlsSourceInput = document.getElementById('watch-hls-source');
        
        // 1. Inicializar Player Base
        if (videoElement && hlsSourceInput) {
            WatchController._player = new VideoPlayer(videoElement);
            WatchController._player.load(hlsSourceInput.value);
        }

        // 2. Inicializar Ambient Light
        const ambientCanvas = document.getElementById('ambient-canvas');
        if (ambientCanvas) {
            WatchController._ambient = new AmbientLight(videoElement, ambientCanvas);
        }

        // 3. Inicializar Scrubbing
        const spriteInput = document.getElementById('watch-sprite-source');
        const vttInput = document.getElementById('watch-vtt-source');
        if (spriteInput && vttInput && spriteInput.value && vttInput.value) {
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
            
            // IMPORTANTE: Aquí obtenemos el avatar del usuario actual desde el HTML
            // Si el usuario no está logueado, esto vendrá vacío o null.
            const userAvatar = metaContext.dataset.userAvatar || null;

            WatchController._interactions = new InteractionManager(videoElement, videoUuid, channelUuid);
            
            // Pasamos el avatar a la sección de comentarios para validar sesión
            WatchController._comments = new CommentsSection(videoUuid, userAvatar);
            
            // 6. UI Helpers (Descripción expandible)
            WatchController._initDescriptionToggle();
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
                    // Reemplazamos saltos de línea por <br> para mantener formato
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

    dispose: () => {
        if (WatchController._player) WatchController._player.destroy();
        
        WatchController._player = null;
        WatchController._ambient = null;
        WatchController._scrubbing = null;
        WatchController._interactions = null;
        WatchController._comments = null;
        WatchController._controls = null;
        WatchController._settings = null;
    }
};