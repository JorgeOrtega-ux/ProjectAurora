export class AmbientLight {
    constructor(videoElement, canvasElement) {
        this.video = videoElement;
        this.canvas = canvasElement;
        this.ctx = this.canvas ? this.canvas.getContext('2d', { alpha: false }) : null;
        this.isEnabled = false;
        this.animationFrameId = null;

        this._init();
    }

    _init() {
        if (!this.canvas || !this.video) return;

        // Configuración inicial del canvas (baja res para performance/blur)
        this.canvas.width = 100;
        this.canvas.height = 56;

        // Cargar preferencia
        const storedPref = localStorage.getItem('aurora_cinematic_mode');
        this.isEnabled = storedPref === 'on';
        this._updateUIState(this.isEnabled);

        this._attachListeners();
    }

    _attachListeners() {
        this.video.addEventListener('play', () => {
            if (this.isEnabled) {
                this.canvas.style.opacity = '1';
                this.startLoop();
            }
        });

        this.video.addEventListener('pause', () => {
            this.stopLoop();
            if (this.isEnabled) this.canvas.style.opacity = '1';
        });

        this.video.addEventListener('ended', () => {
            this.stopLoop();
            this.canvas.style.opacity = '0';
        });

        this.video.addEventListener('seeked', () => {
            if (this.isEnabled && this.video.paused) {
                this.drawFrame();
            }
        });
    }

    setEnabled(enabled) {
        this.isEnabled = enabled;
        localStorage.setItem('aurora_cinematic_mode', enabled ? 'on' : 'off');
        
        if (enabled) {
            this.canvas.style.display = 'block';
            requestAnimationFrame(() => {
                this.canvas.style.opacity = this.video.paused ? '0.5' : '1';
            });
            if (!this.video.paused) {
                this.startLoop();
            } else {
                this.drawFrame(); 
            }
        } else {
            this.canvas.style.opacity = '0';
            this.stopLoop();
        }

        this._updateUIState(enabled);
    }

    startLoop() {
        if (this.animationFrameId) cancelAnimationFrame(this.animationFrameId);
        
        const loop = () => {
            if (!this.video.paused && !this.video.ended && this.isEnabled) {
                this.drawFrame();
                this.animationFrameId = requestAnimationFrame(loop);
            }
        };
        this.animationFrameId = requestAnimationFrame(loop);
    }

    stopLoop() {
        if (this.animationFrameId) {
            cancelAnimationFrame(this.animationFrameId);
            this.animationFrameId = null;
        }
    }

    drawFrame() {
        if (!this.ctx || !this.video) return;
        this.ctx.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);
    }

    _updateUIState(isEnabled) {
        // Actualizar textos o clases de la UI de configuración si existen
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

    destroy() {
        this.stopLoop();
        this.ctx = null;
        this.canvas = null;
        this.video = null;
    }
}