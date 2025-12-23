<?php
$is2FAEnabled = isset($_SESSION['two_factor_enabled']) && (int)$_SESSION['two_factor_enabled'] === 1;
?>

<div class="section-content active" data-section="settings/2fa-setup">
    <div class="component-wrapper">
        <div class="component-header-card">
            <h1 class="component-page-title"><?php echo $i18n->trans('settings.2fa.title'); ?></h1>
            <p class="component-page-description"><?php echo $i18n->trans('settings.2fa.desc'); ?></p>
        </div>

        <div class="component-card" id="2fa-wizard-container">
            
            <?php if ($is2FAEnabled): ?>
                <div style="text-align: center; padding: 20px;">
                    <span class="material-symbols-rounded" style="font-size: 48px; color: #2e7d32;">verified_user</span>
                    <h2 class="component-card__title" style="font-size: 18px; margin-top: 16px;"><?php echo $i18n->trans('settings.2fa.protected_title'); ?></h2>
                    <p class="component-card__description" style="margin-top: 8px;">
                        <?php echo $i18n->trans('settings.2fa.protected_desc'); ?>
                    </p>
                    <br>
                    <button type="button" class="component-button" onclick="disable2FA()" style="width: 100%; justify-content: center; color: #d32f2f; border-color: #d32f2f50;">
                        <?php echo $i18n->trans('settings.2fa.btn_disable'); ?>
                    </button>
                </div>

            <?php else: ?>
                <div id="step-intro" class="active">
                    <div style="text-align: center; padding: 20px;">
                        <span class="material-symbols-rounded" style="font-size: 48px; color: #000;">security</span>
                        <h2 class="component-card__title" style="font-size: 18px; margin-top: 16px;"><?php echo $i18n->trans('settings.2fa.setup_title'); ?></h2>
                        <p class="component-card__description" style="margin-top: 8px;">
                            <?php echo $i18n->trans('settings.2fa.setup_desc'); ?>
                        </p>
                        <br>
                        <button type="button" class="component-button primary" onclick="start2FASetup()" style="width: 100%; justify-content: center;">
                            <?php echo $i18n->trans('settings.2fa.btn_start'); ?>
                        </button>
                    </div>
                </div>

                <div id="step-qr" class="disabled">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 16px;">
                        <p class="component-card__description" style="text-align: center;">
                            <?php echo $i18n->trans('settings.2fa.step_qr'); ?>
                        </p>
                        
                        <div id="qr-container" style="border: 1px solid #eee; padding: 8px; border-radius: 8px;">
                            <div class="spinner-sm" style="border-color: #000; border-left-color: transparent;"></div>
                        </div>

                        <div style="width: 100%; border-top: 1px solid #eee; margin-top: 8px; padding-top: 16px;">
                            <p class="component-card__description" style="margin-bottom: 8px;"><?php echo $i18n->trans('settings.2fa.step_code'); ?></p>
                            <div class="component-input-wrapper">
                                <input type="text" id="input-2fa-verify" class="component-text-input" placeholder="000000" maxlength="6" style="text-align: center; letter-spacing: 4px; font-size: 18px;">
                            </div>
                            <br>
                            <button type="button" class="component-button primary" onclick="verifyAndEnable2FA()" style="width: 100%; justify-content: center;">
                                <?php echo $i18n->trans('settings.2fa.btn_verify'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="step-success" class="disabled">
                    <div style="text-align: center;">
                        <span class="material-symbols-rounded" style="font-size: 48px; color: #2e7d32;">check_circle</span>
                        <h2 class="component-card__title" style="margin-top: 16px;"><?php echo $i18n->trans('settings.2fa.success_title'); ?></h2>
                        <p class="component-card__description" style="margin-top: 8px; color: #d32f2f;">
                            <?php echo $i18n->trans('settings.2fa.success_desc'); ?>
                        </p>
                    </div>
                    
                    <div id="recovery-codes-list" style="background: #f5f5fa; padding: 16px; border-radius: 8px; margin-top: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-family: monospace; font-size: 16px; text-align: center;"></div>

                    <br>
                    <button type="button" class="component-button" onclick="window.location.reload()" style="width: 100%; justify-content: center;">
                        <?php echo $i18n->trans('settings.2fa.btn_finish'); ?>
                    </button>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    // --- NUEVA LÓGICA: DESACTIVAR 2FA ---
    async function disable2FA() {
        if(!confirm('¿Estás seguro de desactivar la autenticación en dos pasos? Tu cuenta será menos segura.')) return;

        const btn = event.target;
        const originalText = btn.innerText;
        btn.disabled = true;
        btn.innerText = "Desactivando...";

        const formData = new FormData();
        formData.append('action', 'disable_2fa');

        // INYECTAR TOKEN CSRF
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if(csrfMeta) formData.append('csrf_token', csrfMeta.getAttribute('content'));

        try {
            // Usamos ruta absoluta o relativa controlada
            const response = await fetch('<?php echo $basePath; ?>api/settings-handler.php', {
                method: 'POST',
                body: formData
            });
            const res = await response.json();

            if(res.success) {
                // Recargar para que PHP detecte el cambio de sesión y muestre el Wizard de nuevo
                window.location.reload();
            } else {
                alert(res.message);
                btn.disabled = false;
                btn.innerText = originalText;
            }
        } catch(e) {
            console.error(e);
            alert("Error de conexión");
            btn.disabled = false;
            btn.innerText = originalText;
        }
    }

    // --- LÓGICA EXISTENTE: ACTIVAR 2FA (CON CORRECCIÓN CSRF) ---
    async function start2FASetup() {
        const btn = event.target;
        btn.disabled = true;
        btn.innerText = "Generando...";

        const formData = new FormData();
        formData.append('action', 'init_2fa');

        // INYECTAR TOKEN CSRF
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if(csrfMeta) formData.append('csrf_token', csrfMeta.getAttribute('content'));

        try {
            const response = await fetch('<?php echo $basePath; ?>api/settings-handler.php', {
                method: 'POST',
                body: formData
            });
            const res = await response.json();

            if(res.success) {
                document.getElementById('step-intro').classList.add('disabled');
                document.getElementById('step-intro').classList.remove('active');
                
                document.getElementById('step-qr').classList.remove('disabled');
                document.getElementById('step-qr').classList.add('active');

                const qrDiv = document.getElementById('qr-container');
                qrDiv.innerHTML = `<img src="${res.qr_url}" alt="QR Code" style="width: 200px; height: 200px;">`;
            } else {
                alert(res.message);
                btn.disabled = false;
                btn.innerText = "Comenzar configuración";
            }
        } catch(e) {
            console.error(e);
            alert("Error de conexión");
            btn.disabled = false;
            btn.innerText = "Comenzar configuración";
        }
    }

    async function verifyAndEnable2FA() {
        const code = document.getElementById('input-2fa-verify').value;
        if(code.length < 6) { alert("Ingresa el código completo"); return; }

        const btn = event.target;
        const originalText = btn.innerText;
        btn.disabled = true;
        btn.innerText = "Verificando...";

        const formData = new FormData();
        formData.append('action', 'enable_2fa');
        formData.append('code', code);

        // INYECTAR TOKEN CSRF
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if(csrfMeta) formData.append('csrf_token', csrfMeta.getAttribute('content'));

        try {
            const response = await fetch('<?php echo $basePath; ?>api/settings-handler.php', {
                method: 'POST',
                body: formData
            });
            const res = await response.json();

            if(res.success) {
                document.getElementById('step-qr').classList.add('disabled');
                document.getElementById('step-qr').classList.remove('active');
                
                document.getElementById('step-success').classList.remove('disabled');
                document.getElementById('step-success').classList.add('active');

                const list = document.getElementById('recovery-codes-list');
                list.innerHTML = res.recovery_codes.map(c => `<span>${c}</span>`).join('');
            } else {
                alert(res.message);
                btn.disabled = false;
                btn.innerText = originalText;
            }
        } catch(e) {
            console.error(e);
            alert("Error verificando código");
            btn.disabled = false;
            btn.innerText = originalText;
        }
    }
</script>