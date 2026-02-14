export class PlayerSettings {
    constructor(videoPlayerInstance, ambientLightInstance) {
        this.player = videoPlayerInstance;
        this.ambient = ambientLightInstance;
        
        this.settingsBtn = document.getElementById('settings-btn');
        this.popover = document.getElementById('settings-popover');
        this.container = document.getElementById('quality-options-container');

        this.init();
    }

    init() {
        this.settingsBtn.addEventListener('click', (e) => {
            e.stopPropagation(); 
            this.toggleMenu();
        });

        document.addEventListener('click', (e) => {
            if (this.popover && !this.popover.contains(e.target) && !this.settingsBtn.contains(e.target)) {
                this.popover.classList.remove('active');
                this.resetMenu();
            }
        });

        this._initNavigation();
        this._initLightingOptions();

        // Escuchar cuando el player cargue los niveles HLS
        this.player.onLevelsLoaded = (levels, isNative) => {
            if (isNative) {
                document.getElementById('quality-status-text').innerText = 'Auto (Nativo)';
            } else {
                this.renderQualityOptions(levels);
            }
        };
    }

    toggleMenu() {
        if (this.popover.classList.contains('active')) {
            this.popover.classList.remove('active');
            setTimeout(() => this.resetMenu(), 200); 
        } else {
            this.popover.classList.add('active');
            this.resetMenu(); 
        }
    }

    resetMenu() {
        document.querySelectorAll('.component-watch-settings-panel').forEach(p => p.classList.remove('active'));
        document.getElementById('settings-main').classList.add('active');
    }

    _initNavigation() {
        if(!this.popover) return;

        this.popover.addEventListener('click', (e) => {
            const item = e.target.closest('.component-watch-settings-item');
            if (item) {
                const targetId = item.getAttribute('data-target');
                const targetPanel = document.getElementById(`settings-${targetId}`);
                if (targetPanel) {
                    document.getElementById('settings-main').classList.remove('active');
                    targetPanel.classList.add('active');
                }
                return;
            }

            const header = e.target.closest('.component-watch-settings-header');
            if (header) {
                const backTarget = header.getAttribute('data-back'); 
                header.closest('.component-watch-settings-panel').classList.remove('active');
                document.getElementById(`settings-${backTarget}`).classList.add('active');
                return;
            }
        });
    }

    _initLightingOptions() {
        const lightingOptions = document.querySelectorAll('#settings-lighting .component-watch-settings-option');
        lightingOptions.forEach(opt => {
            opt.addEventListener('click', () => {
                const val = opt.dataset.value; 
                const isEnabled = val === 'on';
                
                this.ambient.setEnabled(isEnabled);
                
                this.resetMenu();
                this.toggleMenu();
            });
        });
    }

    renderQualityOptions(levels) {
        if (!this.container) return;
        this.container.innerHTML = '';

        const isAutoEnabled = this.player.getAutoLevelEnabled();
        
        const autoOption = this._createOption('-1', 'Automática', isAutoEnabled);
        autoOption.addEventListener('click', () => this.handleQualityChange(-1, 'Automática', autoOption));
        this.container.appendChild(autoOption);

        [...levels].reverse().forEach((level) => {
            const originalIndex = levels.indexOf(level); 
            const height = level.height;
            
            let label = `${height}p`;
            if (height >= 2160) label += ' 4K';
            else if (height >= 1440) label += ' 2K';
            else if (height >= 1080) label += ' FHD';
            else if (height >= 720) label += ' HD';

            const isSelected = !isAutoEnabled && (this.player.getCurrentLevel() === originalIndex);
            
            const option = this._createOption(originalIndex, label, isSelected);
            option.addEventListener('click', () => this.handleQualityChange(originalIndex, label, option));
            this.container.appendChild(option);
        });
    }

    _createOption(val, text, isSelected) {
        const div = document.createElement('div');
        div.className = `component-watch-settings-option ${isSelected ? 'selected' : ''}`;
        div.setAttribute('data-quality', val);
        div.innerHTML = `<span>${text}</span><span class="material-symbols-rounded check-icon">check</span>`;
        return div;
    }

    handleQualityChange(levelIndex, label, element) {
        this.player.setLevel(levelIndex);

        const allOpts = document.querySelectorAll('#quality-options-container .component-watch-settings-option');
        allOpts.forEach(o => o.classList.remove('selected'));
        element.classList.add('selected');

        const statusText = levelIndex === -1 ? 'Auto' : label;
        document.getElementById('quality-status-text').innerText = statusText;

        this.resetMenu();
        this.toggleMenu(); 
    }
}