import { ToastManager } from '../../core/components/toast-manager.js';

export class PlayerSettings {
    constructor(playerInstance, ambientInstance) {
        this.player = playerInstance;
        this.ambient = ambientInstance;
        
        // DOM Elements
        this.settingsBtn = document.getElementById('settings-btn');
        this.popover = document.getElementById('settings-popover');
        this.qualityContainer = document.getElementById('quality-options-container');
        this.panels = document.querySelectorAll('.menu-content'); 
        
        // Status Text Elements
        this.lightingStatus = document.getElementById('lighting-status-text');
        this.qualityStatus = document.getElementById('quality-status-text');
        
        this.init();
    }

    init() {
        if (!this.settingsBtn || !this.popover) return;

        // Toggle Popover
        this.settingsBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.togglePopover();
        });

        // Close on click outside
        document.addEventListener('click', (e) => {
            if (this.popover.classList.contains('active') && 
                !this.popover.contains(e.target) && 
                e.target !== this.settingsBtn) {
                this.closePopover();
            }
        });

        // Navigation between panels
        this._initNavigation();
        
        // Initialize Lighting Settings
        this._initLightingSettings();

        // Listen for levels loaded (from VideoPlayer)
        this.player.onLevelsLoaded = (levels, isNative) => {
            this._renderQualityOptions(levels, isNative);
        };
    }

    togglePopover() {
        const isActive = this.popover.classList.contains('active');
        if (isActive) {
            this.closePopover();
        } else {
            this.popover.classList.add('active');
            this.popover.style.display = 'block'; 
            this._showPanel('settings-main'); 
        }
    }

    closePopover() {
        this.popover.classList.remove('active');
        this.popover.style.display = 'none'; 
    }

    _initNavigation() {
        // Main items click (Items que abren submenús)
        const navItems = this.popover.querySelectorAll('.menu-link[data-target]');
        navItems.forEach(item => {
            item.addEventListener('click', () => {
                const target = item.dataset.target; 
                if (target) {
                    this._showPanel(`settings-${target}`);
                }
            });
        });

        // Back buttons click (Ahora están en .menu-header)
        const backItems = this.popover.querySelectorAll('.menu-header[data-back]');
        backItems.forEach(item => {
            item.addEventListener('click', () => {
                const backTarget = item.dataset.back;
                if (backTarget) {
                    this._showPanel(`settings-${backTarget}`);
                }
            });
        });
    }

    _showPanel(panelId) {
        this.panels.forEach(p => {
            p.classList.remove('active');
            p.style.display = 'none'; 
        });
        
        const target = document.getElementById(panelId);
        if (target) {
            target.classList.add('active');
            target.style.display = 'block'; 
        }
    }

    // --- LIGHTING LOGIC ---
    _initLightingSettings() {
        const lightingOptions = document.querySelectorAll('[data-type="lighting"]');
        
        // Load preference
        const saved = localStorage.getItem('aurora_ambient_mode') || 'off';
        this._updateLightingUI(saved);
        
        if (this.ambient) this.ambient.setEnabled(saved === 'on');

        lightingOptions.forEach(opt => {
            opt.addEventListener('click', () => {
                const val = opt.dataset.value;
                this._updateLightingUI(val);
                
                if (this.ambient) this.ambient.setEnabled(val === 'on');
                
                // Save & Go back
                localStorage.setItem('aurora_ambient_mode', val);
                setTimeout(() => this._showPanel('settings-main'), 200);
            });
        });
    }

    _updateLightingUI(val) {
        document.querySelectorAll('[data-type="lighting"]').forEach(opt => {
            const checkIcon = opt.querySelector('.check-icon');
            if (opt.dataset.value === val) {
                opt.classList.add('active'); 
                if(checkIcon) checkIcon.style.display = 'block';
            } else {
                opt.classList.remove('active');
                if(checkIcon) checkIcon.style.display = 'none';
            }
        });
        this.lightingStatus.innerText = (val === 'on') ? 'Activo' : 'Desactivado';
    }

    // --- QUALITY LOGIC ---
    _renderQualityOptions(levels, isNative) {
        this.qualityContainer.innerHTML = '';

        if (isNative) {
            this.qualityStatus.innerText = 'Auto (Nativo)';
            const nativoEl = this._createQualityOption('Automático (Nativo)', -1, true);
            this.qualityContainer.appendChild(nativoEl);
            return;
        }

        if (!levels || levels.length === 0) {
            this.qualityStatus.innerText = 'Auto';
             const autoEl = this._createQualityOption('Automático', -1, true);
             autoEl.addEventListener('click', () => this._handleQualitySelect(-1));
             this.qualityContainer.appendChild(autoEl);
            return;
        }

        // Add "Auto" option
        const autoEl = this._createQualityOption('Automático', -1, false);
        autoEl.addEventListener('click', () => this._handleQualitySelect(-1));
        this.qualityContainer.appendChild(autoEl);

        // Add Levels
        levels.forEach((lvl, index) => {
            const label = lvl.height ? `${lvl.height}p` : `Nivel ${index}`;
            const el = this._createQualityOption(label, index, false);
            el.addEventListener('click', () => this._handleQualitySelect(index));
            this.qualityContainer.appendChild(el);
        });
        
        this._updateQualityUI(this.player.hls.currentLevel);
    }

    _createQualityOption(label, index, isSelected) {
        const div = document.createElement('div');
        div.className = 'menu-link';
        div.style.cssText = 'display: flex; align-items: center; justify-content: space-between; cursor: pointer;';
        div.dataset.qualityIndex = index;

        // Estructura estricta: Icono Equalizer + Texto + Icono Check
        div.innerHTML = `
            <div class="menu-link-icon">
                <span class="material-symbols-rounded">equalizer</span>
            </div>
            <div class="menu-link-text">${label}</div>
            <div class="menu-link-icon">
                <span class="material-symbols-rounded check-icon" style="display: ${isSelected ? 'block' : 'none'};">check</span>
            </div>
        `;
        return div;
    }

    _handleQualitySelect(index) {
        this.player.setLevel(index);
        this._updateQualityUI(index);
        setTimeout(() => this._showPanel('settings-main'), 200);
    }

    _updateQualityUI(levelIndex) {
        // Reset check icons
        const options = this.qualityContainer.children;
        for (let i = 0; i < options.length; i++) {
            const opt = options[i];
            const check = opt.querySelector('.check-icon');
            if(check) check.style.display = 'none';
        }

        // Determine active option
        let activeOpt = null;
        if (levelIndex === -1) {
             activeOpt = options[0];
             this.qualityStatus.innerText = 'Auto';
        } else {
             activeOpt = options[levelIndex + 1];
             const height = this.player.levels[levelIndex]?.height;
             this.qualityStatus.innerText = height ? `${height}p` : `Nivel ${levelIndex}`;
        }

        if (activeOpt) {
            const check = activeOpt.querySelector('.check-icon');
            if(check) check.style.display = 'block';
        }
    }
}