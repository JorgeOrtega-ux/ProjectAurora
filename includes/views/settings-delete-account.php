<div class="settings-content-wrapper">
    <div class="settings-header">
        <h1 class="settings-title" style="color: #dc2626;"><?= t('settings.delete_account.title') ?></h1>
    </div>

    <div class="settings-card" style="border: 1px solid #fca5a5; background-color: #fef2f2;">
        <div class="settings-card-body">
            <div style="display: flex; gap: 16px; align-items: flex-start;">
                <div style="background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 50%;">
                    <svg xmlns="http://www.w3.org/.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                </div>
                <div style="flex: 1;">
                    <p style="color: #991b1b; font-size: 15px; font-weight: 500; line-height: 1.5; margin-bottom: 20px;">
                        <?= t('settings.delete_account.warning') ?>
                    </p>

                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none;">
                        <input type="checkbox" id="confirmDeleteCheckbox" style="width: 18px; height: 18px; cursor: pointer; accent-color: #dc2626;">
                        <span style="color: #450a0a; font-size: 14px; font-weight: 500;"><?= t('settings.delete_account.checkbox') ?></span>
                    </label>

                    <div id="passwordConfirmationArea" style="margin-top: 24px; display: none; padding-top: 20px; border-top: 1px solid #fecaca;">
                        <label for="deleteAccountPassword" style="display: block; font-size: 14px; font-weight: 500; color: #7f1d1d; margin-bottom: 8px;">
                            <?= t('settings.delete_account.password_label') ?>
                        </label>
                        <input type="password" id="deleteAccountPassword" class="form-input" placeholder="Tu contraseña actual" style="width: 100%; max-width: 300px; padding: 10px; border: 1px solid #fca5a5; border-radius: 6px; margin-bottom: 16px;">
                        
                        <button id="btnDeleteAccountSubmit" class="btn" style="background-color: #dc2626; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background 0.2s;">
                            <?= t('settings.delete_account.btn_delete') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const confirmCheckbox = document.getElementById('confirmDeleteCheckbox');
    const passwordArea = document.getElementById('passwordConfirmationArea');
    const deleteBtn = document.getElementById('btnDeleteAccountSubmit');
    const passwordInput = document.getElementById('deleteAccountPassword');

    // Mostrar/ocultar área de contraseña basado en el checkbox
    confirmCheckbox.addEventListener('change', (e) => {
        if (e.target.checked) {
            passwordArea.style.display = 'block';
            passwordInput.focus();
        } else {
            passwordArea.style.display = 'none';
            passwordInput.value = '';
        }
    });

    // Acción de eliminar la cuenta
    deleteBtn.addEventListener('click', async () => {
        const password = passwordInput.value.trim();

        if (!password) {
            if (typeof window.ToastController !== 'undefined') {
                window.ToastController.show('Ingresa tu contraseña para continuar.', 'warning');
            } else {
                alert('Ingresa tu contraseña para continuar.');
            }
            return;
        }

        const confirmDialog = confirm("¿Estás absolutamente seguro? Esta acción eliminará todo de inmediato.");
        
        if (confirmDialog) {
            deleteBtn.disabled = true;
            deleteBtn.textContent = 'Procesando...';
            deleteBtn.style.opacity = '0.7';

            try {
                // Suponemos la existencia de ApiService o un manejador equivalente.
                // Reutilizamos el token global que se suele inyectar en header.php o app-init.js
                const csrfToken = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                const response = await fetch('/ProjectAurora/api/index.php?route=settings.delete_account', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        password: password
                    })
                });

                const data = await response.json();

                if (data.success) {
                    if (typeof window.ToastController !== 'undefined') {
                        window.ToastController.show(data.message, 'success');
                    }
                    // Redirigir al usuario al login, la sesión ya fue destruida en backend
                    setTimeout(() => {
                        window.location.href = '/ProjectAurora/login';
                    }, 1500);
                } else {
                    deleteBtn.disabled = false;
                    deleteBtn.textContent = "<?= t('settings.delete_account.btn_delete') ?>";
                    deleteBtn.style.opacity = '1';
                    if (typeof window.ToastController !== 'undefined') {
                        window.ToastController.show(data.message, 'error');
                    } else {
                        alert(data.message);
                    }
                }
            } catch (error) {
                console.error(error);
                deleteBtn.disabled = false;
                deleteBtn.textContent = "<?= t('settings.delete_account.btn_delete') ?>";
                deleteBtn.style.opacity = '1';
                
                if (typeof window.ToastController !== 'undefined') {
                    window.ToastController.show('Error de conexión.', 'error');
                } else {
                    alert('Error de conexión.');
                }
            }
        }
    });
});
</script>