export class ControlsManager {
    constructor(videoElement, videoPlayerInstance, ambientLightInstance) {
        this.video = videoElement;
        this.player = videoPlayerInstance;
        this.ambient = ambientLightInstance;
        
        // Elementos DOM
        this.playPauseBtn = document.getElementById('play-pause-btn');
        this.muteBtn = document.getElementById('mute-btn');
        this.volumeBar = document.getElementById('volume-bar');
        this.seekBar = document.getElementById('seek-bar');
        this.currentTimeEl = document.getElementById('current-time');
        this.durationEl = document.getElementById('duration');
        this.fullscreenBtn = document.getElementById('fullscreen-btn');
        this.cinemaBtn = document.getElementById('cinema-mode-btn');
        this.controlsContainer = document.getElementById('custom-controls');
        this.videoContainer = document.getElementById('video-container');

        this.isCinemaMode = false;
        this.controlsTimeout = null;

        this.init();
    }

    init() {
        this._initCinemaMode();
        this._attachVideoListeners();
        this._attachControlListeners();
        this._initAutoHide();
    }

    _initCinemaMode() {
        const layout = document.querySelector('.component-watch-layout');
        const cinemaIcon = this.cinemaBtn ? this.cinemaBtn.querySelector('span') : null;

        if (layout && layout.classList.contains('component-watch-mode-cinema')) {
             this.isCinemaMode = true;
             if(cinemaIcon) cinemaIcon.innerText = 'crop_free';
        } else {
            const storedCinema = localStorage.getItem('aurora_cinema_mode');
            if (storedCinema === 'on') {
                this.setCinemaMode(true);
            }
        }
    }

    setCinemaMode(enable) {
        this.isCinemaMode = enable;
        document.cookie = `aurora_cinema_mode=${enable ? 'on' : 'off'}; path=/; max-age=31536000`; 
        localStorage.setItem('aurora_cinema_mode', enable ? 'on' : 'off');

        const layout = document.querySelector('.component-watch-layout');
        const icon = this.cinemaBtn ? this.cinemaBtn.querySelector('span') : null;

        if (enable) {
            layout.classList.add('component-watch-mode-cinema');
            if(icon) icon.innerText = 'crop_free';
        } else {
            layout.classList.remove('component-watch-mode-cinema');
            if(icon) icon.innerText = 'crop_landscape';
        }

        if (this.ambient && this.ambient.isEnabled) {
            this.ambient.drawFrame();
        }
    }

    _attachVideoListeners() {
        this.video.addEventListener('play', () => this.updatePlayPauseIcon(false));
        this.video.addEventListener('pause', () => this.updatePlayPauseIcon(true));
        
        this.video.addEventListener('loadedmetadata', () => {
            this.seekBar.max = this.video.duration;
            this.durationEl.innerText = this._formatTime(this.video.duration);
        });

        this.video.addEventListener('timeupdate', () => {
            if (!this.video.paused) {
                this.seekBar.value = this.video.currentTime;
                this.currentTimeEl.innerText = this._formatTime(this.video.currentTime);
                this._updateSeekBarBackground(this.seekBar);
            }
        });

        // Click en video para pausa
        this.video.addEventListener('click', (e) => {
            if (e.target.closest('.component-watch-settings-popover')) return; 
            this.player.togglePlay();
        });
    }

    _attachControlListeners() {
        this.playPauseBtn.addEventListener('click', () => this.player.togglePlay());
        
        this.seekBar.addEventListener('input', () => {
            this.video.currentTime = this.seekBar.value;
            this._updateSeekBarBackground(this.seekBar);
            this.currentTimeEl.innerText = this._formatTime(this.seekBar.value);
        });

        this.volumeBar.addEventListener('input', (e) => {
            this.video.volume = e.target.value;
            this.video.muted = e.target.value === 0;
            this.updateVolumeIcon(this.video.volume);
        });

        this.muteBtn.addEventListener('click', () => {
            this.video.muted = !this.video.muted;
            if (this.video.muted) {
                this.volumeBar.value = 0;
                this.updateVolumeIcon(0);
            } else {
                this.video.volume = 1;
                this.volumeBar.value = 1;
                this.updateVolumeIcon(1);
            }
        });

        this.fullscreenBtn.addEventListener('click', () => {
            if (!document.fullscreenElement) {
                if (this.videoContainer.requestFullscreen) {
                    this.videoContainer.requestFullscreen();
                } else if (this.videoContainer.webkitRequestFullscreen) {
                    this.videoContainer.webkitRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        });

        if (this.cinemaBtn) {
            this.cinemaBtn.addEventListener('click', () => {
                this.setCinemaMode(!this.isCinemaMode);
            });
        }
    }

    _initAutoHide() {
        const showControls = () => {
            this.controlsContainer.classList.add('show');
            this.videoContainer.style.cursor = 'default';
            clearTimeout(this.controlsTimeout);
            
            if (!this.video.paused) {
                this.controlsTimeout = setTimeout(() => {
                    const settingsPopover = document.getElementById('settings-popover');
                    if (!settingsPopover || !settingsPopover.classList.contains('active')) {
                        this.controlsContainer.classList.remove('show');
                        this.videoContainer.style.cursor = 'none';
                    }
                }, 3000);
            }
        };

        this.videoContainer.addEventListener('mousemove', showControls);
        this.videoContainer.addEventListener('mouseleave', () => {
            const settingsPopover = document.getElementById('settings-popover');
            if (!this.video.paused && (!settingsPopover || !settingsPopover.classList.contains('active'))) {
                this.controlsContainer.classList.remove('show');
            }
        });
    }

    updatePlayPauseIcon(isPaused) {
        const icon = this.playPauseBtn.querySelector('span');
        icon.innerText = isPaused ? 'play_arrow' : 'pause';
    }

    updateVolumeIcon(vol) {
        const icon = this.muteBtn.querySelector('span');
        if (vol === 0) icon.innerText = 'volume_off';
        else if (vol < 0.5) icon.innerText = 'volume_down';
        else icon.innerText = 'volume_up';
    }

    _formatTime(time) {
        if (!time || isNaN(time)) return "0:00";
        const minutes = Math.floor(time / 60);
        const seconds = Math.floor(time % 60);
        return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
    }

    _updateSeekBarBackground(input) {
        const min = input.min || 0;
        const max = input.max || 100;
        const val = input.value;
        const percentage = ((val - min) / (max - min)) * 100;
        input.style.backgroundSize = `${percentage}% 100%`;
    }
}