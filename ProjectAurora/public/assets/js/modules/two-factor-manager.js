// public/assets/js/modules/two-factor-manager.js

const API_SETTINGS = (window.BASE_PATH || '/ProjectAurora/') + 'api/settings_handler.php';

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

function qs(selector) {
    return document.querySelector(selector);
}

// Variable temporal para guardar el secreto antes de confirmarlo
let tempSecret = '';

export function initTwoFactorManager() {
    // Solo ejecutar si estamos en la sección correcta
    if (!qs('[data-section="settings/2fa-setup"]')) return;

    const els = {
        step1: qs('[data-step="2fa-step-1"]'),
        step2: qs('[data-step="2fa-step-2"]'),
        step3: qs('[data-step="2fa-step-3"]'),
        passInput: qs('[data-element="2fa-current-password"]'),
        verifyBtn: qs('[data-action="verify-pass-2fa"]'),
        qrContainer: qs('#qrcode-display'),
        manualText: qs('#manual-secret-text'),
        codeInput: qs('[data-element="2fa-verify-code"]'),
        confirmBtn: qs('[data-action="confirm-enable-2fa"]'),
        backupList: qs('#backup-codes-list')
    };

    // --- PASO 1: VERIFICAR CONTRASEÑA ---
    if (els.verifyBtn) {
        els.verifyBtn.onclick = async () => {
            const password = els.passInput.value;
            if (!password) return alert('Ingresa tu contraseña');

            setLoading(els.verifyBtn, true);

            try {
                // 1. Verificar contraseña
                const res = await fetch(API_SETTINGS, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
                    body: JSON.stringify({ action: 'verify_current_password', password: password })
                });
                const data = await res.json();

                if (data.success) {
                    // 2. Si es correcta, pedir el secreto para el QR
                    await generateSecret(els);
                } else {
                    if(window.alertManager) window.alertManager.showAlert(data.message, 'error');
                    setLoading(els.verifyBtn, false, 'Continuar');
                }
            } catch (e) {
                console.error(e);
                setLoading(els.verifyBtn, false, 'Continuar');
            }
        };
    }

    // --- PASO 2: CONFIRMAR CÓDIGO ---
    if (els.confirmBtn) {
        els.confirmBtn.onclick = async () => {
            const code = els.codeInput.value.trim();
            if (code.length !== 6) return alert('El código debe tener 6 dígitos');

            setLoading(els.confirmBtn, true);

            try {
                const res = await fetch(API_SETTINGS, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
                    body: JSON.stringify({ 
                        action: 'enable_2fa_confirm', 
                        secret: tempSecret,
                        code: code
                    })
                });
                const data = await res.json();

                if (data.success) {
                    // Mostrar códigos de respaldo
                    renderBackupCodes(data.backup_codes, els.backupList);
                    
                    // Cambiar a Fase 3
                    els.step2.classList.remove('active');
                    els.step2.classList.add('disabled');
                    els.step3.classList.remove('disabled');
                    els.step3.classList.add('active');
                    
                    if(window.alertManager) window.alertManager.showAlert('2FA Activado correctamente', 'success');
                } else {
                    if(window.alertManager) window.alertManager.showAlert(data.message, 'error');
                    setLoading(els.confirmBtn, false, 'Activar 2FA');
                }
            } catch (e) {
                setLoading(els.confirmBtn, false, 'Activar 2FA');
            }
        };
    }
}

async function generateSecret(els) {
    try {
        const res = await fetch(API_SETTINGS, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ action: 'generate_2fa_secret' })
        });
        const data = await res.json();

        if (data.success) {
            tempSecret = data.secret;
            els.manualText.textContent = data.secret;

            // Generar QR
            els.qrContainer.innerHTML = '';
            // Formato: otpauth://totp/ProjectAurora:Usuario?secret=SECRETO&issuer=ProjectAurora
            const uri = `otpauth://totp/ProjectAurora:${data.username}?secret=${data.secret}&issuer=ProjectAurora`;
            
            new QRCode(els.qrContainer, {
                text: uri,
                width: 180,
                height: 180
            });

            // Transición visual
            els.step1.classList.remove('active');
            els.step1.classList.add('disabled');
            els.step2.classList.remove('disabled');
            els.step2.classList.add('active');
        }
    } catch (e) {
        console.error("Error generando secreto", e);
    }
}

function renderBackupCodes(codes, container) {
    if (!codes || !container) return;
    let html = '';
    codes.forEach(code => {
        html += `<div style="font-size: 18px; letter-spacing: 2px;">${code}</div>`;
    });
    container.innerHTML = html;
}

function setLoading(btn, isLoading, originalText) {
    if (isLoading) {
        btn.innerHTML = '<div class="small-spinner"></div>'; // Asegúrate de tener estilos para spinner
        btn.disabled = true;
    } else {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}