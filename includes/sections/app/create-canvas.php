<?php
// includes/sections/app/create-canvas.php
// Obtener el nombre de usuario para la URL
$username = $_SESSION['username'] ?? 'usuario';
// Detectar IP o Dominio actual para mostrar en el input (o dejar fijo si prefieres)
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

    <div class="component-header-card mt-24">
        <h1 class="component-page-title">Configurar Lienzo</h1>
        <p class="component-page-description">Define las propiedades de tu nuevo espacio de arte.</p>
    </div>

    <div class="component-card component-card--grouped">
        
        <div class="component-group-item">
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
                    <div class="trigger-selector" id="trigger-size">
                        <span class="trigger-select-text" id="display-size">64 x 64</span>
                        <span class="material-symbols-rounded">expand_more</span>
                    </div>
                    
                    <div class="popover-module">
                        <div class="menu-content menu-content--flush">
                            <div class="menu-list">
                                <div class="menu-link active" data-action="select-size" data-value="64">
                                    <div class="menu-link-text">64 x 64 Píxeles</div>
                                </div>
                                <div class="menu-link" data-action="select-size" data-value="128">
                                    <div class="menu-link-text">128 x 128 Píxeles</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="component-divider">

        <div class="component-group-item">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title">Dirección Web</h2>
                    <p class="component-card__description">Tu lienzo será accesible mediante esta URL única.</p>
                    
                    <div class="component-input-wrapper mt-16">
                        <input type="text" class="component-text-input" value="<?php echo "$baseUrl/$username"; ?>" readonly style="background-color: var(--bg-hover-light); color: var(--text-secondary); cursor: not-allowed;">
                    </div>
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
                    <h2 class="component-card__title">Privacidad y Acceso</h2>
                    <p class="component-card__description">Controla quién puede pintar en tu lienzo.</p>
                </div>
            </div>

            <div class="component-card__actions w-100 mt-16">
                <div class="trigger-select-wrapper w-100" style="max-width: 100%;" data-trigger="dropdown">
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

            <div id="privacy-info-public" class="component-message component-message--success w-100" style="margin-top: 12px; background-color: var(--bg-hover-light); color: var(--text-secondary); border: 1px solid var(--border-light);">
                <div style="display: flex; gap: 12px; align-items: center;">
                    <span class="material-symbols-rounded">info</span>
                    <span>Cualquiera con el enlace podrá entrar y pintar en tu lienzo.</span>
                </div>
            </div>

            <div id="privacy-info-private" class="d-none w-100" style="margin-top: 12px;">
                <div class="component-input-wrapper">
                    <label style="font-size: 13px; color: var(--text-secondary); margin-bottom: 6px; display: block;">Código de acceso (12 dígitos)</label>
                    <div style="position: relative;">
                        <input type="text" id="access-code-display" class="component-text-input font-mono" readonly value="" style="letter-spacing: 2px; text-align: center; font-weight: bold;">
                        <button type="button" class="component-input-action" id="btn-refresh-code" title="Generar nuevo código">
                            <span class="material-symbols-rounded">refresh</span>
                        </button>
                    </div>
                    <p style="font-size: 12px; color: var(--text-tertiary); margin-top: 6px;">
                        Comparte este código solo con las personas que quieras invitar.
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>