<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<div class="section-content active" data-section="settings/accessibility">
    <div class="component-wrapper">

        <div class="component-header-card">
            <h1 class="component-page-title" data-i18n="settings.accessibility.title"><?php echo trans('settings.accessibility.title'); ?></h1>
            <p class="component-page-description" data-i18n="settings.accessibility.description"><?php echo trans('settings.accessibility.description'); ?></p>
        </div>

        <div class="component-card component-card--column">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.accessibility.theme_title"><?php echo trans('settings.accessibility.theme_title'); ?></h2>
                    <p class="component-card__description" data-i18n="settings.accessibility.theme_desc"><?php echo trans('settings.accessibility.theme_desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions">
                <div class="trigger-select-wrapper">
                    <div class="trigger-selector" data-action="toggleModuleThemeSelect">
                        <div class="trigger-select-icon">
                            <span class="material-symbols-rounded">desktop_windows</span>
                        </div>
                        <div class="trigger-select-text">
                            <span data-i18n="settings.accessibility.theme_options.system"><?php echo trans('settings.accessibility.theme_options.system'); ?></span>
                        </div>
                        <div class="trigger-select-arrow">
                            <span class="material-symbols-rounded">arrow_drop_down</span>
                        </div>
                    </div>

                    <div class="popover-module popover-module--anchor-width body-title disabled" data-module="moduleThemeSelect" data-preference-type="theme">
                        <div class="menu-content">
                            <div class="menu-list">
                                
                                <div class="menu-link active" data-value="system">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">desktop_windows</span></div>
                                    <div class="menu-link-text"><span data-i18n="settings.accessibility.theme_options.system"><?php echo trans('settings.accessibility.theme_options.system'); ?></span></div>
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">check</span></div>
                                </div>

                                <div class="menu-link" data-value="light">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">light_mode</span></div>
                                    <div class="menu-link-text"><span data-i18n="settings.accessibility.theme_options.light"><?php echo trans('settings.accessibility.theme_options.light'); ?></span></div>
                                    <div class="menu-link-icon"></div>
                                </div>

                                <div class="menu-link" data-value="dark">
                                    <div class="menu-link-icon"><span class="material-symbols-rounded">dark_mode</span></div>
                                    <div class="menu-link-text"><span data-i18n="settings.accessibility.theme_options.dark"><?php echo trans('settings.accessibility.theme_options.dark'); ?></span></div>
                                    <div class="menu-link-icon"></div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--edit-mode">
            <div class="component-card__content">
                <div class="component-card__text">
                    <h2 class="component-card__title" data-i18n="settings.accessibility.msg_time_title"><?php echo trans('settings.accessibility.msg_time_title'); ?></h2>
                    <p class="component-card__description" data-i18n="settings.accessibility.msg_time_desc"><?php echo trans('settings.accessibility.msg_time_desc'); ?></p>
                </div>
            </div>
            <div class="component-card__actions actions-right">
                <label class="component-toggle-switch">
                    <input type="checkbox" data-element="toggle-msg-persistence" data-preference-type="boolean" data-field-name="extended_message_time">
                    <span class="component-toggle-slider"></span>
                </label>
            </div>
        </div>

    </div>
</div>