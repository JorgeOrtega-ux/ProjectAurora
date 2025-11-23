<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<div class="section-content active" data-section="settings/sessions">
    <div class="component-wrapper">

        <div class="component-header-card">
            <div class="auth-back-link" style="margin-bottom: 15px; text-align: left;">
                <a href="#" data-nav="settings/login-security" style="color:#666; text-decoration:none; display:flex; align-items:center; gap:5px; font-size:14px;">
                    <span class="material-symbols-rounded" style="font-size:18px;">arrow_back</span> 
                    <span data-i18n="global.back"><?php echo trans('global.back'); ?></span>
                </a>
            </div>
            <h1 class="component-page-title" data-i18n="settings.sessions.title"><?php echo trans('settings.sessions.title'); ?></h1>
            <p class="component-page-description" data-i18n="settings.sessions.description"><?php echo trans('settings.sessions.description'); ?></p>
        </div>

        <div id="sessions-list-container">
            <div style="text-align: center; padding: 40px;">
                <div class="small-spinner"></div>
            </div>
        </div>

        <div class="component-card component-card--danger" style="margin-top: 20px;">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.sessions.logout_all"><?php echo trans('settings.sessions.logout_all'); ?></h2>
                    <p class="component-card__description" data-i18n="settings.sessions.logout_all_confirm"><?php echo trans('settings.sessions.logout_all_confirm'); ?></p>
                </div>
            </div>
            <div class="component-card__actions actions-right">
                <button type="button" class="component-button danger" data-action="revoke-all-sessions">
                    <span class="material-symbols-rounded" style="font-size: 18px;">logout</span>
                    <span data-i18n="settings.sessions.logout_all"><?php echo trans('settings.sessions.logout_all'); ?></span>
                </button>
            </div>
        </div>

    </div>
</div>