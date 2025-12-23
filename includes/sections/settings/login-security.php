<div class="section-content active" data-section="settings/login-security">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title">Inicio de sesión y seguridad</h1>
            <p class="component-page-description">Mantén tu cuenta protegida.</p>
        </div>

        <div class="component-card component-card--grouped">

            <div class="component-group-item" data-component="password-update-section">
                
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">lock</span>
                    </div>

                    <div class="component-card__text">
                        <h2 class="component-card__title">Contraseña</h2>
                        <p class="component-card__description" style="color: #666;">Se recomienda usar una contraseña segura.</p>
                    </div>
                </div>

                <div class="component-card__actions actions-right active" data-state="password-stage-0">
                    <button type="button" class="component-button" data-action="pass-start-flow">
                        Cambiar contraseña
                    </button>
                </div>

                <div class="w-100 component-stage-form disabled" data-state="password-stage-1">
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input" id="current-password-input" placeholder="Contraseña actual">
                    </div>

                    <div class="component-card__actions actions-right actions-force-end">
                        <button type="button" class="component-button" data-action="pass-cancel-flow">Cancelar</button>
                        <button type="button" class="component-button primary" data-action="pass-go-step-2">Continuar</button>
                    </div>
                </div>

                <div class="w-100 component-stage-form disabled" data-state="password-stage-2">
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input" id="new-password-input" placeholder="Nueva contraseña">
                    </div>
                    <div class="component-input-wrapper">
                        <input type="password" class="component-text-input" id="repeat-password-input" placeholder="Confirmar contraseña">
                    </div>

                    <div class="component-card__actions actions-right actions-force-end">
                        <button type="button" class="component-button" data-action="pass-cancel-flow">Cancelar</button>
                        <button type="button" class="component-button primary" data-action="pass-submit-final">Guardar</button>
                    </div>
                </div>
            </div>
            
            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">shield</span>
                    </div>

                    <div class="component-card__text">
                        <h2 class="component-card__title">Autenticación en dos pasos</h2>
                        <p class="component-card__description">
                            Desactivado (Recomendado)
                        </p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button primary" data-nav="settings/2fa-setup">
                        Activar
                    </button>
                </div>
            </div>

        </div>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">devices</span>
                    </div>

                    <div class="component-card__text">
                        <h2 class="component-card__title">Dispositivos</h2>
                        <p class="component-card__description">Gestiona tus sesiones activas.</p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" data-nav="settings/devices">
                        Gestionar
                    </button>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" style="color: #d32f2f;">Eliminar cuenta</h2>
                        <p class="component-card__description">
                            Esta acción es irreversible y eliminará todos tus datos.
                        </p>
                    </div>
                </div>

                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" style="color: #d32f2f; border-color: rgba(211, 47, 47, 0.3);" data-nav="settings/delete-account">
                        Eliminar cuenta
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>