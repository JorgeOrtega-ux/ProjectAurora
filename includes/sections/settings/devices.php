<div class="section-content active" data-section="settings/devices">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title">Dispositivos</h1>
            <p class="component-page-description">Aquí verás los dispositivos en los que has iniciado sesión.</p>
        </div>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Cerrar todas las sesiones</h2>
                        <p class="component-card__description">Se cerrará la sesión en todos los dispositivos, incluido este.</p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" id="btn-revoke-all" style="color: #d32f2f; border-color: rgba(211, 47, 47, 0.3);">
                        Cerrar todas
                    </button>
                </div>
            </div>

            <hr class="component-divider">

            <div id="devices-list-container">
                <div style="padding: 24px; text-align: center;">
                    <div class="spinner-sm" style="border-color: #000; border-left-color: transparent;"></div>
                </div>
            </div>

        </div>

        <div class="component-card">
            <button class="component-button" data-nav="settings/login-security">
                <span class="material-symbols-rounded">arrow_back</span>
                Volver
            </button>
        </div>

    </div>
</div>