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
    </div>
    
    <script>
    (function(){
        const maintToggle = document.getElementById('admin-maintenance-toggle');
        const regToggle = document.getElementById('admin-registration-toggle');
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const basePath = window.BASE_PATH || '/ProjectAurora/';

        // Solo ejecutar si los elementos existen (estamos en admin)
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
                    }
                });
            }

            function updateConfig() {
                const formData = new FormData();
                formData.append('action', 'update_server_config');
                formData.append('maintenance_mode', maintToggle.checked ? 1 : 0);
                formData.append('allow_registrations', regToggle.checked ? 1 : 0);
                formData.append('csrf_token', csrf);
                
                maintToggle.disabled = true;
                regToggle.disabled = true;

                fetch(basePath + 'api/admin_handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if(res.status !== 'success') {
                        alert(res.message);
                        maintToggle.checked = !maintToggle.checked; 
                    }
                })
                .finally(() => {
                    maintToggle.disabled = false;
                    regToggle.disabled = false;
                });
            }

            maintToggle.addEventListener('change', updateConfig);
            regToggle.addEventListener('change', updateConfig);

            loadConfig();
        }
    })();
    </script>
</div>