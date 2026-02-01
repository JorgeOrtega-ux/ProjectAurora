<?php
// includes/sections/admin/server-config.php

require_once __DIR__ . '/../../libs/Utils.php';
require_once __DIR__ . '/../../../api/services/AdminService.php';

$currentAdminId = $_SESSION['user_id'] ?? 0;
$adminService = new AdminService($pdo, $i18n, $currentAdminId);
$res = $adminService->getServerConfigAll();
$config = $res['success'] ? $res['config'] : [];

function conf($key, $default = '', $configArray) {
    return isset($configArray[$key]) ? htmlspecialchars($configArray[$key]) : $default;
}

function isChecked($key, $configArray) {
    return (isset($configArray[$key]) && $configArray[$key] == '1') ? 'checked' : '';
}

function renderStepper($label, $desc, $name, $value, $stepSmall = 1, $stepLarge = 10) {
    ?>
    <div class="component-group-item component-group-item--stacked">
        <div class="component-card__content w-100">
            <div class="component-card__text">
                <h2 class="component-card__title"><?php echo $label; ?></h2>
                <p class="component-card__description"><?php echo $desc; ?></p>
            </div>
        </div>
        <div class="component-card__actions w-100">
            <div class="stepper-control" data-role="stepper" data-step-small="<?php echo $stepSmall; ?>" data-step-large="<?php echo $stepLarge; ?>">
                <div class="stepper-side left">
                    <button type="button" class="component-button stepper-btn" data-action="dec-large" title="-<?php echo $stepLarge; ?>">
                        <span class="material-symbols-rounded">keyboard_double_arrow_left</span>
                    </button>
                    <button type="button" class="component-button stepper-btn" data-action="dec-small" title="-<?php echo $stepSmall; ?>">
                        <span class="material-symbols-rounded">chevron_left</span>
                    </button>
                </div>
                <div class="stepper-center">
                    <input type="number" class="component-text-input stepper-input" name="<?php echo $name; ?>" value="<?php echo $value; ?>">
                </div>
                <div class="stepper-side right">
                    <button type="button" class="component-button stepper-btn" data-action="inc-small" title="+<?php echo $stepSmall; ?>">
                        <span class="material-symbols-rounded">chevron_right</span>
                    </button>
                    <button type="button" class="component-button stepper-btn" data-action="inc-large" title="+<?php echo $stepLarge; ?>">
                        <span class="material-symbols-rounded">keyboard_double_arrow_right</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <hr class="component-divider">
    <?php
}
?>

