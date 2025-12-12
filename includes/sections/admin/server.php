<?php
// includes/sections/admin/server.php
?>
<div class="section-content active" data-section="admin/server">
    <div class="component-wrapper">
        
        <div class="component-toolbar-wrapper" style="width: 100%; position: sticky; top: 16px; z-index: 100;">
            <div class="component-toolbar" style="justify-content: flex-end;">
                 <button class="component-button primary" id="btn-save-limits">
                    <span class="material-symbols-rounded">save</span>
                    <?= __('global.save') ?>
                 </button>
            </div>
        </div>

        <div class="component-header-card">
            <h1 class="component-page-title" data-lang-key="admin.server.title"><?= __('admin.server.title') ?></h1>
            <p class="component-page-description" data-lang-key="admin.server.desc"><?= __('admin.server.desc') ?></p>
        </div>

        <style>
            .accordion-header {
                cursor: pointer;
                transition: background-color 0.2s;
            }
            .accordion-header:hover {
                background-color: #f9f9f9;
            }
            .accordion-content {
                display: none;
                padding-top: 0;
                border-top: 1px solid #f0f0f0;
                animation: slideDown 0.3s ease-out;
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
        </style>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item accordion-header" onclick="toggleServerSection(this, 'section-general')">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #455a64;">settings</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Configuración General</h2>
                        <p class="component-card__description">Mantenimiento, registros y medios.</p>
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
                            <h2 class="component-card__title"><?= __('admin.server.maintenance_title') ?></h2>
                            <p class="component-card__description"><?= __('admin.server.maintenance_desc') ?></p>
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
                            <h2 class="component-card__title"><?= __('admin.server.reg_title') ?></h2>
                            <p class="component-card__description"><?= __('admin.server.reg_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <label class="component-toggle-switch">
                            <input type="checkbox" id="admin-registration-toggle">
                            <span class="component-toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?= __('admin.server.avatar_size_title') ?></h2>
                            <p class="component-card__description"><?= __('admin.server.avatar_size_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper" style="width: 200px;">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('profile-size', -1)">
                                        <span class="material-symbols-rounded">keyboard_arrow_left</span>
                                    </button>
                                </div>
                                <input type="number" id="profile-size" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('profile-size', 1)">
                                        <span class="material-symbols-rounded">keyboard_arrow_right</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item accordion-header" onclick="toggleServerSection(this, 'section-limits')">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #0288d1;">password</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= __('admin.server.limits_title') ?></h2>
                        <p class="component-card__description"><?= __('admin.server.limits_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <span class="material-symbols-rounded chevron-icon">expand_more</span>
                </div>
            </div>

            <div id="section-limits" class="accordion-content">
                
                <div class="component-group-item" style="flex-direction: column; align-items: flex-start;">
                    <div class="component-card__content" style="width: 100%; margin-bottom: 15px;">
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?= __('admin.server.domains_title') ?></h2>
                            <p class="component-card__description"><?= __('admin.server.domains_desc') ?></p>
                        </div>
                    </div>
                    <div style="width: 100%;">
                        <textarea id="allowed-domains" class="component-text-input" style="height: auto; min-height: 80px; padding: 10px;" rows="3" placeholder="gmail.com, outlook.com..."></textarea>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title">Mínimo Contraseña</h2>
                            <p class="component-card__description">Caracteres mínimos requeridos.</p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper" style="width: 200px;">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('min-pass-len', -1)"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="min-pass-len" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('min-pass-len', 1)"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title">Máximo Contraseña</h2>
                            <p class="component-card__description">Límite superior de caracteres.</p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper" style="width: 200px;">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('max-pass-len', -1)"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="max-pass-len" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('max-pass-len', 1)"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title">Mínimo Usuario</h2>
                            <p class="component-card__description">Longitud mínima del nombre de usuario.</p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper" style="width: 200px;">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('min-user-len', -1)"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="min-user-len" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('min-user-len', 1)"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title">Máximo Usuario</h2>
                            <p class="component-card__description">Longitud máxima del nombre de usuario.</p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper" style="width: 200px;">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('max-user-len', -1)"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="max-user-len" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('max-user-len', 1)"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title">Máximo Email</h2>
                            <p class="component-card__description">Longitud total permitida para correos.</p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper" style="width: 200px;">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('max-email-len', -1)"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="max-email-len" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('max-email-len', 1)"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <hr class="component-divider">

            <div class="component-group-item accordion-header" onclick="toggleServerSection(this, 'section-security')">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #d32f2f;">security</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= __('admin.server.security_limits_title') ?></h2>
                        <p class="component-card__description"><?= __('admin.server.security_limits_desc') ?></p>
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
                            <h2 class="component-card__title"><?= __('admin.server.max_login_title') ?></h2>
                            <p class="component-card__description"><?= __('admin.server.max_login_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper" style="width: 200px;">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('max-login-attempts', -1)"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="max-login-attempts" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('max-login-attempts', 1)"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?= __('admin.server.lockout_title') ?></h2>
                            <p class="component-card__description"><?= __('admin.server.lockout_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper" style="width: 200px;">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('lockout-time', -1)"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="lockout-time" class="counter-input" min="1">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('lockout-time', 1)"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?= __('admin.server.resend_title') ?></h2>
                            <p class="component-card__description"><?= __('admin.server.resend_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper" style="width: 200px;">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('code-resend', -10)"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="code-resend" class="counter-input" min="0">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('code-resend', 10)"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?= __('admin.server.user_cooldown_title') ?></h2>
                            <p class="component-card__description"><?= __('admin.server.user_cooldown_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper" style="width: 200px;">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('username-cooldown', -1)"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="username-cooldown" class="counter-input" min="0">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('username-cooldown', 1)"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="component-group-item">
                    <div class="component-card__content">
                        <div class="component-card__text">
                            <h2 class="component-card__title"><?= __('admin.server.email_cooldown_title') ?></h2>
                            <p class="component-card__description"><?= __('admin.server.email_cooldown_desc') ?></p>
                        </div>
                    </div>
                    <div class="component-card__actions actions-right">
                        <div class="component-input-wrapper" style="width: 200px;">
                            <div class="component-counter-control">
                                <div class="counter-group left">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('email-cooldown', -1)"><span class="material-symbols-rounded">keyboard_arrow_left</span></button>
                                </div>
                                <input type="number" id="email-cooldown" class="counter-input" min="0">
                                <div class="counter-group right">
                                    <button type="button" class="counter-btn" onclick="adjustCounter('email-cooldown', 1)"><span class="material-symbols-rounded">keyboard_arrow_right</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            
        </div>
    </div>
    
    <script>
    (function(){
        // Función para colapsar/expandir
        window.toggleServerSection = function(header, contentId) {
            const content = document.getElementById(contentId);
            const isOpen = content.classList.contains('open');
            
            if(isOpen) {
                content.classList.remove('open');
                header.classList.remove('active');
            } else {
                content.classList.add('open');
                header.classList.add('active');
            }
        };

        // Función global para ajustar contadores
        window.adjustCounter = function(id, amount) {
            const input = document.getElementById(id);
            if(!input) return;
            
            let val = parseInt(input.value) || 0;
            let min = parseInt(input.getAttribute('min')) || 0;
            let max = parseInt(input.getAttribute('max')) || 9999;
            
            val += amount;
            
            if(val < min) val = min;
            if(val > max) val = max;
            
            input.value = val;
        };

        const maintToggle = document.getElementById('admin-maintenance-toggle');
        const regToggle = document.getElementById('admin-registration-toggle');
        
        // Inputs existentes
        const minPass = document.getElementById('min-pass-len');
        const maxPass = document.getElementById('max-pass-len');
        const minUser = document.getElementById('min-user-len');
        const maxUser = document.getElementById('max-user-len');
        const maxEmail = document.getElementById('max-email-len');
        const allowedDomains = document.getElementById('allowed-domains'); 
        
        // Nuevos inputs
        const maxLogin = document.getElementById('max-login-attempts');
        const lockoutTime = document.getElementById('lockout-time');
        const codeResend = document.getElementById('code-resend');
        const userCooldown = document.getElementById('username-cooldown');
        const emailCooldown = document.getElementById('email-cooldown');
        const profileSize = document.getElementById('profile-size');

        const btnSaveLimits = document.getElementById('btn-save-limits');

        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const basePath = window.BASE_PATH || '/ProjectAurora/';

        if(maintToggle && regToggle){
            function loadConfig() {
                const formData = new FormData();
                formData.append('action', 'get_server_config');
                formData.append('csrf_token', csrf);

                fetch(basePath + 'api/admin_handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if(res.status === 'success') {
                        maintToggle.checked = (res.data.maintenance_mode == 1);
                        regToggle.checked = (res.data.allow_registrations == 1);

                        if(minPass) minPass.value = res.data.min_password_length;
                        if(maxPass) maxPass.value = res.data.max_password_length;
                        if(minUser) minUser.value = res.data.min_username_length;
                        if(maxUser) maxUser.value = res.data.max_username_length;
                        if(maxEmail) maxEmail.value = res.data.max_email_length;
                        if(allowedDomains) allowedDomains.value = res.data.allowed_email_domains || ''; 

                        if(maxLogin) maxLogin.value = res.data.max_login_attempts;
                        if(lockoutTime) lockoutTime.value = res.data.lockout_time_minutes;
                        if(codeResend) codeResend.value = res.data.code_resend_cooldown;
                        if(userCooldown) userCooldown.value = res.data.username_cooldown;
                        if(emailCooldown) emailCooldown.value = res.data.email_cooldown;
                        if(profileSize) profileSize.value = res.data.profile_picture_max_size;
                    }
                });
            }

            function updateConfig(e) {
                const isToggle = (e.target.type === 'checkbox');
                
                const formData = new FormData();
                formData.append('action', 'update_server_config');
                formData.append('maintenance_mode', maintToggle.checked ? 1 : 0);
                formData.append('allow_registrations', regToggle.checked ? 1 : 0);
                
                formData.append('min_password_length', minPass.value);
                formData.append('max_password_length', maxPass.value);
                formData.append('min_username_length', minUser.value);
                formData.append('max_username_length', maxUser.value);
                formData.append('max_email_length', maxEmail.value);
                formData.append('allowed_email_domains', allowedDomains.value); 

                formData.append('max_login_attempts', maxLogin.value);
                formData.append('lockout_time_minutes', lockoutTime.value);
                formData.append('code_resend_cooldown', codeResend.value);
                formData.append('username_cooldown', userCooldown.value);
                formData.append('email_cooldown', emailCooldown.value);
                formData.append('profile_picture_max_size', profileSize.value);

                formData.append('csrf_token', csrf);
                
                if(isToggle) e.target.disabled = true;
                if(btnSaveLimits) {
                    btnSaveLimits.disabled = true; 
                    btnSaveLimits.innerText = '...';
                }

                fetch(basePath + 'api/admin_handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if(res.status !== 'success') {
                        alert(res.message);
                        loadConfig(); 
                    }
                })
                .finally(() => {
                    if(isToggle) e.target.disabled = false;
                    if(btnSaveLimits) {
                        btnSaveLimits.disabled = false;
                        btnSaveLimits.innerText = '<?= __('global.save') ?>';
                    }
                });
            }

            maintToggle.addEventListener('change', updateConfig);
            regToggle.addEventListener('change', updateConfig);
            
            if(btnSaveLimits) {
                btnSaveLimits.addEventListener('click', updateConfig);
            }

            loadConfig();
        }
    })();
    </script>
</div>