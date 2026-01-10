<?php
// includes/sections/settings/accessibility.php
?>
<div class="section-content active" data-section="settings/accessibility">
    <div class="component-wrapper">
        
        <div class="component-header-card">
            <h1 class="component-page-title">Accesibilidad</h1>
            <p class="component-page-description">Opciones para mejorar la visibilidad y uso de la aplicación.</p>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item component-group-item--stacked">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Contraste y Tema</h2>
                        <p class="component-card__description">Ajusta el modo de color para reducir la fatiga visual.</p>
                    </div>
                </div>
                
                <div class="component-card__actions">
                    <div class="trigger-select-wrapper" data-trigger="dropdown">
                        <div class="trigger-selector">
                            <span class="material-symbols-rounded trigger-select-icon">contrast</span>
                            <span class="trigger-select-text">Estándar</span>
                            <span class="material-symbols-rounded">expand_more</span>
                        </div>
                        <div class="popover-module">
                            <div class="menu-list">
                                <div class="menu-link active" data-action="select-option" data-value="standard" data-label="Estándar">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">contrast</span></div>
                                    <div class="menu-link-text">Estándar</div>
                                </div>
                                <div class="menu-link" data-action="select-option" data-value="high-contrast" data-label="Alto Contraste">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">contrast_rtl_off</span></div>
                                    <div class="menu-link-text">Alto Contraste</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>