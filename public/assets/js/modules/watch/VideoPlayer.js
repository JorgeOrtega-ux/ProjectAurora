import { ToastManager } from '../../core/components/toast-manager.js';

export class VideoPlayer {
    constructor(videoElement) {
        this.video = videoElement;
        this.hls = null;
        this.levels = [];
        this.onLevelsLoaded = null; // Callback para notificar a la UI
    }

    load(source) {
        if (Hls.isSupported()) {
            this._initHls(source);
        } else if (this.video.canPlayType('application/vnd.apple.mpegurl')) {
            // Safari Nativo
            this.video.src = source;
            this.video.addEventListener('loadedmetadata', () => {
                this.play();
                if (this.onLevelsLoaded) this.onLevelsLoaded([], true); // true = isNative
            });
        } else {
            ToastManager.show('Tu navegador no soporta reproducción HLS.', 'error');
        }
    }

    _initHls(source) {
        if (this.hls) {
            this.hls.destroy();
        }

        this.hls = new Hls({
            capLevelToPlayerSize: true, 
            autoStartLoad: true
        });

        this.hls.loadSource(source);
        this.hls.attachMedia(this.video);

        this.hls.on(Hls.Events.MANIFEST_PARSED, (event, data) => {
            console.log(`VideoPlayer: HLS Manifest cargado. ${data.levels.length} niveles encontrados.`);
            this.levels = data.levels;
            
            // Notificar a quien le interese (ej. Settings)
            if (this.onLevelsLoaded) {
                this.onLevelsLoaded(this.levels, false);
            }
            
            this.play();
        });

        this.hls.on(Hls.Events.ERROR, (event, data) => {
            if (data.fatal) {
                switch (data.type) {
                    case Hls.ErrorTypes.NETWORK_ERROR:
                        console.warn("HLS Network Error, intentando recuperar...");
                        this.hls.startLoad();
                        break;
                    case Hls.ErrorTypes.MEDIA_ERROR:
                        console.warn("HLS Media Error, intentando recuperar...");
                        this.hls.recoverMediaError();
                        break;
                    default:
                        console.error("HLS Error Fatal:", data);
                        this.hls.destroy();
                        ToastManager.show('Error crítico de reproducción.', 'error');
                        break;
                }
            }
        });
    }

    play() {
        // Promesa para evitar errores de play ininterrumpido
        const playPromise = this.video.play();
        if (playPromise !== undefined) {
            playPromise.catch(e => console.log("Autoplay bloqueado o interrumpido:", e));
        }
    }

    pause() {
        this.video.pause();
    }

    togglePlay() {
        if (this.video.paused || this.video.ended) {
            this.play();
        } else {
            this.pause();
        }
    }

    setLevel(index) {
        if (this.hls) {
            this.hls.currentLevel = index;
        }
    }

    getAutoLevelEnabled() {
        return this.hls ? this.hls.autoLevelEnabled : true;
    }

    getCurrentLevel() {
        return this.hls ? this.hls.currentLevel : -1;
    }

    destroy() {
        if (this.hls) {
            this.hls.destroy();
            this.hls = null;
        }
        this.video = null;
        this.levels = [];
    }
}