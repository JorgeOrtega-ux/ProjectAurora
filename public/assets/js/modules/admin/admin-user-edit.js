// public/assets/js/modules/admin/admin-user-edit.js

import { AdminApi } from '../../services/api-service.js';
import { t } from '../../core/i18n-manager.js';
import { setButtonLoading, toggleCardError } from '../../core/utilities.js';

let targetUserId = null;
let currentUserData = null;

export function initAdminUserEdit() {
    const inputId = document.getElementById('edit-target-id');
    if (!inputId) return;
    
    targetUserId = inputId.value;
    loadUserData();
    initListeners();
}

async function loadUserData() {
    // [REFACTORIZADO]
    const res = await AdminApi.getUserDetails(targetUserId);

    if (res.success) {
        currentUserData = res.user;
        renderData();
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
}

function renderData() {
    if (!currentUserData) return;

    // --- MANEJO DE AVATAR Y BOTONES ---
    const img = document.getElementById('admin-edit-pfp-img');
    const icon = document.getElementById('admin-edit-pfp-icon');
    const container = document.getElementById('admin-edit-pfp-container');
    
    // Contenedores de botones
    const actionsDefault = document.getElementById('admin-pfp-actions-default');
    const actionsCustom = document.getElementById('admin-pfp-actions-custom');
    const actionsSave = document.getElementById('admin-pfp-actions-save');

    if (container) container.dataset.role = currentUserData.role;

    // Resetear visibilidad botones (ocultar todos primero)
    if (actionsDefault) actionsDefault.className = 'disabled';
    if (actionsCustom) actionsCustom.className = 'disabled';
    if (actionsSave) actionsSave.className = 'disabled';

    let isCustomPfp = false;
    
    if (currentUserData.profile_picture) {
        img.src = (window.BASE_PATH || '/ProjectAurora/') + currentUserData.profile_picture + '?t=' + Date.now();
        img.style.display = 'block';
        icon.style.display = 'none';
        
        // Detectar si es custom (usualmente 'assets/uploads/profile_pictures/custom/...')
        if (currentUserData.profile_picture.includes('/custom/')) {
            isCustomPfp = true;
        }
    } else {
        img.style.display = 'none';
        icon.style.display = 'flex';
    }

    // Mostrar el grupo de botones correcto
    if (isCustomPfp) {
        if (actionsCustom) actionsCustom.className = 'active';
    } else {
        if (actionsDefault) actionsDefault.className = 'active';
    }

    // --- USERNAME ---
    document.getElementById('admin-username-display').textContent = currentUserData.username;
    document.getElementById('admin-username-input').value = currentUserData.username;

    // --- EMAIL ---
    document.getElementById('admin-email-display').textContent = currentUserData.email;
    document.getElementById('admin-email-input').value = currentUserData.email;
}

function initListeners() {
    // --- PROFILE PICTURE ---
    const pfpInput = document.querySelector('[data-element="admin-pfp-input"]');
    const btnsUpload = document.querySelectorAll('[data-action="admin-upload-pfp"]'); // Puede haber más de uno
    const btnOverlay = document.querySelector('[data-action="admin-trigger-pfp-upload"]');
    const btnRemove = document.querySelector('[data-action="admin-remove-pfp"]');
    const btnSavePfp = document.querySelector('[data-action="admin-save-pfp"]');
    const btnCancelPfp = document.querySelector('[data-action="admin-cancel-pfp"]');
    const imgPreview = document.getElementById('admin-edit-pfp-img');

    const triggerUpload = (e) => { 
        if(e) e.preventDefault();
        pfpInput.click(); 
    };
    
    btnsUpload.forEach(btn => btn.onclick = triggerUpload);
    if(btnOverlay) btnOverlay.onclick = triggerUpload;

    if (pfpInput) {
        pfpInput.onchange = (e) => {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = (evt) => {
                imgPreview.src = evt.target.result;
                imgPreview.style.display = 'block';
                togglePfpState('preview'); // Pasar a modo preview
            };
            reader.readAsDataURL(file);
        };
    }

    if (btnCancelPfp) {
        btnCancelPfp.onclick = () => {
            renderData(); // Restaurar imagen y botones originales
            pfpInput.value = '';
        };
    }

    if (btnSavePfp) {
        btnSavePfp.onclick = async () => {
            const file = pfpInput.files[0];
            if (!file) return;

            setButtonLoading(btnSavePfp, true);
            
            try {
                // [REFACTORIZADO] Uso del servicio para subir imagen
                const data = await AdminApi.adminUpdateProfilePicture(targetUserId, file);
                
                if (data.success) {
                    if(window.alertManager) window.alertManager.showAlert(data.message, 'success');
                    currentUserData.profile_picture = data.path; // Actualizar local
                    renderData(); // Esto reseteará los botones al estado correcto
                } else {
                    if(window.alertManager) window.alertManager.showAlert(data.message, 'error');
                }
            } catch (e) {
                console.error(e);
            }
            setButtonLoading(btnSavePfp, false, t('global.save'));
        };
    }

    if (btnRemove) {
        btnRemove.onclick = async () => {
            if (!confirm(t('settings.profile.reset_confirm') || '¿Restablecer foto?')) return;
            setButtonLoading(btnRemove, true);
            
            // [REFACTORIZADO]
            const res = await AdminApi.adminRemoveProfilePicture(targetUserId);
            
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                currentUserData.profile_picture = res.path;
                renderData();
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
            }
            setButtonLoading(btnRemove, false, t('global.delete') || 'Restablecer');
        };
    }

    // --- USERNAME ---
    const btnEditUser = document.querySelector('[data-action="admin-edit-username"]');
    const btnCancelUser = document.querySelector('[data-action="admin-cancel-username"]');
    const btnSaveUser = document.querySelector('[data-action="admin-save-username"]');
    
    if(btnEditUser) btnEditUser.onclick = () => toggleSection('username', true);
    if(btnCancelUser) btnCancelUser.onclick = () => {
        document.getElementById('admin-username-input').value = currentUserData.username;
        toggleSection('username', false);
    };

    if(btnSaveUser) {
        btnSaveUser.onclick = async () => {
            const newVal = document.getElementById('admin-username-input').value.trim();
            if(!newVal) return alert('El nombre no puede estar vacío');
            
            if (newVal === currentUserData.username) {
                toggleSection('username', false);
                return;
            }

            setButtonLoading(btnSaveUser, true);
            
            // [REFACTORIZADO]
            const res = await AdminApi.adminUpdateUsername(targetUserId, newVal);
            
            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                currentUserData.username = newVal;
                renderData();
                toggleSection('username', false);
            } else {
                const card = btnSaveUser.closest('.component-card');
                if (card) toggleCardError(card, res.message);
                else alert(res.message);
            }
            setButtonLoading(btnSaveUser, false, t('global.save'));
        };
    }

    // --- EMAIL ---
    const btnEditEmail = document.querySelector('[data-action="admin-edit-email"]');
    const btnCancelEmail = document.querySelector('[data-action="admin-cancel-email"]');
    const btnSaveEmail = document.querySelector('[data-action="admin-save-email"]');

    if(btnEditEmail) btnEditEmail.onclick = () => toggleSection('email', true);
    if(btnCancelEmail) btnCancelEmail.onclick = () => {
        document.getElementById('admin-email-input').value = currentUserData.email;
        toggleSection('email', false);
    };

    if(btnSaveEmail) {
        btnSaveEmail.onclick = async () => {
            const newVal = document.getElementById('admin-email-input').value.trim();
            if(!newVal) return alert('Email requerido');

            if (newVal === currentUserData.email) {
                toggleSection('email', false);
                return;
            }

            setButtonLoading(btnSaveEmail, true);
            
            // [REFACTORIZADO]
            const res = await AdminApi.adminUpdateEmail(targetUserId, newVal);

            if (res.success) {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
                currentUserData.email = newVal;
                renderData();
                toggleSection('email', false);
            } else {
                const card = btnSaveEmail.closest('.component-card');
                if (card) toggleCardError(card, res.message);
                else alert(res.message);
            }
            setButtonLoading(btnSaveEmail, false, t('global.save'));
        };
    }
}