<div class="component-wrapper" data-section="admin-server-config">
    
    <div class="component-toolbar-wrapper">
        <div class="component-toolbar component-toolbar--primary">
            <div class="toolbar-group">
                <div class="component-toolbar__side component-toolbar__side--left">
                    <div class="component-toolbar-title"><?php echo $i18n->t('admin.server.title'); ?></div>
                </div>
                <div class="component-toolbar__side component-toolbar__side--right">
                    <button class="header-button" data-nav="admin/redis" data-tooltip="<?php echo $i18n->t('admin.server.toolbar.redis'); ?>">
                        <span class="material-symbols-rounded" style="color: #d32f2f;">memory</span>
                    </button>

                    <button class="component-button primary" id="btn-save-server-config">
                        <span class="material-symbols-rounded" style="font-size: 18px;">save</span>
                        <?php echo $i18n->t('global.save'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="component-header-card mt-4">
        <h1 class="component-page-title"><?php echo $i18n->t('menu.admin.server'); ?></h1>
        <p class="component-page-description"><?php echo $i18n->t('admin.server.desc'); ?></p>
    </div>

    <div class="component-card component-card--grouped mt-4">
        <div class="component-accordion-item" data-accordion-id="access">
            <div class="component-group-item component-accordion-header">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">admin_panel_settings</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('admin.server.access.title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('admin.server.access.desc'); ?></p>
                    </div>
                </div>
                <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
            </div>

            <div class="component-accordion-content">
                <hr class="component-divider">
                
                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?php echo $i18n->t('admin.server.access.maintenance_mode'); ?></h2>
                            <p class="component-card__description"><?php echo $i18n->t('admin.server.access.maintenance_desc'); ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <label class="component-toggle-switch">
                            <input type="checkbox" name="maintenance_mode" <?php echo isChecked('maintenance_mode', $config); ?>>
                            <span class="component-toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?php echo $i18n->t('admin.server.access.registrations'); ?></h2>
                            <p class="component-card__description"><?php echo $i18n->t('admin.server.access.registrations_desc'); ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <label class="component-toggle-switch">
                            <input type="checkbox" name="allow_registrations" <?php echo isChecked('allow_registrations', $config); ?>>
                            <span class="component-toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?php echo $i18n->t('admin.server.access.login'); ?></h2>
                            <p class="component-card__description"><?php echo $i18n->t('admin.server.access.login_desc'); ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <label class="component-toggle-switch">
                            <input type="checkbox" name="allow_login" <?php echo isChecked('allow_login', $config); ?>>
                            <span class="component-toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <hr class="component-divider">

        <div class="component-accordion-item" data-accordion-id="security">
            <div class="component-group-item component-accordion-header">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">shield</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('admin.server.security.title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('admin.server.security.desc'); ?></p>
                    </div>
                </div>
                <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
            </div>

            <div class="component-accordion-content">
                <hr class="component-divider">

                <?php 
                renderStepper(
                    $i18n->t('admin.server.security.login_attempts'), 
                    $i18n->t('admin.server.security.login_attempts_desc'), 
                    'security_login_max_attempts', 
                    conf('security_login_max_attempts', 5, $config), 1, 5
                );
                renderStepper(
                    $i18n->t('admin.server.security.block_duration'), 
                    $i18n->t('admin.server.security.block_duration_desc'), 
                    'security_block_duration', 
                    conf('security_block_duration', 15, $config), 1, 15
                );
                renderStepper(
                    $i18n->t('admin.server.security.rate_limit'), 
                    $i18n->t('admin.server.security.rate_limit_desc'), 
                    'security_general_rate_limit', 
                    conf('security_general_rate_limit', 10, $config), 1, 10
                );
                ?>
            </div>
        </div>

        <hr class="component-divider">

        <div class="component-accordion-item" data-accordion-id="accounts">
            <div class="component-group-item component-accordion-header">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">badge</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('admin.server.accounts.title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('admin.server.accounts.desc'); ?></p>
                    </div>
                </div>
                <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
            </div>

            <div class="component-accordion-content">
                <hr class="component-divider">

                <?php
                renderStepper(
                    $i18n->t('admin.server.accounts.password_min'), 
                    $i18n->t('admin.server.accounts.password_min_desc'), 
                    'password_min_length', 
                    conf('password_min_length', 8, $config), 1, 4
                );
                renderStepper(
                    $i18n->t('admin.server.accounts.username_min'), 
                    $i18n->t('admin.server.accounts.username_min_desc'), 
                    'username_min_length', 
                    conf('username_min_length', 4, $config), 1, 2
                );
                renderStepper(
                    $i18n->t('admin.server.accounts.username_max'), 
                    $i18n->t('admin.server.accounts.username_max_desc'), 
                    'username_max_length', 
                    conf('username_max_length', 20, $config), 1, 5
                );
                renderStepper(
                    $i18n->t('admin.server.accounts.email_prefix'), 
                    $i18n->t('admin.server.accounts.email_prefix_desc'), 
                    'email_min_prefix_length', 
                    conf('email_min_prefix_length', 3, $config), 1, 3
                );
                ?>

                <div class="component-group-item component-group-item--stacked">
                    <div class="component-card__content w-100">
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?php echo $i18n->t('admin.server.accounts.domains'); ?></h2>
                            <p class="component-card__description"><?php echo $i18n->t('admin.server.accounts.domains_desc'); ?></p>
                        </div>
                    </div>
                    
                    <div class="component-card__actions w-100">
                        <input type="hidden" name="email_allowed_domains" id="input-allowed-domains" value="<?php echo conf('email_allowed_domains', '*', $config); ?>">
                        
                        <div class="domain-manager-wrapper">
                            <div class="domain-list" id="domain-list-container"></div>
                            
                            <div class="domain-input-group" id="domain-input-group" style="display: none;">
                                <input type="text" class="component-text-input" id="new-domain-input" placeholder="ej: gmail.com" autocomplete="off">
                                
                                <div class="domain-input-actions">
                                    <button type="button" class="component-button" id="btn-cancel-domain">
                                        <?php echo $i18n->t('global.cancel'); ?>
                                    </button>
                                    <button type="button" class="component-button primary" id="btn-confirm-domain">
                                        <span class="material-symbols-rounded">check</span>
                                        <?php echo $i18n->t('global.add'); ?>
                                    </button>
                                </div>
                            </div>

                            <button type="button" class="component-button" id="btn-add-domain">
                                <span class="material-symbols-rounded">add</span>
                                <?php echo $i18n->t('admin.server.accounts.btn_add_domain'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="component-divider">

        <div class="component-accordion-item" data-accordion-id="uploads">
            <div class="component-group-item component-accordion-header">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">cloud_upload</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('admin.server.uploads.title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('admin.server.uploads.desc'); ?></p>
                    </div>
                </div>
                <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
            </div>

            <div class="component-accordion-content">
                <hr class="component-divider">
                <?php
                $bytesVal = (int)conf('upload_avatar_max_size', 2097152, $config);
                $mbVal = $bytesVal / 1048576;
                renderStepper(
                    $i18n->t('admin.server.uploads.avatar_size'), 
                    $i18n->t('admin.server.uploads.avatar_size_desc'), 
                    'upload_avatar_max_size', $mbVal, 1, 5
                );
                renderStepper(
                    $i18n->t('admin.server.uploads.avatar_dim'), 
                    $i18n->t('admin.server.uploads.avatar_dim_desc'), 
                    'upload_avatar_max_dim', conf('upload_avatar_max_dim', 4096, $config), 128, 1024
                );
                ?>
            </div>
        </div>

        <hr class="component-divider">

        <div class="component-accordion-item" data-accordion-id="tokens">
            <div class="component-group-item component-accordion-header">
                <div class="component-card__content">
                    <div class="component-card__icon-container component-card__icon-container--bordered">
                        <span class="material-symbols-rounded">timer</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?php echo $i18n->t('admin.server.tokens.title'); ?></h2>
                        <p class="component-card__description"><?php echo $i18n->t('admin.server.tokens.desc'); ?></p>
                    </div>
                </div>
                <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
            </div>

            <div class="component-accordion-content">
                <hr class="component-divider">
                <?php
                renderStepper(
                    $i18n->t('admin.server.tokens.verify_expiry'), 
                    $i18n->t('admin.server.tokens.verify_expiry_desc'), 
                    'auth_verification_code_expiry', conf('auth_verification_code_expiry', 15, $config), 1, 5
                );
                renderStepper(
                    $i18n->t('admin.server.tokens.reset_expiry'), 
                    $i18n->t('admin.server.tokens.reset_expiry_desc'), 
                    'auth_reset_token_expiry', conf('auth_reset_token_expiry', 60, $config), 5, 30
                );
                ?>
            </div>
        </div>

    </div>
</div>