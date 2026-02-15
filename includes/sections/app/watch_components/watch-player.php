<div class="component-watch-player-card" id="video-container">
    <canvas id="ambient-canvas" class="component-watch-ambient-canvas"></canvas>

    <div class="component-watch-player-wrapper">
        <video id="main-player" playsinline poster="" class="component-watch-video-element"></video>
        
        <div id="buffering-spinner" class="component-watch-buffering-indicator">
            <div class="component-watch-spinner-circle"></div>
        </div>

        <div id="scrub-tooltip" class="component-watch-scrub-tooltip">
            <div class="component-watch-scrub-img-wrapper">
                <div class="component-watch-scrub-preview"></div>
            </div>
            <div class="component-watch-scrub-time-pill">
                <span class="component-watch-scrub-time">0:00</span>
            </div>
        </div>
    </div>

    <div class="component-watch-controls" id="custom-controls">
        <div class="component-watch-progress-container">
            <div class="component-watch-progress-hover"></div>
            <input type="range" id="seek-bar" class="component-watch-seek-bar" value="0" min="0" step="0.1">
        </div>

        <div class="component-watch-controls-row">
            <div class="component-watch-controls-left">
                <div class="component-watch-control-pill">
                    <button id="play-pause-btn" class="component-watch-control-btn" title="Reproducir">
                        <span class="material-symbols-rounded">play_arrow</span>
                    </button>
                </div>
                <div class="component-watch-control-pill component-watch-volume-container">
                    <div class="component-watch-volume-box">
                        <button id="mute-btn" class="component-watch-control-btn" title="Volumen">
                            <span class="material-symbols-rounded">volume_up</span>
                        </button>
                        <div class="component-watch-volume-expander">
                            <div class="component-watch-volume-slider-wrap">
                                <input type="range" id="volume-bar" class="component-watch-volume-bar" min="0" max="1" step="0.05" value="1">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="component-watch-control-pill component-watch-timer-pill">
                    <div class="component-watch-timer-block">
                        <span id="current-time">0:00</span><span class="component-watch-time-sep">/</span><span id="duration">0:00</span>
                    </div>
                </div>
            </div>

            <div class="component-watch-controls-right">
                <div class="component-watch-control-pill component-watch-group-pill" style="position: relative;">
                    
                    <div id="settings-popover" class="popover-module popover-module--searchable popover-module--upwards popover-module--dark" style="width: 280px; display: none;">
                        
                        <div id="settings-main" class="menu-content menu-content--flush active">
                            <div class="menu-list menu-list--scrollable">
                                <div class="menu-link" data-target="lighting" style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">light_mode</span>
                                    </div>
                                    <div class="menu-link-text">
                                        Iluminación <span id="lighting-status-text" style="opacity: 0.6; font-size: 0.85em; margin-left: 6px;">Off</span>
                                    </div>
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">chevron_right</span>
                                    </div>
                                </div>

                                <div class="menu-link" data-target="quality" style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">tune</span>
                                    </div>
                                    <div class="menu-link-text">
                                        Calidad <span id="quality-status-text" style="opacity: 0.6; font-size: 0.85em; margin-left: 6px;">Auto</span>
                                    </div>
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">chevron_right</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="settings-lighting" class="menu-content menu-content--flush" style="display: none;">
                            <div class="menu-search-header">
                                <div class="menu-list">
                                    <div class="menu-link" data-back="main" style="cursor: pointer; display: flex; align-items: center;">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded">arrow_back</span>
                                        </div>
                                        <div class="menu-link-text">Iluminación</div>
                                        <div class="menu-link-icon"></div> </div>
                                </div>
                            </div>

                            <div class="menu-list menu-list--scrollable">
                                <div class="menu-link" data-type="lighting" data-value="off" style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">light_off</span>
                                    </div>
                                    <div class="menu-link-text">Desactivado</div>
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded check-icon" style="display: none;">check</span>
                                    </div>
                                </div>
                                <div class="menu-link" data-type="lighting" data-value="on" style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded">light_mode</span>
                                    </div>
                                    <div class="menu-link-text">Activo</div>
                                    <div class="menu-link-icon">
                                        <span class="material-symbols-rounded check-icon" style="display: none;">check</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="settings-quality" class="menu-content menu-content--flush" style="display: none;">
                            <div class="menu-search-header">
                                <div class="menu-list">
                                    <div class="menu-link" data-back="main" style="cursor: pointer; display: flex; align-items: center;">
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded">arrow_back</span>
                                        </div>
                                        <div class="menu-link-text">Calidad</div>
                                        <div class="menu-link-icon"></div> </div>
                                </div>
                            </div>
                            
                            <div class="menu-list menu-list--scrollable" id="quality-options-container">
                                </div>
                        </div>

                    </div>
                    <button id="settings-btn" class="component-watch-control-btn" title="Configuración">
                        <span class="material-symbols-rounded">settings</span>
                    </button>
                    <button id="cinema-mode-btn" class="component-watch-control-btn" title="Modo Cine">
                        <span class="material-symbols-rounded">crop_landscape</span>
                    </button>
                    <button id="fullscreen-btn" class="component-watch-control-btn" title="Pantalla Completa">
                        <span class="material-symbols-rounded">fullscreen</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>