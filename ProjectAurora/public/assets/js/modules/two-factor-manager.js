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
        backupList: qs('#backup-codes-list'),
        // Elementos para desactivación
        disableBtn: qs('[data-action="disable-2fa-btn"]'),
        disablePass: qs('[data-element="2fa-disable-password"]')
    };

    // --- LÓGICA DE ACTIVACIÓN (Fases 1, 2, 3) ---

    // 1. VERIFICAR CONTRASEÑA
    if (els.verifyBtn) {
        els.verifyBtn.onclick = async () => {
            const password = els.passInput.value;
            if (!password) return alert('Ingresa tu contraseña');

            setLoading(els.verifyBtn, true);

            try {
                const res = await fetch(API_SETTINGS, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
                    body: JSON.stringify({ action: 'verify_current_password', password: password })
                });
                const data = await res.json();

                if (data.success) {
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

    // 2. CONFIRMAR CÓDIGO Y ACTIVAR
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
                    renderBackupCodes(data.backup_codes, els.backupList);
                    
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

    // --- LÓGICA DE DESACTIVACIÓN ---
    if (els.disableBtn) {
        els.disableBtn.onclick = async () => {
            const password = els.disablePass.value;
            if (!password) return alert('Ingresa tu contraseña para confirmar');

            if (!confirm('¿Estás seguro de desactivar la autenticación en dos pasos? Tu cuenta será menos segura.')) return;

            setLoading(els.disableBtn, true);

            try {
                const res = await fetch(API_SETTINGS, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
                    body: JSON.stringify({ 
                        action: 'disable_2fa', 
                        password: password
                    })
                });
                const data = await res.json();

                if (data.success) {
                    if(window.alertManager) window.alertManager.showAlert(data.message, 'info');
                    setTimeout(() => {
                        window.location.reload(); // Recargamos para que PHP muestre el flujo de activación de nuevo
                    }, 1500);
                } else {
                    if(window.alertManager) window.alertManager.showAlert(data.message, 'error');
                    setLoading(els.disableBtn, false, 'Desactivar 2FA');
                }
            } catch (e) {
                console.error(e);
                setLoading(els.disableBtn, false, 'Desactivar 2FA');
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

            // Limpiar contenedor previo
            els.qrContainer.innerHTML = '';
            
            const uri = `otpauth://totp/ProjectAurora:${data.username}?secret=${data.secret}&issuer=ProjectAurora`;
            
            // [MODIFICADO] Configuración de Alta Calidad (SVG + Transparencia)
            const qrCode = new QRCodeStyling({
                width: 280,  // Tamaño base aumentado para mejor detalle
                height: 280,
                type: "svg", // Vectorial: Calidad infinita en cualquier pantalla
                data: uri,
                dotsOptions: {
                    color: "#000000",
                    type: "rounded" // Puntos redondeados
                },
                cornersSquareOptions: {
                    type: "extra-rounded" // Esquinas redondeadas
                },
                backgroundOptions: {
                    color: "transparent", // Fondo transparente (toma el del contenedor padre)
                }
            });

            qrCode.append(els.qrContainer);

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
        btn.innerHTML = '<div class="small-spinner"></div>'; 
        btn.disabled = true;
    } else {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}