// public/assets/js/modules/admin-status-manager.js

(function() {
    const API_ADMIN = (window.BASE_PATH || '/ProjectAurora/') + 'api/admin_handler.php';
    const targetId = document.getElementById('target-user-id')?.value;
    
    if (!targetId) return;

    // UI Elements
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
    const wrapperDuration = document.getElementById('wrapper-duration');

    // --- EVENT DELEGATION ---

    document.body.addEventListener('click', (e) => {
        
        // 1. Toggle Dropdowns
        const toggleBtn = e.target.closest('[data-action="toggle-dropdown"]');
        if (toggleBtn) {
            e.stopPropagation();
            const targetId = toggleBtn.dataset.target;
            const targetEl = document.getElementById(targetId);
            
            if (targetEl) {
                // Cerrar otros abiertos
                closeAllDropdowns();
                targetEl.classList.toggle('disabled');
            }
            return;
        }

        // 2. Select Status Option
        const statusOpt = e.target.closest('[data-action="select-status-option"]');
        if (statusOpt) {
            const val = statusOpt.dataset.value;
            const label = statusOpt.dataset.label;
            const icon = statusOpt.dataset.icon;
            const color = statusOpt.dataset.color;
            
            selectStatus(val, label, icon, color);
            closeAllDropdowns();
            return;
        }

        // 3. Select Duration Option
        const durOpt = e.target.closest('[data-action="select-duration-option"]');
        if (durOpt) {
            selectDuration(durOpt.dataset.value);
            closeAllDropdowns();
            return;
        }

        // 4. Select Reason Option
        const reasonOpt = e.target.closest('[data-action="select-reason-option"]');
        if (reasonOpt) {
            selectReason(reasonOpt.dataset.value);
            closeAllDropdowns();
            return;
        }

        // 5. Close Dropdowns if clicking outside
        if (!e.target.closest('.trigger-select-wrapper')) {
            closeAllDropdowns();
        }
    });

    function closeAllDropdowns() {
        const dropdowns = ['dropdown-status-options', 'dropdown-duration', 'dropdown-reasons'];
        dropdowns.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.add('disabled');
        });
    }

    // --- UI Logic Functions ---

    function selectStatus(val, text, icon, color) {
        inputStatus.value = val;
        txtStatus.textContent = text;
        iconStatus.textContent = icon;
        iconStatus.style.color = color;
        
        if (val === 'suspended_temp') {
            wrapperDuration.classList.remove('d-none');
        } else if (val === 'suspended_perm') {
            wrapperDuration.classList.add('d-none'); 
        }
    }

    function selectDuration(days) {
        inputDuration.value = days;
        txtDuration.textContent = days + ' Días';
    }

    function selectReason(reason) {
        inputReason.value = reason;
        txtReason.textContent = reason;
    }

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
                
                // [CORREGIDO] Asignar el rol al contenedor para mostrar el borde
                const container = document.getElementById('status-avatar-container');
                if (container) container.dataset.role = u.role;

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