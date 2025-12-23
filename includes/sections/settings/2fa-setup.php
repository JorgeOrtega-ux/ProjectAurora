<?php
// includes/sections/settings/2fa-setup.php

// Verificamos el estado desde la sesión
$is2FAEnabled = isset($_SESSION['two_factor_enabled']) && (int)$_SESSION['two_factor_enabled'] === 1;
?>

<div class="section-content active" data-section="settings/2fa-setup">
    <div class="component-wrapper">
        <div class="component-header-card">
            <h1 class="component-page-title">Autenticación en dos pasos (2FA)</h1>
            <p class="component-page-description">Añade una capa extra de seguridad a tu cuenta.</p>
        </div>

        <div class="component-card" id="2fa-wizard-container">
            
            <?php if ($is2FAEnabled): ?>
                <div style="text-align: center; padding: 20px;">
                    <span class="material-symbols-rounded" style="font-size: 48px; color: #2e7d32;">verified_user</span>
                    <h2 class="component-card__title" style="font-size: 18px; margin-top: 16px;">Tu cuenta está protegida</h2>
                    <p class="component-card__description" style="margin-top: 8px;">
                        La autenticación en dos pasos está activada. Se te pedirá un código al iniciar sesión.
                    </p>
                    <br>
                    <button type="button" class="component-button" onclick="disable2FA()" style="width: 100%; justify-content: center; color: #d32f2f; border-color: #d32f2f50;">
                        Desactivar 2FA
                    </button>
                </div>

            <?php else: ?>
                <div id="step-intro" class="active">
                    <div style="text-align: center; padding: 20px;">
                        <span class="material-symbols-rounded" style="font-size: 48px; color: #000;">security</span>
                        <h2 class="component-card__title" style="font-size: 18px; margin-top: 16px;">Protege tu cuenta</h2>
                        <p class="component-card__description" style="margin-top: 8px;">
                            Al activar la autenticación en dos pasos, necesitarás ingresar un código generado por una aplicación móvil (como Google Authenticator) cada vez que inicies sesión.
                        </p>
                        <br>
                        <button type="button" class="component-button primary" onclick="start2FASetup()" style="width: 100%; justify-content: center;">
                            Comenzar configuración
                        </button>
                    </div>
                </div>

                <div id="step-qr" class="disabled">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 16px;">
                        <p class="component-card__description" style="text-align: center;">
                            1. Abre tu aplicación de autenticación (Google Authenticator, Authy, etc).<br>
                            2. Escanea el siguiente código QR.
                        </p>
                        
                        <div id="qr-container" style="border: 1px solid #eee; padding: 8px; border-radius: 8px;">
                            <div class="spinner-sm" style="border-color: #000; border-left-color: transparent;"></div>
                        </div>

                        <div style="width: 100%; border-top: 1px solid #eee; margin-top: 8px; padding-top: 16px;">
                            <p class="component-card__description" style="margin-bottom: 8px;">3. Ingresa el código de 6 dígitos que muestra la app:</p>
                            <div class="component-input-wrapper">
                                <input type="text" id="input-2fa-verify" class="component-text-input" placeholder="000000" maxlength="6" style="text-align: center; letter-spacing: 4px; font-size: 18px;">
                            </div>
                            <br>
                            <button type="button" class="component-button primary" onclick="verifyAndEnable2FA()" style="width: 100%; justify-content: center;">
                                Verificar y Activar
                            </button>
                        </div>
                    </div>
                </div>

                <div id="step-success" class="disabled">
                    <div style="text-align: center;">
                        <span class="material-symbols-rounded" style="font-size: 48px; color: #2e7d32;">check_circle</span>
                        <h2 class="component-card__title" style="margin-top: 16px;">¡2FA Activado!</h2>
                        <p class="component-card__description" style="margin-top: 8px; color: #d32f2f;">
                            IMPORTANTE: Guarda estos códigos de recuperación en un lugar seguro.
                            Si pierdes acceso a tu teléfono, podrás usarlos para entrar a tu cuenta.
                        </p>
                    </div>

                    <div id="recovery-codes-list" style="
                        background: #f5f5fa; 
                        padding: 16px; 
                        border-radius: 8px; 
                        margin-top: 16px; 
                        display: grid; 
                        grid-template-columns: 1fr 1fr; 
                        gap: 8px; 
                        font-family: monospace; 
                        font-size: 16px; 
                        text-align: center;">
                        </div>

                    <br>
                    <button type="button" class="component-button" onclick="window.location.reload()" style="width: 100%; justify-content: center;">
                        Finalizar
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