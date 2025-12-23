<div class="section-content active" data-section="settings/delete-account">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title" style="color: #d32f2f;">Eliminar cuenta</h1>
            <p class="component-page-description">Lamentamos que quieras irte. Por favor lee esto con atención.</p>
        </div>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered" style="color: #d32f2f; background: #ffebee; border-color: #ffcdd2;">
                        <span class="material-symbols-rounded">warning</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">¿Qué implica esto?</h2>
                        <p class="component-card__description">
                            Tu perfil dejará de ser visible, se cerrarán todas tus sesiones activas y perderás acceso inmediato a tu cuenta. 
                            <br><br>
                            <strong>Esta acción no se puede deshacer fácilmente desde la interfaz.</strong>
                        </p>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title">Entiendo las consecuencias</h2>
                        <p class="component-card__description">Confirmo que quiero desactivar mi cuenta permanentemente.</p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <label class="component-toggle-switch">
                        <input type="checkbox" id="check-confirm-delete">
                        <span class="component-toggle-slider"></span>
                    </label>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item component-group-item--stacked disabled" id="delete-confirmation-area">
                <div class="component-card__content w-100">
                    <div class="component-card__text w-100">
                        <h2 class="component-card__title">Confirma tu contraseña</h2>
                        <p class="component-card__description">Por seguridad, ingresa tu contraseña para continuar.</p>
                        
                        <div class="component-input-wrapper mt-16">
                            <input type="password" class="component-text-input" id="delete-password-input" placeholder="Tu contraseña actual">
                        </div>
                    </div>
                </div>

                <div class="component-card__actions actions-right w-100">
                    <button type="button" class="component-button" id="btn-delete-final" style="background-color: #d32f2f; color: white; border: none; width: 100%;">
                        Eliminar mi cuenta definitivamente
                    </button>
                </div>
            </div>

        </div>

        <div class="component-card">
            <button class="component-button" data-nav="settings/login-security">
                Cancelar y volver
            </button>
        </div>

    </div>
</div>