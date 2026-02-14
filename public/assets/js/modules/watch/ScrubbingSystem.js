export class ScrubbingSystem {
    constructor(videoElement, spriteUrl, vttUrl) {
        // console.log('[Scrubbing] 🔧 Constructor iniciado');
        this.video = videoElement;
        this.spriteUrl = spriteUrl;
        this.vttUrl = vttUrl;
        this.vttData = [];
        this.spriteImage = null;
        
        // Elementos UI
        this.progressContainer = document.querySelector('.component-watch-progress-container');
        this.playerCard = this.video.closest('.component-watch-player-card') || document.getElementById('video-container');
        
        this.tooltip = document.getElementById('scrub-tooltip');
        // Necesitamos el wrapper para leer el SCALE del CSS
        this.tooltipWrapper = this.tooltip ? this.tooltip.querySelector('.component-watch-scrub-img-wrapper') : null;
        this.tooltipImg = this.tooltip ? this.tooltip.querySelector('.component-watch-scrub-preview') : null;
        this.tooltipTime = this.tooltip ? this.tooltip.querySelector('.component-watch-scrub-time') : null;

        if (this.spriteUrl && this.vttUrl) {
            this.init();
        }
    }

    async init() {
        this.spriteImage = new Image();
        this.spriteImage.src = this.spriteUrl;
        
        try {
            const response = await fetch(this.vttUrl);
            if (!response.ok) throw new Error("Error cargando VTT");
            const text = await response.text();
            this.vttData = this._parseVTT(text);
            this._attachListeners();
        } catch (e) {
            console.warn("[Scrubbing] ❌ Fallo al inicializar:", e);
        }
    }

    _parseVTT(vttText) {
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
                currentStart = this._parseTime(timeMatch[1]);
                currentEnd = this._parseTime(timeMatch[2]);
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

    _parseTime(timeStr) {
        const parts = timeStr.split(':');
        const h = parseInt(parts[0]);
        const m = parseInt(parts[1]);
        const s = parseFloat(parts[2]);
        return (h * 3600) + (m * 60) + s;
    }

    _formatTime(time) {
        if (!time || isNaN(time)) return "0:00";
        const minutes = Math.floor(time / 60);
        const seconds = Math.floor(time % 60);
        return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
    }

    // Función auxiliar para leer la escala real del CSS
    _getScaleFactor() {
        if (!this.tooltipWrapper) return 1;
        const style = window.getComputedStyle(this.tooltipWrapper);
        const transform = style.transform || style.webkitTransform;
        
        // La matriz de transformación es "matrix(a, b, c, d, tx, ty)"
        // El valor 'a' (índice 0) suele ser la escala X
        if (transform && transform !== 'none') {
            const values = transform.split('(')[1].split(')')[0].split(',');
            const a = parseFloat(values[0]);
            const b = parseFloat(values[1]);
            // Calculamos la magnitud del vector para ser precisos (por si hubiera rotación)
            const scale = Math.sqrt(a*a + b*b);
            return scale;
        }
        return 1;
    }

    _attachListeners() {
        if (!this.progressContainer || !this.tooltip) return;

        this.progressContainer.addEventListener('mousemove', (e) => {
            if (!this.vttData.length) return;

            // 1. Calcular Tiempo
            const rectBar = this.progressContainer.getBoundingClientRect();
            const offsetXBar = e.clientX - rectBar.left;
            let percent = offsetXBar / rectBar.width;
            if (percent < 0) percent = 0;
            if (percent > 1) percent = 1;

            const hoverTime = percent * this.video.duration;
            if (this.tooltipTime) this.tooltipTime.innerText = this._formatTime(hoverTime);

            // 2. Buscar Frame
            const frame = this.vttData.find(f => hoverTime >= f.start && hoverTime < f.end);
            
            if (frame && this.tooltipImg) {
                this.tooltip.style.display = 'flex';
                
                // Aplicar imagen
                this.tooltipImg.style.backgroundImage = `url('${this.spriteUrl}')`;
                this.tooltipImg.style.backgroundPosition = `-${frame.x}px -${frame.y}px`;
                this.tooltipImg.style.width = `${frame.w}px`;
                this.tooltipImg.style.height = `${frame.h}px`;

                // 3. CÁLCULO DE POSICIÓN DINÁMICO
                if (this.playerCard) {
                    const cardRect = this.playerCard.getBoundingClientRect();
                    
                    // A. Obtenemos el factor de escala DIRECTAMENTE del CSS
                    const currentScale = this._getScaleFactor();
                    
                    // B. Margen de seguridad (px)
                    const SAFETY_MARGIN = 12; 

                    // C. Calculamos ancho visual real usando la escala detectada
                    const visualWidth = this.tooltip.offsetWidth * currentScale;
                    const halfTooltipVisual = (visualWidth / 2) + SAFETY_MARGIN;
                    
                    // D. Límites y Clamping
                    const mouseGlobalX = e.clientX;
                    const minGlobalX = cardRect.left + halfTooltipVisual;
                    const maxGlobalX = cardRect.right - halfTooltipVisual;

                    let targetGlobalX = Math.max(minGlobalX, Math.min(mouseGlobalX, maxGlobalX));

                    // E. Posicionamiento local
                    const offsetParent = this.tooltip.offsetParent;
                    if (offsetParent) {
                        const parentRect = offsetParent.getBoundingClientRect();
                        const finalLocalLeft = targetGlobalX - parentRect.left;
                        
                        this.tooltip.style.left = `${finalLocalLeft}px`;
                    }
                }
            }
        });

        this.progressContainer.addEventListener('mouseleave', () => {
            if (this.tooltip) this.tooltip.style.display = 'none';
        });
    }

    destroy() {
        this.vttData = [];
        this.spriteImage = null;
    }
}