/* public/assets/js/modules/watch/AmbientLight.js */

export class AmbientLight {
    constructor(videoElement, canvasElement) {
        console.log('[AmbientLight] 🔧 Constructor iniciado');
        this.video = videoElement;
        this.canvas = canvasElement;
        this.ctx = this.canvas ? this.canvas.getContext('2d', { alpha: false }) : null;
        
        // Estado de preferencia del usuario (ON/OFF en el menú)
        this.userPreference = true; 
        
        // Estados del entorno
        this.isDarkMode = false;
        this.isCinemaMode = false;
        
        this.animationFrameId = null;

        if (!this.video) console.error('[AmbientLight] ❌ Falta el elemento Video');
        if (!this.canvas) console.error('[AmbientLight] ❌ Falta el elemento Canvas');

        this._init();
    }

    _init() {
        if (!this.canvas || !this.video) return;

        this.canvas.width = 100;
        this.canvas.height = 56;

        // 1. Cargar preferencia
        const storedPref = localStorage.getItem('aurora_cinematic_mode');
        this.userPreference = storedPref !== 'off';
        console.log(`[AmbientLight] 💾 Preferencia cargada: "${storedPref}" -> Activo: ${this.userPreference}`);

        // 2. Detectar entorno inicial
        this._checkEnvironment();

        // 3. Listeners
        this._attachListeners();
        this._attachEnvironmentObservers();

        // 4. Aplicar estado inicial
        this._updateUIState();
        this._applyVisibility(); 
    }

    /**
     * Verifica el entorno actual basándose EXCLUSIVAMENTE en el DOM.
     * Ya no preguntamos al sistema porque SettingsController se encarga de eso.
     */
    _checkEnvironment() {
        // Detectar Dark Mode en HTML o BODY
        const htmlTheme = document.documentElement.getAttribute('data-theme');
        const bodyTheme = document.body.getAttribute('data-theme');
        
        // Ahora confiamos plenamente en que SettingsController puso el atributo
        this.isDarkMode = (htmlTheme === 'dark' || bodyTheme === 'dark');

        // Detectar Modo Cine
        const layout = document.querySelector('.component-watch-layout');
        this.isCinemaMode = layout ? layout.classList.contains('component-watch-mode-cinema') : false;

        console.log(`[AmbientLight] 🌍 Check Environment:
        > HTML Theme: "${htmlTheme}"
        > Is DarkMode: ${this.isDarkMode}
        > Is CinemaMode: ${this.isCinemaMode}`);
    }

    _shouldBeVisible() {
        return this.userPreference && (this.isDarkMode || this.isCinemaMode);
    }

    _applyVisibility() {
        const shouldShow = this._shouldBeVisible();
        
        if (shouldShow) {
            this.canvas.style.display = 'block';
            requestAnimationFrame(() => {
                this.canvas.style.opacity = '1';
            });
            
            if (!this.video.paused && !this.animationFrameId) {
                this.startLoop();
            } else {
                this.drawFrame(); 
            }
        } else {
            this.canvas.style.opacity = '0';
            setTimeout(() => {
                if (!this._shouldBeVisible()) {
                    this.canvas.style.display = 'none';
                    this.stopLoop();
                }
            }, 800);
        }
    }

    _attachListeners() {
        this.video.addEventListener('play', () => {
            if (this._shouldBeVisible()) {
                this.canvas.style.display = 'block';
                requestAnimationFrame(() => this.canvas.style.opacity = '1');
                this.startLoop();
            }
        });

        this.video.addEventListener('pause', () => {
            this.stopLoop();
            if (this._shouldBeVisible()) this.canvas.style.opacity = '1';
        });

        this.video.addEventListener('ended', () => {
            this.stopLoop();
            this.canvas.style.opacity = '0';
        });

        this.video.addEventListener('seeked', () => {
            if (this._shouldBeVisible() && this.video.paused) {
                this.drawFrame();
            }
        });
    }

    _attachEnvironmentObservers() {
        // Solo observamos el DOM. Si el sistema cambia, SettingsController actualizará el DOM,
        // y este observer se disparará. ¡Eficiencia pura!
        const themeObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme') {
                    console.log('[AmbientLight] 🔄 Cambio de atributo TEMA detectado');
                    this._checkEnvironment();
                    this._applyVisibility();
                }
            });
        });
        themeObserver.observe(document.documentElement, { attributes: true });

        const layout = document.querySelector('.component-watch-layout');
        if (layout) {
            const modeObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        this._checkEnvironment();
                        this._applyVisibility();
                    }
                });
            });
            modeObserver.observe(layout, { attributes: true });
        }
    }

    // --- API Pública ---

    setEnabled(enabled) {
        console.log(`[AmbientLight] 🖱️ Usuario cambió interruptor a: ${enabled}`);
        this.userPreference = enabled;
        localStorage.setItem('aurora_cinematic_mode', enabled ? 'on' : 'off');
        this._updateUIState();
        this._applyVisibility();
    }

    startLoop() {
        if (this.animationFrameId) cancelAnimationFrame(this.animationFrameId);
        
        const loop = () => {
            if (!this.video.paused && !this.video.ended && this._shouldBeVisible()) {
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
        try {
            this.ctx.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);
        } catch (e) {}
    }

    _updateUIState() {
        const textStatus = document.getElementById('lighting-status-text');
        if (textStatus) {
            textStatus.innerText = this.userPreference ? 'Activo' : 'Desactivado';
        }

        const options = document.querySelectorAll('#settings-lighting .component-watch-settings-option');
        options.forEach(opt => {
            const isTargetOn = opt.dataset.value === 'on';
            if (isTargetOn === this.userPreference) {
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