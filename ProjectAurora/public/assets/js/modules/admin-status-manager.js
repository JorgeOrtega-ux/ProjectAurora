// public/assets/js/modules/admin-status-manager.js

(function() {
    const API_ADMIN = (window.BASE_PATH || '/ProjectAurora/') + 'api/admin_handler.php';
    const targetId = document.getElementById('target-user-id')?.value;
    
    if (!targetId) return;

    // --- Selectores ---
    const statusDropdown = document.getElementById('dropdown-status-options');
    const statusTrigger = document.querySelector('[data-action="toggleStatusDropdown"]');
    const panelSuspension = document.getElementById('panel-suspension');
    const inputStatus = document.getElementById('input-status-value');
    const inputReason = document.getElementById('input-reason-value');
    const btnSave = document.getElementById('btn-save-status');
    const errorBox = document.getElementById('status-error-msg');

    // --- Funciones Globales para el HTML ---
    window.selectStatus = function(val, text, icon, color) {
        inputStatus.value = val;
        document.getElementById('current-status-text').textContent = text;
        const iconEl = document.getElementById('current-status-icon');
        iconEl.textContent = icon;
        iconEl.style.color = color;
        
        statusDropdown.classList.add('disabled');

        // Lógica Condicional UI
        if (val === 'suspended') {
            panelSuspension.classList.remove('d-none');
        } else {
            panelSuspension.classList.add('d-none');
        }
    };

    window.selectReason = function(reason) {
        inputReason.value = reason;
        document.getElementById('reason-text').textContent = reason;
        document.getElementById('dropdown-reasons').classList.add('disabled');
    };

    // --- Toggle Dropdown ---
    if(statusTrigger) {
        statusTrigger.onclick = (e) => {
            e.stopPropagation();
            statusDropdown.classList.toggle('disabled');
        }
    }
    // Cerrar al hacer click fuera
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.trigger-select-wrapper')) {
            document.querySelectorAll('.popover-module').forEach(el => el.classList.add('disabled'));
        }
    });

    // --- Carga Inicial de Datos ---
    async function loadUserData() {
        try {
            const res = await fetch(API_ADMIN, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
                body: JSON.stringify({ action: 'get_user_details', target_id: targetId })
            });
            const data = await res.json();
            
            if(data.success) {
                const u = data.user;
                document.getElementById('status-username').textContent = u.username;
                document.getElementById('status-email').textContent = u.email;
                
                if(u.avatar) {
                    const img = document.getElementById('status-user-avatar');
                    img.src = (window.BASE_PATH || '/ProjectAurora/') + u.avatar;
                    img.style.display = 'block';
                    document.getElementById('status-user-icon').style.display = 'none';
                }

                // Pre-seleccionar estado actual
                if(u.account_status === 'active') selectStatus('active', 'Activo', 'check_circle', '#2e7d32');
                if(u.account_status === 'deleted') selectStatus('deleted', 'Eliminado', 'delete', '#616161');
                if(u.account_status === 'suspended') {
                    selectStatus('suspended', 'Suspendido', 'block', '#d32f2f');
                    if(u.suspension_reason) selectReason(u.suspension_reason);
                    if(data.days_remaining) document.getElementById('input-days').value = data.days_remaining;
                }
            }
        } catch(e) { console.error(e); }
    }

    // --- Guardar ---
    btnSave.onclick = async () => {
        const status = inputStatus.value;
        const reason = inputReason.value;
        const days = document.getElementById('input-days').value;

        // Validación simple
        if (status === 'suspended') {
            if (!reason) { showError('Debes seleccionar una razón para la suspensión.'); return; }
            if (days < 1) { showError('La duración debe ser al menos 1 día.'); return; }
        }

        showError('', false);
        btnSave.disabled = true;
        btnSave.textContent = 'Guardando...';

        try {
            const res = await fetch(API_ADMIN, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
                body: JSON.stringify({
                    action: 'update_user_status',
                    target_id: targetId,
                    status: status,
                    reason: reason,
                    days: days
                })
            });
            const data = await res.json();

            if(data.success) {
                if(window.alertManager) window.alertManager.showAlert('Estado actualizado correctamente.', 'success');
                setTimeout(() => {
                    if(window.navigateTo) window.navigateTo('admin/users');
                }, 1000);
            } else {
                showError(data.message);
                btnSave.disabled = false;
                btnSave.textContent = 'Guardar Cambios';
            }
        } catch(e) {
            showError('Error de conexión.');
            btnSave.disabled = false;
            btnSave.textContent = 'Guardar Cambios';
        }
    };

    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    }

    function showError(msg, show = true) {
        if(show) {
            errorBox.textContent = msg;
            errorBox.classList.add('active');
        } else {
            errorBox.classList.remove('active');
        }
    }

    loadUserData();

})();