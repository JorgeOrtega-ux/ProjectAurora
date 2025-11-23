
(function() {
    const API_ADMIN = (window.BASE_PATH || '/ProjectAurora/') + 'api/admin_handler.php';
    const targetId = document.getElementById('target-user-id')?.value;
    
    if (!targetId) return;

    // Estado Actual del Usuario
    let currentUserState = {
        isSuspended: false,
        isPermanent: false,
        reason: null
    };

    // UI Elements
    const inputStatus = document.getElementById('input-status-value');
    const inputDuration = document.getElementById('input-duration-value');
    const inputReason = document.getElementById('input-reason-value');
    
    const txtStatus = document.getElementById('current-status-text');
    const iconStatus = document.getElementById('current-status-icon');
    const txtDuration = document.getElementById('current-duration-text');
    const txtReason = document.getElementById('current-reason-text');
    
    const btnSave = document.getElementById('btn-save-status');
    const btnLift = document.getElementById('btn-lift-ban');
    const btnSaveText = document.getElementById('btn-save-text');
    
    const historyBody = document.getElementById('suspension-history-body');
    const wrapperDuration = document.getElementById('wrapper-duration');
    
    const activeAlert = document.getElementById('active-sanction-alert');
    const activeAlertDesc = document.getElementById('active-sanction-desc');

    function updateCardError(element, message = '', show = true) {
        if (!element) return;
        const cardContainer = element.closest('.component-card') || element;
        let nextElement = cardContainer.nextElementSibling;
        let errorDiv = null;

        if (nextElement && nextElement.classList.contains('component-card__error')) {
            errorDiv = nextElement;
        }

        if (!errorDiv && show) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'component-card__error';
            errorDiv.style.marginTop = '16px'; 
            cardContainer.after(errorDiv);
        }

        if (show && errorDiv) {
            errorDiv.textContent = message;
            requestAnimationFrame(() => errorDiv.classList.add('active'));
        } else if (!show && errorDiv) {
            errorDiv.classList.remove('active');
            setTimeout(() => {
                if (errorDiv.parentNode) errorDiv.parentNode.removeChild(errorDiv);
            }, 200);
        }
    }

    function showError(msg, show = true) {
        updateCardError(inputStatus, msg, show);
    }

    // --- UI HELPER FOR DROPDOWN VISUALS ---
    function handleDropdownSelectionVisuals(selectedOption) {
        // 1. Find parent container
        const menuList = selectedOption.closest('.menu-list');
        if (!menuList) return;

        // 2. Reset all options in this dropdown
        const allOptions = menuList.querySelectorAll('.menu-link');
        allOptions.forEach(opt => {
            opt.classList.remove('active');
            const iconContainer = opt.lastElementChild; // The right-side icon container
            if (iconContainer) iconContainer.innerHTML = '';
        });

        // 3. Set active state for selected
        selectedOption.classList.add('active');
        const activeIconContainer = selectedOption.lastElementChild;
        if (activeIconContainer) activeIconContainer.innerHTML = '<span class="material-symbols-rounded">check</span>';
    }

    // --- EVENT DELEGATION ---

    document.body.addEventListener('click', (e) => {
        
        // 1. Toggle Dropdowns (Fixed Logic)
        const toggleBtn = e.target.closest('[data-action="toggle-dropdown"]');
        if (toggleBtn) {
            e.stopPropagation();
            const targetId = toggleBtn.dataset.target;
            const targetEl = document.getElementById(targetId);
            
            if (targetEl) {
                // Check if it's currently open before closing all
                const isCurrentlyOpen = !targetEl.classList.contains('disabled');
                
                closeAllDropdowns(); // Close others
                
                // Toggle logic: if it was closed, open it. If open, leave it closed (since closeAll closed it)
                if (!isCurrentlyOpen) {
                    targetEl.classList.remove('disabled');
                }
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
            handleDropdownSelectionVisuals(statusOpt); // Update checkmarks
            closeAllDropdowns();
            return;
        }

        // 3. Select Duration Option
        const durOpt = e.target.closest('[data-action="select-duration-option"]');
        if (durOpt) {
            selectDuration(durOpt.dataset.value);
            handleDropdownSelectionVisuals(durOpt); // Update checkmarks
            closeAllDropdowns();
            return;
        }

        // 4. Select Reason Option
        const reasonOpt = e.target.closest('[data-action="select-reason-option"]');
        if (reasonOpt) {
            selectReason(reasonOpt.dataset.value);
            handleDropdownSelectionVisuals(reasonOpt); // Update checkmarks
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
                const container = document.getElementById('status-avatar-container');
                if (container) container.dataset.role = u.role;

                if(u.avatar) {
                    const img = document.getElementById('status-user-avatar');
                    img.src = (window.BASE_PATH || '/ProjectAurora/') + u.avatar;
                    img.style.display = 'block';
                    document.getElementById('status-user-icon').style.display = 'none';
                }

                // Estado
                if (u.account_status === 'suspended') {
                    currentUserState.isSuspended = true;
                    currentUserState.reason = u.suspension_reason;
                    
                    activeAlert.classList.remove('d-none');
                    btnLift.classList.remove('d-none');
                    btnSaveText.textContent = 'Actualizar Sanción';

                    let activeText = '';
                    if (u.suspension_end_date === null) {
                        currentUserState.isPermanent = true;
                        activeText = 'Permanente';
                        selectStatus('suspended_perm', 'Suspensión Permanente', 'block', '#d32f2f');
                        // Update UI selection manually for initial load
                        updateInitialSelection('dropdown-status-options', 'suspended_perm');
                    } else {
                        currentUserState.isPermanent = false;
                        const endDate = new Date(u.suspension_end_date).toLocaleDateString();
                        activeText = `Hasta el ${endDate}`;
                        selectStatus('suspended_temp', 'Suspensión Temporal', 'timer', '#f57c00');
                        updateInitialSelection('dropdown-status-options', 'suspended_temp');
                    }
                    
                    activeAlertDesc.innerHTML = `<strong>${activeText}</strong><br>Motivo: ${u.suspension_reason}`;
                    if (u.suspension_reason) {
                        selectReason(u.suspension_reason);
                        updateInitialSelection('dropdown-reasons', u.suspension_reason);
                    }

                } else {
                    currentUserState.isSuspended = false;
                    currentUserState.isPermanent = false;
                    currentUserState.reason = null;

                    activeAlert.classList.add('d-none');
                    btnLift.classList.add('d-none');
                    btnSaveText.textContent = 'Aplicar Sanción';
                    selectStatus('suspended_temp', 'Suspensión Temporal', 'timer', '#f57c00');
                    updateInitialSelection('dropdown-status-options', 'suspended_temp');
                }

                if (data.history && data.history.length > 0) {
                    renderHistory(data.history);
                } else {
                    historyBody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:16px; color:#888;">Sin historial de suspensiones.</td></tr>';
                }
            }
        } catch(e) { console.error(e); }
    }

    function updateInitialSelection(dropdownId, value) {
        const dropdown = document.getElementById(dropdownId);
        if (!dropdown) return;
        const option = dropdown.querySelector(`.menu-link[data-value="${value}"]`);
        if (option) handleDropdownSelectionVisuals(option);
    }

    function renderHistory(logs) {
        let html = '';
        logs.forEach(log => {
            const start = new Date(log.started_at).toLocaleDateString();
            const adminName = log.admin_name ? log.admin_name : 'Sistema';
            
            let durationDisplay = '';
            let endDisplay = '';

            if (parseInt(log.duration_days) === -1) {
                durationDisplay = 'Permanente';
                endDisplay = 'Indefinido';
            } else {
                durationDisplay = log.duration_days + ' días';
                if (log.ends_at) {
                    endDisplay = new Date(log.ends_at).toLocaleDateString();
                } else {
                    endDisplay = '-';
                }
            }

            let liftedDisplay = '<span style="color:#ccc;">-</span>';
            if (log.lifted_at) {
                const liftedDate = new Date(log.lifted_at).toLocaleDateString();
                const lifter = log.lifter_name ? log.lifter_name : 'Admin';
                liftedDisplay = `
                    <div style="display:flex; flex-direction:column;">
                        <span style="color:#2e7d32; font-weight:600; font-size:12px;">
                            <span class="material-symbols-rounded" style="font-size:14px; vertical-align:text-bottom;">check_circle</span> 
                            ${liftedDate}
                        </span>
                        <span style="color:#888; font-size:11px;">por ${lifter}</span>
                    </div>
                `;
            }

            html += `
                <tr>
                    <td>${start}</td>
                    <td><span style="display:inline-block; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${log.reason}">${log.reason}</span></td>
                    <td>${durationDisplay}</td>
                    <td>${endDisplay}</td>
                    <td><span style="background:#eee; padding:2px 6px; border-radius:4px; font-size:12px;">${adminName}</span></td>
                    <td>${liftedDisplay}</td>
                </tr>`;
        });
        historyBody.innerHTML = html;
    }

    btnSave.onclick = async () => {
        const statusType = inputStatus.value; 
        const reason = inputReason.value;
        const duration = inputDuration.value;

        showError('', false);

        if (!reason) { 
            showError('Debes seleccionar una razón para la sanción.'); 
            return; 
        }

        if (currentUserState.isPermanent && statusType === 'suspended_perm' && reason === currentUserState.reason) {
            showError('Este usuario ya tiene una suspensión permanente activa por el mismo motivo.');
            return;
        }

        btnSave.disabled = true;
        btnSave.innerHTML = '<div class="small-spinner"></div>';

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
                if(window.alertManager) window.alertManager.showAlert(data.message, 'success');
                loadUserData();
            } else {
                showError(data.message);
            }
        } catch(e) {
            showError('Error de conexión.');
        } finally {
            btnSave.disabled = false;
            btnSave.innerHTML = '<span class="material-symbols-rounded">save</span><span id="btn-save-text">' + (currentUserState.isSuspended ? 'Actualizar Sanción' : 'Aplicar Sanción') + '</span>';
        }
    };

    btnLift.onclick = async () => {
        if (!confirm('¿Estás seguro de querer levantar la sanción y reactivar inmediatamente a este usuario?')) return;

        btnLift.disabled = true;
        const originalContent = btnLift.innerHTML;
        btnLift.innerHTML = '<div class="small-spinner" style="border-color:#d32f2f; border-top-color:transparent;"></div>';

        try {
            const res = await fetch(API_ADMIN, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
                body: JSON.stringify({ 
                    action: 'update_user_status',
                    target_id: targetId,
                    status: 'active'
                })
            });
            const data = await res.json();

            if(data.success) {
                if(window.alertManager) window.alertManager.showAlert('Sanción levantada. Usuario activo.', 'success');
                loadUserData();
            } else {
                showError(data.message);
            }
        } catch(e) {
            showError('Error de conexión al intentar levantar la sanción.');
        } finally {
            btnLift.disabled = false;
            btnLift.innerHTML = originalContent;
        }
    };

    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    }

    loadUserData();

})();
