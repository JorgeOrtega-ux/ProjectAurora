<?php
// includes/sections/admin/server.php
?>
<div class="section-content active" data-section="admin/server">
    <div class="component-wrapper">
        
        <div class="component-toolbar-wrapper">
            <div class="component-toolbar">
                 <button class="header-button" id="btn-save-limits" 
                         data-tooltip="<?= __('global.save') ?>"
                         data-lang-tooltip="global.save">
                    <span class="material-symbols-rounded">save</span>
                 </button>
            </div>
        </div>

        <div class="component-header-card">
            <h1 class="component-page-title" data-lang-key="admin.server.title"><?= __('admin.server.title') ?></h1>
            <p class="component-page-description" data-lang-key="admin.server.desc"><?= __('admin.server.desc') ?></p>
        </div>

        <style>
            /* --- CORRECCIÓN DE BORDES (SIN OVERFLOW:HIDDEN) --- */
            .accordion-header {
                cursor: pointer;
                transition: background-color 0.2s, border-radius 0.2s;
                border-top-left-radius: 12px;
                border-top-right-radius: 12px;
                border-bottom-left-radius: 12px;
                border-bottom-right-radius: 12px;
            }
            .accordion-header.active {
                background-color: #f5f5fa;
                border-bottom-left-radius: 0;
                border-bottom-right-radius: 0;
            }
            .accordion-header:hover {
                background-color: #f9f9f9;
            }
            .accordion-content {
                display: none;
                padding-top: 0;
                border-top: 1px solid #f0f0f0;
                animation: slideDown 0.3s ease-out;
                border-bottom-left-radius: 12px;
                border-bottom-right-radius: 12px;
                background-color: #fff; 
            }
            .accordion-content.open {
                display: block;
            }
            .chevron-icon {
                transition: transform 0.3s ease;
            }
            .accordion-header.active .chevron-icon {
                transform: rotate(180deg);
            }
            @keyframes slideDown {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* --- GESTOR DE DOMINIOS (CHIPS) --- */
            .domain-input-group {
                display: flex;
                gap: 8px;
                width: 100%;
                margin-bottom: 12px;
            }
            .domain-list-container {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                padding: 12px;
                background-color: #f5f5fa;
                border-radius: 8px;
                border: 1px solid #00000010;
                min-height: 50px;
            }
            .domain-chip {
                display: inline-flex;
                align-items: center;
                background-color: #fff;
                border: 1px solid #00000020;
                border-radius: 6px;
                padding: 4px 8px 4px 12px;
                font-size: 13px;
                font-weight: 500;
                color: #333;
                animation: popIn 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }
            .domain-chip span {
                margin-right: 6px;
            }
            .domain-chip-remove {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 20px;
                height: 20px;
                border-radius: 50%;
                cursor: pointer;
                color: #999;
                transition: background-color 0.2s, color 0.2s;
            }
            .domain-chip-remove:hover {
                background-color: #ffcdd2;
                color: #d32f2f;
            }
            .domain-chip-remove .material-symbols-rounded {
                font-size: 16px;
            }
            @keyframes popIn {
                from { transform: scale(0.8); opacity: 0; }
                to { transform: scale(1); opacity: 1; }
            }
            .empty-domains-text {
                color: #999;
                font-size: 13px;
                font-style: italic;
                width: 100%;
                text-align: center;
                margin-top: 4px;
            }
        </style>

        <div class="component-card component-card--grouped">
            <div class="component-group-item accordion-header">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded">settings</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-lang-key="admin.server.general_title"><?= __('admin.server.general_title') ?></h2>
                        <p class="component-card__description" data-lang-key="admin.server.general_desc"><?= __('admin.server.general_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <span class="material-symbols-rounded chevron-icon">expand_more</span>
                </div>
            </div>

            <div id="section-general" class="accordion-content">
                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-lang-key="admin.server.maintenance_title"><?= __('admin.server.maintenance_title') ?></h2>
                            <p class="component-card__description" data-lang-key="admin.server.maintenance_desc"><?= __('admin.server.maintenance_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <label class="component-toggle-switch">
                            <input type="checkbox" id="admin-maintenance-toggle">
                            <span class="component-toggle-slider"></span>
                        </label>
                    </div>
                </div>
                
                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-lang-key="admin.server.reg_title"><?= __('admin.server.reg_title') ?></h2>
                            <p class="component-card__description" data-lang-key="admin.server.reg_desc"><?= __('admin.server.reg_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <label class="component-toggle-switch">
                            <input type="checkbox" id="admin-registration-toggle">
                            <span class="component-toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item accordion-header">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded">password</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-lang-key="admin.server.limits_title"><?= __('admin.server.limits_title') ?></h2>
                        <p class="component-card__description" data-lang-key="admin.server.limits_desc"><?= __('admin.server.limits_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <span class="material-symbols-rounded chevron-icon">expand_more</span>
                </div>
            </div>

            <div id="section-limits" class="accordion-content">
                
                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-lang-key="admin.server.domains_title"><?= __('admin.server.domains_title') ?></h2>
                            <p class="component-card__description" data-lang-key="admin.server.domains_desc"><?= __('admin.server.domains_desc') ?></p>
                        </div>
                    </div>
                    <div>
                        <div class="domain-input-group">
                            <div class="component-input-wrapper">
                                <input type="text" id="new-domain-input" class="component-text-input" 
                                       placeholder="<?= __('admin.server.domains_placeholder') ?>"
                                       data-lang-placeholder="admin.server.domains_placeholder">
                            </div>
                            <button type="button" class="component-button primary" id="btn-add-domain">
                                <span class="material-symbols-rounded">add</span>
                            </button>
                        </div>
                        <div id="domain-chips-container" class="domain-list-container">
                            <span class="empty-domains-text" data-lang-key="admin.server.domains_empty"><?= __('admin.server.domains_empty') ?></span>
                        </div>
                        <input type="hidden" id="allowed-domains">
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-lang-key="admin.server.min_pass_title"><?= __('admin.server.min_pass_title') ?></h2>
                            <p class="component-card__description" data-lang-key="admin.server.min_pass_desc"><?= __('admin.server.min_pass_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_left</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="min-pass-len" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-lang-key="admin.server.max_pass_title"><?= __('admin.server.max_pass_title') ?></h2>
                            <p class="component-card__description" data-lang-key="admin.server.max_pass_desc"><?= __('admin.server.max_pass_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_left</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="max-pass-len" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-lang-key="admin.server.min_user_title"><?= __('admin.server.min_user_title') ?></h2>
                            <p class="component-card__description" data-lang-key="admin.server.min_user_desc"><?= __('admin.server.min_user_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_left</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="min-user-len" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-lang-key="admin.server.max_user_title"><?= __('admin.server.max_user_title') ?></h2>
                            <p class="component-card__description" data-lang-key="admin.server.max_user_desc"><?= __('admin.server.max_user_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_left</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="max-user-len" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-lang-key="admin.server.max_email_title"><?= __('admin.server.max_email_title') ?></h2>
                            <p class="component-card__description" data-lang-key="admin.server.max_email_desc"><?= __('admin.server.max_email_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_left</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="max-email-len" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-lang-key="admin.server.avatar_size_title"><?= __('admin.server.avatar_size_title') ?></h2>
                            <p class="component-card__description" data-lang-key="admin.server.avatar_size_desc"><?= __('admin.server.avatar_size_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_left</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="profile-size" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="component-card component-card--grouped">
            <div class="component-group-item accordion-header">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded">security</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title" data-lang-key="admin.server.security_limits_title"><?= __('admin.server.security_limits_title') ?></h2>
                        <p class="component-card__description" data-lang-key="admin.server.security_limits_desc"><?= __('admin.server.security_limits_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <span class="material-symbols-rounded chevron-icon">expand_more</span>
                </div>
            </div>

            <div id="section-security" class="accordion-content">
                
                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-lang-key="admin.server.max_login_title"><?= __('admin.server.max_login_title') ?></h2>
                            <p class="component-card__description" data-lang-key="admin.server.max_login_desc"><?= __('admin.server.max_login_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_left</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="max-login-attempts" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-lang-key="admin.server.lockout_title"><?= __('admin.server.lockout_title') ?></h2>
                            <p class="component-card__description" data-lang-key="admin.server.lockout_desc"><?= __('admin.server.lockout_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_left</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="lockout-time" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-lang-key="admin.server.resend_title"><?= __('admin.server.resend_title') ?></h2>
                            <p class="component-card__description" data-lang-key="admin.server.resend_desc"><?= __('admin.server.resend_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_left</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="code-resend" class="counter-input" min="0">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-lang-key="admin.server.user_cooldown_title"><?= __('admin.server.user_cooldown_title') ?></h2>
                            <p class="component-card__description" data-lang-key="admin.server.user_cooldown_desc"><?= __('admin.server.user_cooldown_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_left</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="username-cooldown" class="counter-input" min="0">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title" data-lang-key="admin.server.email_cooldown_title"><?= __('admin.server.email_cooldown_title') ?></h2>
                            <p class="component-card__description" data-lang-key="admin.server.email_cooldown_desc"><?= __('admin.server.email_cooldown_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_left</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="email-cooldown" class="counter-input" min="0">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                    <button type="button" class="counter-btn"><span class="material-symbols-rounded">keyboard_double_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        
    </div>
</div>