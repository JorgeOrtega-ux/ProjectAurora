// public/assets/js/modules/admin-manage-manager.js

(function() {
    const API_ADMIN = (window.BASE_PATH || '/ProjectAurora/') + 'api/admin_handler.php';
    const targetId = document.getElementById('manage-target-id')?.value;
    
    if (!targetId) return;

    // UI ELEMENTS
    const inputStatus = document.getElementById('manage-status-value');
    const inputDelType = document.getElementById('manage-deletion-type');
    
    const txtStatus = document.getElementById('manage-status-text');
    const iconStatus = document.getElementById('manage-status-icon');
    const txtDelType = document.getElementById('text-deletion-type');
    
    const dropdownStatus = document.getElementById('dropdown-manage-status');
    const dropdownDelType = document.getElementById('dropdown-deletion-type');
    
    const wrapperDelDetails = document.getElementById('wrapper-deletion-details');
    const wrapperUserReason = document.getElementById('wrapper-user-reason');
    
    const inputUserReason = document.getElementById('input-user-reason');
    const inputAdminComments = document.getElementById('input-admin-comments');
    
    const btnSave = document.getElementById('btn-save-manage');
    const errorBox = document.getElementById('manage-error-msg');

    // --- EVENT DELEGATION ---

    document.body.addEventListener('click', (e) => {
        
        // 1. Toggle Dropdowns
        const toggleBtn = e.target.closest('[data-action="toggle-dropdown"]');
        if (toggleBtn) {
            e.stopPropagation();
            const targetId = toggleBtn.dataset.target;
            const targetEl = document.getElementById(targetId);
            
            if (targetEl) {
                closeAllDropdowns();
                targetEl.classList.toggle('disabled');
            }
            return;
        }

        // 2. Select Account Status
        const statusOpt = e.target.closest('[data-action="select-manage-status"]');
        if (statusOpt) {
            const val = statusOpt.dataset.value;
            const label = statusOpt.dataset.label;
            const icon = statusOpt.dataset.icon;
            const color = statusOpt.dataset.color;
            
            selectManageStatus(val, label, icon, color);
            closeAllDropdowns();
            return;
        }

        // 3. Select Deletion Type
        const delTypeOpt = e.target.closest('[data-action="select-deletion-type"]');
        if (delTypeOpt) {
            const val = delTypeOpt.dataset.value;
            const label = delTypeOpt.dataset.label;
            
            selectDeletionType(val, label);
            closeAllDropdowns();
            return;
        }

        // 4. Close Dropdowns if clicking outside
        if (!e.target.closest('.trigger-select-wrapper')) {
            closeAllDropdowns();
        }
    });

    function closeAllDropdowns() {
        if (dropdownStatus) dropdownStatus.classList.add('disabled');
        if (dropdownDelType) dropdownDelType.classList.add('disabled');
    }

    // --- Logic Functions ---

    function selectManageStatus(val, text, icon, color) {
        inputStatus.value = val;
        txtStatus.textContent = text;
        iconStatus.textContent = icon;
        iconStatus.style.color = color;

        if (val === 'deleted') {
            wrapperDelDetails.classList.remove('d-none');
        } else {
            wrapperDelDetails.classList.add('d-none');
        }
    }

    function selectDeletionType(val, text) {
        inputDelType.value = val;
        txtDelType.textContent = text;

        if (val === 'user_decision') {
            wrapperUserReason.classList.remove('d-none');
        } else {
            wrapperUserReason.classList.add('d-none');
        }
    }

    // --- Load User Data ---
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
                document.getElementById('manage-username').textContent = u.username;
                document.getElementById('manage-email').textContent = u.email;
                
                // [CORREGIDO] Asignar rol al contenedor para mostrar el borde
                const container = document.getElementById('manage-avatar-container');
                if (container) container.dataset.role = u.role;

                if(u.avatar) {
                    const img = document.getElementById('manage-user-avatar');
                    img.src = (window.BASE_PATH || '/ProjectAurora/') + u.avatar;
                    img.style.display = 'block';
                    document.getElementById('manage-user-icon').style.display = 'none';
                }

                // Precargar estado
                if (u.account_status === 'deleted') {
                    selectManageStatus('deleted', 'Cuenta Eliminada', 'delete_forever', '#616161');
                    
                    if (u.deletion_type) {
                        const typeText = (u.deletion_type === 'user_decision') ? 'Decisión del Usuario' : 'Decisión Administrativa';
                        selectDeletionType(u.deletion_type, typeText);
                    }
                    if (u.deletion_reason) inputUserReason.value = u.deletion_reason;
                    if (u.admin_comments) inputAdminComments.value = u.admin_comments;

                } else {
                    selectManageStatus('active', 'Activo', 'check_circle', '#2e7d32');
                }
            }
        } catch(e) { console.error(e); }
    }

    // --- Guardar ---
    btnSave.onclick = async () => {
        const status = inputStatus.value; 
        const delType = inputDelType.value;
        const userReason = inputUserReason.value.trim();
        const adminComments = inputAdminComments.value.trim();

        showError('', false);

        const payload = {
            action: 'update_user_general',
            target_id: targetId,
            status: status
        };

        if (status === 'deleted') {
            if (!adminComments) {
                showError('Los comentarios administrativos son obligatorios para eliminar una cuenta.');
                return;
            }
            payload.deletion_type = delType;
            payload.admin_comments = adminComments;

            if (delType === 'user_decision') {
                if (!userReason) {
                    showError('Si es decisión del usuario, debes especificar la razón.');
                    return;
                }
                payload.deletion_reason = userReason;
            }
        }

        btnSave.disabled = true;
        btnSave.textContent = 'Guardando...';

        try {
            const res = await fetch(API_ADMIN, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if(data.success) {
                if(window.alertManager) window.alertManager.showAlert(data.message, 'success');
                loadUserData();
            } else {
                showError(data.message);
            }
        } catch(e) {
            showError('Error de conexión.');
        } finally {
            btnSave.disabled = false;
            btnSave.textContent = 'Guardar Estado';
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