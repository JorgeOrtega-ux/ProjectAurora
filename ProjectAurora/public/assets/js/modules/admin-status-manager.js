// public/assets/js/modules/admin-status-manager.js

(function() {
    const API_ADMIN = (window.BASE_PATH || '/ProjectAurora/') + 'api/admin_handler.php';
    const targetId = document.getElementById('target-user-id')?.value;
    
    if (!targetId) return;

    // --- Selectores UI ---
    const dropdownStatus = document.getElementById('dropdown-status-options');
    const dropdownDuration = document.getElementById('dropdown-duration');
    const dropdownReasons = document.getElementById('dropdown-reasons');
    
    const wrapperDuration = document.getElementById('wrapper-duration');
    const inputStatus = document.getElementById('input-status-value');
    const inputDuration = document.getElementById('input-duration-value');
    const inputReason = document.getElementById('input-reason-value');
    
    const txtStatus = document.getElementById('current-status-text');
    const iconStatus = document.getElementById('current-status-icon');
    const txtDuration = document.getElementById('current-duration-text');
    const txtReason = document.getElementById('current-reason-text');
    
    const btnSave = document.getElementById('btn-save-status');
    const errorBox = document.getElementById('status-error-msg');
    const historyBody = document.getElementById('suspension-history-body');

    // --- Funciones Globales ---

    window.selectStatus = function(val, text, icon, color) {
        inputStatus.value = val;
        txtStatus.textContent = text;
        iconStatus.textContent = icon;
        iconStatus.style.color = color;
        
        dropdownStatus.classList.add('disabled');

        // Control de visibilidad
        if (val === 'suspended_temp') {
            wrapperDuration.classList.remove('d-none');
        } 
        else if (val === 'suspended_perm') {
            wrapperDuration.classList.add('d-none'); 
        }
    };

    window.selectDuration = function(days) {
        inputDuration.value = days;
        txtDuration.textContent = days + ' Días';
        dropdownDuration.classList.add('disabled');
    };

    window.selectReason = function(reason) {
        inputReason.value = reason;
        txtReason.textContent = reason;
        dropdownReasons.classList.add('disabled');
    };

    // --- Toggle Dropdown Principal ---
    const statusTrigger = document.querySelector('[data-action="toggleStatusDropdown"]');
    if(statusTrigger) {
        statusTrigger.onclick = (e) => {
            e.stopPropagation();
            dropdownStatus.classList.toggle('disabled');
            // Cerramos los otros por si acaso
            dropdownDuration.classList.add('disabled');
            dropdownReasons.classList.add('disabled');
        }
    }

    // [CORRECCIÓN AQUÍ] Listener para cerrar dropdowns específicos al hacer clic fuera
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.trigger-select-wrapper')) {
            // En lugar de cerrar '.popover-module' (que cierra el header), cerramos solo estos:
            if (dropdownStatus) dropdownStatus.classList.add('disabled');
            if (dropdownDuration) dropdownDuration.classList.add('disabled');
            if (dropdownReasons) dropdownReasons.classList.add('disabled');
        }
    });

    // --- Carga Inicial ---
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

                if (u.account_status === 'suspended') {
                    if (u.suspension_end_date === null) {
                        selectStatus('suspended_perm', 'Suspensión Permanente', 'block', '#d32f2f');
                    } else {
                        selectStatus('suspended_temp', 'Suspensión Temporal', 'timer', '#f57c00');
                    }
                    if (u.suspension_reason) selectReason(u.suspension_reason);
                } else {
                    selectStatus('suspended_temp', 'Suspensión Temporal', 'timer', '#f57c00');
                }

                if (data.history && data.history.length > 0) {
                    renderHistory(data.history);
                } else {
                    historyBody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:16px; color:#888;">Sin historial de suspensiones.</td></tr>';
                }
            }
        } catch(e) { console.error(e); }
    }

    function renderHistory(logs) {
        let html = '';
        logs.forEach(log => {
            const start = new Date(log.started_at).toLocaleDateString();
            let end = '-';
            let duration = 'Permanente';
            if (log.ends_at) {
                end = new Date(log.ends_at).toLocaleDateString();
                duration = log.duration_days + ' días';
            }
            const adminName = log.admin_name ? log.admin_name : 'Sistema';
            html += `
                <tr>
                    <td>${start}</td>
                    <td><span style="display:inline-block; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${log.reason}">${log.reason}</span></td>
                    <td>${duration}</td>
                    <td>${end}</td>
                    <td><span style="background:#eee; padding:2px 6px; border-radius:4px; font-size:12px;">${adminName}</span></td>
                </tr>
            `;
        });
        historyBody.innerHTML = html;
    }

    // --- Guardar ---
    btnSave.onclick = async () => {
        const statusType = inputStatus.value; 
        const reason = inputReason.value;
        const duration = inputDuration.value;

        showError('', false);

        if (!reason) { 
            showError('Debes seleccionar una razón para la sanción.'); 
            return; 
        }

        btnSave.disabled = true;
        btnSave.textContent = 'Aplicando...';

        const payload = {
            action: 'update_user_status',
            target_id: targetId,
            status: 'suspended', 
            reason: reason,
            duration_days: 0
        };

        if (statusType === 'suspended_perm') {
            payload.duration_days = 'permanent'; 
        } 
        else if (statusType === 'suspended_temp') {
            payload.duration_days = parseInt(duration);
        }

        try {
            const res = await fetch(API_ADMIN, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if(data.success) {
                if(window.alertManager) window.alertManager.showAlert('Sanción aplicada correctamente.', 'success');
                loadUserData();
            } else {
                showError(data.message);
            }
        } catch(e) {
            showError('Error de conexión.');
        } finally {
            btnSave.disabled = false;
            btnSave.textContent = 'Aplicar Sanción';
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