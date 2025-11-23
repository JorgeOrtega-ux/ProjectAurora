// public/assets/js/modules/admin-status-manager.js

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

    function handleDropdownSelectionVisuals(selectedOption) {
        const menuList = selectedOption.closest('.menu-list');
        if (!menuList) return;

        const allOptions = menuList.querySelectorAll('.menu-link');
        allOptions.forEach(opt => {
            opt.classList.remove('active');
            const iconContainer = opt.lastElementChild; 
            if (iconContainer) iconContainer.innerHTML = '';
        });

        selectedOption.classList.add('active');
        const activeIconContainer = selectedOption.lastElementChild;
        if (activeIconContainer) activeIconContainer.innerHTML = '<span class="material-symbols-rounded">check</span>';
    }

    document.body.addEventListener('click', (e) => {
        const toggleBtn = e.target.closest('[data-action="toggle-dropdown"]');
        if (toggleBtn) {
            e.stopPropagation();
            const targetId = toggleBtn.dataset.target;
            const targetEl = document.getElementById(targetId);
            if (targetEl) {
                const isCurrentlyOpen = !targetEl.classList.contains('disabled');
                closeAllDropdowns(); 
                if (!isCurrentlyOpen) {
                    targetEl.classList.remove('disabled');
                }
            }
            return;
        }

        const statusOpt = e.target.closest('[data-action="select-status-option"]');
        if (statusOpt) {
            const val = statusOpt.dataset.value;
            const label = statusOpt.dataset.label;
            const icon = statusOpt.dataset.icon;
            const color = statusOpt.dataset.color;
            selectStatus(val, label, icon, color);
            handleDropdownSelectionVisuals(statusOpt); 
            closeAllDropdowns();
            return;
        }

        const durOpt = e.target.closest('[data-action="select-duration-option"]');
        if (durOpt) {
            selectDuration(durOpt.dataset.value);
            handleDropdownSelectionVisuals(durOpt); 
            closeAllDropdowns();
            return;
        }

        const reasonOpt = e.target.closest('[data-action="select-reason-option"]');
        if (reasonOpt) {
            selectReason(reasonOpt.dataset.value);
            handleDropdownSelectionVisuals(reasonOpt);
            closeAllDropdowns();
            return;
        }

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
            }
        } catch(e) { console.error(e); }
    }

    function updateInitialSelection(dropdownId, value) {
        const dropdown = document.getElementById(dropdownId);
        if (!dropdown) return;
        const option = dropdown.querySelector(`.menu-link[data-value="${value}"]`);
        if (option) handleDropdownSelectionVisuals(option);
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