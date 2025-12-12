<?php
// includes/sections/admin/server.php
// Refactorizado para mostrar configuración de servidor y nuevos límites avanzados
?>
<div class="section-content active" data-section="admin/server">
    <div class="component-wrapper">
        <div class="component-header-card">
            <h1 class="component-page-title" data-lang-key="admin.server.title"><?= __('admin.server.title') ?></h1>
            <p class="component-page-description" data-lang-key="admin.server.desc"><?= __('admin.server.desc') ?></p>
        </div>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #f57c00;">engineering</span>
                    </div>
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
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #455a64;">person_add</span>
                    </div>
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

        </div>

        <div class="component-header-card" style="margin-top: 24px; text-align: left; padding: 16px 24px;">
             <h2 class="component-card__title"><?= __('admin.server.limits_title') ?></h2>
             <p class="component-card__description"><?= __('admin.server.limits_desc') ?></p>
        </div>

        <div class="component-card component-card--grouped">
            
            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #333;">pin</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Mínimo Contraseña</h2>
                        <p class="component-card__description">Caracteres mínimos requeridos.</p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <div class="component-input-wrapper" style="width: 260px;">
                        <div class="component-counter-control">
                            <div class="counter-group left">
                                <button type="button" class="counter-btn" onclick="adjustCounter('min-pass-len', -1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_left</span>
                                </button>
                            </div>
                            <input type="number" id="min-pass-len" class="counter-input" min="1">
                            <div class="counter-group right">
                                <button type="button" class="counter-btn" onclick="adjustCounter('min-pass-len', 1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_right</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #333;">password</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Máximo Contraseña</h2>
                        <p class="component-card__description">Límite superior de caracteres.</p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <div class="component-input-wrapper" style="width: 260px;">
                        <div class="component-counter-control">
                            <div class="counter-group left">
                                <button type="button" class="counter-btn" onclick="adjustCounter('max-pass-len', -1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_left</span>
                                </button>
                            </div>
                            <input type="number" id="max-pass-len" class="counter-input" min="1">
                            <div class="counter-group right">
                                <button type="button" class="counter-btn" onclick="adjustCounter('max-pass-len', 1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_right</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #5e35b1;">person_remove</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Mínimo Usuario</h2>
                        <p class="component-card__description">Longitud mínima del nombre de usuario.</p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <div class="component-input-wrapper" style="width: 260px;">
                        <div class="component-counter-control">
                            <div class="counter-group left">
                                <button type="button" class="counter-btn" onclick="adjustCounter('min-user-len', -1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_left</span>
                                </button>
                            </div>
                            <input type="number" id="min-user-len" class="counter-input" min="1">
                            <div class="counter-group right">
                                <button type="button" class="counter-btn" onclick="adjustCounter('min-user-len', 1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_right</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #5e35b1;">badge</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Máximo Usuario</h2>
                        <p class="component-card__description">Longitud máxima del nombre de usuario.</p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <div class="component-input-wrapper" style="width: 260px;">
                        <div class="component-counter-control">
                            <div class="counter-group left">
                                <button type="button" class="counter-btn" onclick="adjustCounter('max-user-len', -1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_left</span>
                                </button>
                            </div>
                            <input type="number" id="max-user-len" class="counter-input" min="1">
                            <div class="counter-group right">
                                <button type="button" class="counter-btn" onclick="adjustCounter('max-user-len', 1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_right</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #0288d1;">mail</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title">Máximo Email</h2>
                        <p class="component-card__description">Longitud total permitida para correos.</p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <div class="component-input-wrapper" style="width: 260px;">
                        <div class="component-counter-control">
                            <div class="counter-group left">
                                <button type="button" class="counter-btn" onclick="adjustCounter('max-email-len', -1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_left</span>
                                </button>
                            </div>
                            <input type="number" id="max-email-len" class="counter-input" min="1">
                            <div class="counter-group right">
                                <button type="button" class="counter-btn" onclick="adjustCounter('max-email-len', 1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_right</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="component-header-card" style="margin-top: 24px; text-align: left; padding: 16px 24px;">
             <h2 class="component-card__title"><?= __('admin.server.security_limits_title') ?></h2>
             <p class="component-card__description"><?= __('admin.server.security_limits_desc') ?></p>
        </div>

        <div class="component-card component-card--grouped">

             <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #d32f2f;">lock_clock</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= __('admin.server.max_login_title') ?></h2>
                        <p class="component-card__description"><?= __('admin.server.max_login_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <div class="component-input-wrapper" style="width: 260px;">
                        <div class="component-counter-control">
                            <div class="counter-group left">
                                <button type="button" class="counter-btn" onclick="adjustCounter('max-login-attempts', -1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_left</span>
                                </button>
                            </div>
                            <input type="number" id="max-login-attempts" class="counter-input" min="1">
                            <div class="counter-group right">
                                <button type="button" class="counter-btn" onclick="adjustCounter('max-login-attempts', 1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_right</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #c2185b;">timer</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= __('admin.server.lockout_title') ?></h2>
                        <p class="component-card__description"><?= __('admin.server.lockout_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <div class="component-input-wrapper" style="width: 260px;">
                        <div class="component-counter-control">
                            <div class="counter-group left">
                                <button type="button" class="counter-btn" onclick="adjustCounter('lockout-time', -1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_left</span>
                                </button>
                            </div>
                            <input type="number" id="lockout-time" class="counter-input" min="1">
                            <div class="counter-group right">
                                <button type="button" class="counter-btn" onclick="adjustCounter('lockout-time', 1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_right</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #7b1fa2;">sms_failed</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= __('admin.server.resend_title') ?></h2>
                        <p class="component-card__description"><?= __('admin.server.resend_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <div class="component-input-wrapper" style="width: 260px;">
                        <div class="component-counter-control">
                            <div class="counter-group left">
                                <button type="button" class="counter-btn" onclick="adjustCounter('code-resend', -10)">
                                    <span class="material-symbols-rounded">keyboard_arrow_left</span>
                                </button>
                            </div>
                            <input type="number" id="code-resend" class="counter-input" min="0">
                            <div class="counter-group right">
                                <button type="button" class="counter-btn" onclick="adjustCounter('code-resend', 10)">
                                    <span class="material-symbols-rounded">keyboard_arrow_right</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #512da8;">manage_accounts</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= __('admin.server.user_cooldown_title') ?></h2>
                        <p class="component-card__description"><?= __('admin.server.user_cooldown_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <div class="component-input-wrapper" style="width: 260px;">
                        <div class="component-counter-control">
                            <div class="counter-group left">
                                <button type="button" class="counter-btn" onclick="adjustCounter('username-cooldown', -1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_left</span>
                                </button>
                            </div>
                            <input type="number" id="username-cooldown" class="counter-input" min="0">
                            <div class="counter-group right">
                                <button type="button" class="counter-btn" onclick="adjustCounter('username-cooldown', 1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_right</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #303f9f;">mark_email_read</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= __('admin.server.email_cooldown_title') ?></h2>
                        <p class="component-card__description"><?= __('admin.server.email_cooldown_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <div class="component-input-wrapper" style="width: 260px;">
                        <div class="component-counter-control">
                            <div class="counter-group left">
                                <button type="button" class="counter-btn" onclick="adjustCounter('email-cooldown', -1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_left</span>
                                </button>
                            </div>
                            <input type="number" id="email-cooldown" class="counter-input" min="0">
                            <div class="counter-group right">
                                <button type="button" class="counter-btn" onclick="adjustCounter('email-cooldown', 1)">
                                    <span class="material-symbols-rounded">keyboard_arrow_right</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="component-divider">

            <div class="component-group-item">
                <div class="component-card__content">
                    <div class="component-card__profile-picture component-card__profile-picture--bordered">
                        <span class="material-symbols-rounded" style="color: #0097a7;">image</span>
                    </div>
                    <div class="component-card__text">
                        <h2 class="component-card__title"><?= __('admin.server.avatar_size_title') ?></h2>
                        <p class="component-card__description"><?= __('admin.server.avatar_size_desc') ?></p>
                    </div>
                </div>
                <div class="component-card__actions actions-right">
                    <div class="component-input-wrapper" style="width: 260px;">
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

            <div class="component-group-item" style="background-color: #f5f5fa; justify-content: flex-end;">
                 <button class="component-button primary" id="btn-save-limits" style="width: 100%; justify-content: center;">
                    <?= __('global.save') ?>
                 </button>
            </div>

        </div>
    </div>
    
    <script>
    (function(){
        // Función global (dentro del scope de la página) para ajustar contadores
        window.adjustCounter = function(id, amount) {
            const input = document.getElementById(id);
            if(!input) return;
            
            let val = parseInt(input.value) || 0;
            // Obtener límites del atributo HTML, por defecto 1 y 9999 si no existen
            let min = parseInt(input.getAttribute('min')) || 0;
            let max = parseInt(input.getAttribute('max')) || 9999;
            
            val += amount;
            
            // Validar límites
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
                        // Toggles
                        maintToggle.checked = (res.data.maintenance_mode == 1);
                        regToggle.checked = (res.data.allow_registrations == 1);

                        // Limits
                        if(minPass) minPass.value = res.data.min_password_length;
                        if(maxPass) maxPass.value = res.data.max_password_length;
                        if(minUser) minUser.value = res.data.min_username_length;
                        if(maxUser) maxUser.value = res.data.max_username_length;
                        if(maxEmail) maxEmail.value = res.data.max_email_length;

                        // Nuevos
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
                // Determinar si fue disparado por un toggle o por el botón de guardar
                const isToggle = (e.target.type === 'checkbox');
                
                const formData = new FormData();
                formData.append('action', 'update_server_config');
                // General
                formData.append('maintenance_mode', maintToggle.checked ? 1 : 0);
                formData.append('allow_registrations', regToggle.checked ? 1 : 0);
                
                // Limits
                formData.append('min_password_length', minPass.value);
                formData.append('max_password_length', maxPass.value);
                formData.append('min_username_length', minUser.value);
                formData.append('max_username_length', maxUser.value);
                formData.append('max_email_length', maxEmail.value);

                // Nuevos valores
                formData.append('max_login_attempts', maxLogin.value);
                formData.append('lockout_time_minutes', lockoutTime.value);
                formData.append('code_resend_cooldown', codeResend.value);
                formData.append('username_cooldown', userCooldown.value);
                formData.append('email_cooldown', emailCooldown.value);
                formData.append('profile_picture_max_size', profileSize.value);

                formData.append('csrf_token', csrf);
                
                // Bloqueo UI
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
                        loadConfig(); // Revertir en caso de error
                    } else {
                        // Opcional: Toast de éxito
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