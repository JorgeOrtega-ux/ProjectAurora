/**
 * public/assets/js/modules/app/watch-controller.js
 * Controlador de reproducción de video HLS.
 */

import { ToastManager } from '../../core/components/toast-manager.js';

let _hls = null;

export const WatchController = {
    init: () => {
        const container = document.querySelector('[data-section="watch"]');
        if (!container) return;

        console.log("WatchController: Inicializado (Player HLS)");

        const videoEl = document.getElementById('main-player');
        const hlsSourceInput = document.getElementById('watch-hls-source');

        if (videoEl && hlsSourceInput) {
            const src = hlsSourceInput.value;
            loadPlayer(videoEl, src);
        }
    },

    dispose: () => {
        if (_hls) {
            _hls.destroy();
            _hls = null;
        }
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

        _hls.on(Hls.Events.MANIFEST_PARSED, () => {
            console.log("HLS Manifest loaded, playing...");
            // Intentar autoplay (puede ser bloqueado por el navegador si no está muteado)
            video.play().catch(e => console.log("Autoplay bloqueado (esperando interacción):", e));
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
        });
    } else {
        ToastManager.show('Tu navegador no soporta reproducción HLS.', 'error');
    }
}