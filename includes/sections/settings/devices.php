<?php
// includes/sections/settings/devices.php
// Esta vista se carga vía AJAX y el controlador de seguridad inyecta los datos.
?>
<div class="section-content active" data-section="settings/devices">
    <div class="component-wrapper">
        
        <div class="component-header-card" style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1 class="component-page-title"><?= __('settings.devices.title') ?></h1>
                <p class="component-page-description"><?= __('settings.devices.desc') ?></p>
            </div>
        </div>

        <div style="margin-bottom: 16px;">
             <button class="component-button" data-nav="settings/login-and-security">
                <span class="material-symbols-rounded">arrow_back</span> <?= __('global.back') ?>
             </button>
        </div>

        <div class="component-card component-card--grouped">
            
            <div id="devices-list-container">
                <div class="loader-container">
                    <div class="spinner"></div>
                </div>
            </div>

            <div class="component-group-item" style="background-color: #ffebee;">
                <div class="component-card__content">
                    <div class="component-card__text">
                        <h2 class="component-card__title" style="color: #d32f2f;"><?= __('settings.devices.logout_others_title') ?></h2>
                        <p class="component-card__description"><?= __('settings.devices.logout_others_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <button class="component-button danger" data-action="revoke-all">
                        <?= __('global.close_all') ?>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>