<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<div class="view-content" id="devices-view-container">
    <input type="hidden" id="csrf_token_settings" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
    
    <div class="component-wrapper">
        <div class="component-header-card">
            <h1 class="component-page-title"><?= t('settings.security.devices_title') ?? 'Tus dispositivos' ?></h1>
            <p class="component-page-description"><?= t('settings.security.devices_desc') ?? 'Administra los dispositivos en los que has iniciado sesión actualmente.' ?></p>
        </div>

        <div class="component-card--grouped">
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">phonelink_erase</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Cerrar otras sesiones</h2>
                        <p class="component-card__description">Cierra la sesión en todos los demás dispositivos excepto en el que estás usando ahora.</p>
                    </div>
                </div>
                
                <div class="component-card__actions actions-right">
                    <button type="button" class="component-button" id="btn-revoke-all">
                        Cerrar demás sesiones
                    </button>
                </div>
            </div>
        </div>

        <h3>Sesiones activas</h3>

        <div class="component-card--grouped" id="devices-list-container">
            <div>
                <div class="component-spinner-button dark-spinner"></div>
            </div>
        </div>

    </div>
</div>