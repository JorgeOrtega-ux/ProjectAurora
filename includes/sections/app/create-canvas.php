<?php
// includes/sections/app/create-canvas.php
$username = $_SESSION['username'] ?? 'usuario';
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$baseUrl = "$protocol://$host/ProjectAurora"; 
?>

<div class="component-wrapper" data-section="app/create-canvas">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            <div class="component-toolbar__side">
                <span class="component-toolbar-title">
                    <span class="material-symbols-rounded" style="margin-right: 8px;">add_photo_alternate</span>
                    Nuevo Lienzo
                </span>
            </div>
            <div class="component-toolbar__side">
                <button type="button" class="component-button primary" id="btn-create-canvas">
                    <span class="material-symbols-rounded">check</span>
                    <span class="btn-text-responsive">Crear Lienzo</span>
                </button>
            </div>
        </div>
    </div>

    <div class="component-header-card">
        <h1 class="component-page-title">Configurar Lienzo</h1>
        <p class="component-page-description">Define las propiedades de tu nuevo espacio de arte.</p>
    </div>

    <div class="component-card component-card--grouped">
        
        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__icon-container component-card__icon-container--bordered">
                    <span class="material-symbols-rounded">aspect_ratio</span>
                </div>
                <div class="component-card__text">
                    <h2 class="component-card__title">Tamaño del Lienzo</h2>
                    <p class="component-card__description">Dimensiones de la cuadrícula de píxeles.</p>
                </div>
            </div>
            
            <div class="component-card__actions">
                <div class="trigger-select-wrapper" data-trigger="dropdown">
                    <div class="trigger-selector">
                        <span class="material-symbols-rounded trigger-select-icon">grid_on</span>
                        <span class="trigger-select-text" id="display-size">64 x 64 Píxeles</span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>
                    
                    <div class="popover-module">
                        <div class="menu-content menu-content--flush">
                            <div class="menu-list">
                                <div class="menu-link active" data-action="select-size" data-value="64">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">grid_on</span></div>
                                    <div class="menu-link-text">64 x 64 Píxeles</div>
                                </div>
                                <div class="menu-link" data-action="select-size" data-value="128">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">grid_4x4</span></div>
                                    <div class="menu-link-text">128 x 128 Píxeles</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="component-divider">

        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content">
                <div class="component-card__icon-container component-card__icon-container--bordered">
                    <span class="material-symbols-rounded">link</span>
                </div>
                <div class="component-card__text">
                    <h2 class="component-card__title">Dirección Web</h2>
                    <p class="component-card__description">Tu lienzo será accesible mediante esta URL.</p>
                </div>
            </div>
            
            <div class="w-100">
                 <div class="component-input-wrapper">
                    <input type="text" class="component-text-input" value="<?php echo "$baseUrl/$username"; ?>" readonly style="background-color: var(--bg-hover-light); color: var(--text-secondary); cursor: not-allowed;">
                </div>
            </div>
        </div>

        <hr class="component-divider">

        <div class="component-group-item component-group-item--stacked">
            <div class="component-card__content w-100">
                <div class="component-card__icon-container component-card__icon-container--bordered">
                    <span class="material-symbols-rounded">lock</span>
                </div>
                <div class="component-card__text">
                    <h2 class="component-card__title">Privacidad</h2>
                    <p class="component-card__description">Controla quién puede pintar en tu lienzo.</p>
                </div>
            </div>
            
            <div class="component-card__actions">
                <div class="trigger-select-wrapper" data-trigger="dropdown">
                    <div class="trigger-selector" id="trigger-privacy">
                        <span class="material-symbols-rounded trigger-select-icon">public</span>
                        <span class="trigger-select-text" id="display-privacy">Público</span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>
                    
                    <div class="popover-module">
                        <div class="menu-content menu-content--flush">
                            <div class="menu-list">
                                <div class="menu-link active" data-action="select-privacy" data-value="public" data-icon="public" data-label="Público">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">public</span></div>
                                    <div class="menu-link-text">Público</div>
                                </div>
                                <div class="menu-link" data-action="select-privacy" data-value="private" data-icon="vpn_key" data-label="Privado">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">vpn_key</span></div>
                                    <div class="menu-link-text">Privado</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
        </div>

        <div id="privacy-info-private" class="component-group-item component-group-item--stacked d-none" style="border-top: 1px solid var(--border-light);">
             <div class="component-card__content w-100">
                <div class="component-card__icon-container component-card__icon-container--bordered">
                    <span class="material-symbols-rounded">key</span>
                </div>
                <div class="component-card__text">
                    <h2 class="component-card__title">Código de Acceso</h2>
                    <p class="component-card__description">Comparte este código solo con tus invitados.</p>
                </div>
            </div>

            <div class="w-100">
                <div class="component-input-wrapper">
                    <div style="position: relative; display: flex; gap: 8px;">
                        <input type="text" id="access-code-display" class="component-text-input font-mono" readonly value="" style="letter-spacing: 2px; text-align: center; font-weight: bold;">
                        <button type="button" class="component-button" id="btn-refresh-code" title="Generar nuevo código" style="padding: 0 12px;">
                            <span class="material-symbols-rounded">refresh</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>