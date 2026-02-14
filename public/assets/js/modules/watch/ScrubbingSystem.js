export class ScrubbingSystem {
    constructor(videoElement, spriteUrl, vttUrl) {
        this.video = videoElement;
        this.spriteUrl = spriteUrl;
        this.vttUrl = vttUrl;
        this.vttData = [];
        this.spriteImage = null;
        
        // Elementos UI
        this.progressContainer = document.querySelector('.component-watch-progress-container');
        this.videoContainer = document.getElementById('video-container');
        this.tooltip = document.getElementById('scrub-tooltip');
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
            console.log(`ScrubbingSystem: ${this.vttData.length} frames indexados.`);
            this._attachListeners();
        } catch (e) {
            console.warn("Fallo al inicializar scrubbing:", e);
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

    _attachListeners() {
        if (!this.progressContainer || !this.tooltip) return;

        this.progressContainer.addEventListener('mousemove', (e) => {
            if (!this.vttData.length) return;

            const rectBar = this.progressContainer.getBoundingClientRect();
            const offsetXBar = e.clientX - rectBar.left;
            let percent = offsetXBar / rectBar.width;
            
            if (percent < 0) percent = 0;
            if (percent > 1) percent = 1;

            const hoverTime = percent * this.video.duration;

            if (this.tooltipTime) {
                this.tooltipTime.innerText = this._formatTime(hoverTime);
            }

            const frame = this.vttData.find(f => hoverTime >= f.start && hoverTime < f.end);
            
            if (frame && this.tooltipImg) {
                this.tooltip.style.display = 'flex';
                
                const tooltipParent = this.tooltip.offsetParent || this.videoContainer; 
                
                if (tooltipParent) {
                    const parentRect = tooltipParent.getBoundingClientRect();
                    let relativeX = e.clientX - parentRect.left;

                    const halfTooltip = 80; // Mitad aproximada del ancho del tooltip
                    const maxLimit = parentRect.width - halfTooltip;

                    if (relativeX < halfTooltip) relativeX = halfTooltip;
                    if (relativeX > maxLimit) relativeX = maxLimit;

                    this.tooltip.style.left = `${relativeX}px`;
                }
                
                this.tooltipImg.style.backgroundImage = `url('${this.spriteUrl}')`;
                this.tooltipImg.style.backgroundPosition = `-${frame.x}px -${frame.y}px`;
                this.tooltipImg.style.width = `${frame.w}px`;
                this.tooltipImg.style.height = `${frame.h}px`;
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