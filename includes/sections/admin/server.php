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

        <div class="component-card">
            <h2 class="component-card__title" style="margin-bottom: 12px;"><?= __('admin.server.limits_title') ?></h2>
            <p class="component-card__description" style="margin-bottom: 24px;"><?= __('admin.server.limits_desc') ?></p>

            <div class="form-groups-wrapper">
                <div style="display: flex; gap: 16px;">
                    <div class="form-group" style="flex: 1;">
                        <input type="number" id="min-pass-len" class="component-text-input" placeholder=" " min="1">
                        <label>Min Password Length</label>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <input type="number" id="max-pass-len" class="component-text-input" placeholder=" " min="1">
                        <label>Max Password Length</label>
                    </div>
                </div>
                
                <div style="display: flex; gap: 16px;">
                    <div class="form-group" style="flex: 1;">
                        <input type="number" id="min-user-len" class="component-text-input" placeholder=" " min="1">
                        <label>Min Username Length</label>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <input type="number" id="max-user-len" class="component-text-input" placeholder=" " min="1">
                        <label>Max Username Length</label>
                    </div>
                </div>

                <div class="form-group">
                    <input type="number" id="max-email-len" class="component-text-input" placeholder=" " min="1">
                    <label>Max Email Length</label>
                </div>
            </div>

            <div class="component-card__actions actions-right">
                <button class="component-button primary" id="btn-save-limits"><?= __('global.save') ?></button>
            </div>
        </div>
    </div>
    
    <script>
    (function(){
        const maintToggle = document.getElementById('admin-maintenance-toggle');
        const regToggle = document.getElementById('admin-registration-toggle');
        
        const minPass = document.getElementById('min-pass-len');
        const maxPass = document.getElementById('max-pass-len');
        const minUser = document.getElementById('min-user-len');
        const maxUser = document.getElementById('max-user-len');
        const maxEmail = document.getElementById('max-email-len');
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
                    }
                });
            }

            function updateConfig() {
                const formData = new FormData();
                formData.append('action', 'update_server_config');
                // General
                formData.append('maintenance_mode', maintToggle.checked ? 1 : 0);
                formData.append('allow_registrations', regToggle.checked ? 1 : 0);
                
                // Limits (si existen en el DOM, enviamos lo que haya, el backend valida si enviar o mantener anterior)
                // Pero aquí enviamos todo junto para simplificar, el backend espera todo en update_server_config
                formData.append('min_password_length', minPass.value);
                formData.append('max_password_length', maxPass.value);
                formData.append('min_username_length', minUser.value);
                formData.append('max_username_length', maxUser.value);
                formData.append('max_email_length', maxEmail.value);

                formData.append('csrf_token', csrf);
                
                // Bloqueo UI
                maintToggle.disabled = true;
                regToggle.disabled = true;
                if(btnSaveLimits) {
                    btnSaveLimits.disabled = true; 
                    btnSaveLimits.innerText = '...';
                }

                fetch(basePath + 'api/admin_handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if(res.status !== 'success') {
                        alert(res.message);
                        // Revertir UI si es necesario (recargar config)
                        loadConfig();
                    } else {
                        // Opcional: Toast de éxito
                    }
                })
                .finally(() => {
                    maintToggle.disabled = false;
                    regToggle.disabled = false;
                    if(btnSaveLimits) {
                        btnSaveLimits.disabled = false;
                        btnSaveLimits.innerText = 'Guardar';
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