function togglePfpState(mode) {
    const defActions = document.getElementById('admin-pfp-actions-default');
    const customActions = document.getElementById('admin-pfp-actions-custom');
    const saveActions = document.getElementById('admin-pfp-actions-save');

    // Desactivar todos
    if(defActions) defActions.className = 'disabled';
    if(customActions) customActions.className = 'disabled';
    if(saveActions) saveActions.className = 'disabled';

    if (mode === 'preview') {
        if(saveActions) saveActions.className = 'active';
    } 
}

function toggleSection(section, isEditing) {
    const viewEl = document.getElementById(`admin-${section}-view`);
    const editEl = document.getElementById(`admin-${section}-edit`);
    const actionEl = document.getElementById(`admin-${section}-actions`);
    
    // Reset errores visuales si existen
    const card = viewEl.closest('.component-card');
    if (card) toggleCardError(card, '', false);

    if (isEditing) {
        viewEl.classList.remove('active'); viewEl.classList.add('disabled');
        actionEl.classList.remove('active'); actionEl.classList.add('disabled');
        editEl.classList.remove('disabled'); editEl.classList.add('active');
        
        const input = editEl.querySelector('input');
        if(input) setTimeout(() => input.focus(), 50);

    } else {
        editEl.classList.remove('active'); editEl.classList.add('disabled');
        viewEl.classList.remove('disabled'); viewEl.classList.add('active');
        actionEl.classList.remove('disabled'); actionEl.classList.add('active');
    }
}