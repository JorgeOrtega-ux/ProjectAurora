/**
 * public/assets/js/modules/settings/profile-controller.js
 */

export const ProfileController = {
    init: () => {
        console.log("ProfileController: Inicializado");
        initAvatarInteractions(); 
        initIdentityInteractions();
    }
};

function getCsrfToken() {
    const input = document.getElementById('profile-csrf');
    return input ? input.value : '';
}

/* --- FOTO DE PERFIL --- */
function initAvatarInteractions() {
    const container = document.querySelector('[data-component="profile-picture-section"]');
    const fileInput = document.getElementById('upload-avatar');
    const previewImg = document.getElementById('preview-avatar');
    
    // Botones
    const triggerBtn = document.getElementById('btn-trigger-upload');
    const initBtn = document.getElementById('btn-upload-init');
    const changeBtn = document.querySelector('[data-action="profile-picture-change"]');

    if (!container || !fileInput || !previewImg) return;

    let originalSrc = previewImg.src;

    const openFileSelector = () => fileInput.click();
    if(triggerBtn) triggerBtn.addEventListener('click', openFileSelector);
    if(initBtn) initBtn.addEventListener('click', openFileSelector);
    if(changeBtn) changeBtn.addEventListener('click', openFileSelector);

    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (evt) => {
                previewImg.src = evt.target.result;
                toggleAvatarActions('preview'); 
            };
            reader.readAsDataURL(file);
        }
    });

    container.addEventListener('click', async (e) => {
        const btn = e.target.closest('button');
        if (!btn || !btn.dataset.action) return;
        const action = btn.dataset.action;

        if (action === 'profile-picture-cancel') {
            previewImg.src = originalSrc;
            fileInput.value = ''; 
            const state = originalSrc.includes('ui-avatars') ? 'default' : 'custom';
            toggleAvatarActions(state);
        }

        if (action === 'profile-picture-save') {
            const file = fileInput.files[0];
            if (!file) return;

            startLoading(btn, 'Guardando...');
            
            const formData = new FormData();
            formData.append('action', 'settings_update_avatar');
            formData.append('avatar', file);
            formData.append('csrf_token', getCsrfToken());

            try {
                const response = await fetch(window.BASE_PATH, { method: 'POST', body: formData });
                const data = await response.json();

                if (data.status) {
                    originalSrc = data.newUrl;
                    previewImg.src = data.newUrl;
                    toggleAvatarActions('custom');
                    updateGlobalHeaderAvatar(data.newUrl);
                    alert('Foto actualizada.');
                } else {
                    alert(data.message);
                    previewImg.src = originalSrc;
                }
            } catch (err) {
                console.error(err);
                alert('Error de conexión.');
            } finally {
                stopLoading(btn, 'Guardar');
            }
        }

        if (action === 'profile-picture-delete') {
            if(confirm('¿Eliminar foto personalizada?')) {
                startLoading(btn, '...');
                const formData = new FormData();
                formData.append('action', 'settings_delete_avatar');
                formData.append('csrf_token', getCsrfToken());

                try {
                    const response = await fetch(window.BASE_PATH, { method: 'POST', body: formData });
                    const data = await response.json();

                    if (data.status) {
                        originalSrc = data.newUrl;
                        previewImg.src = data.newUrl;
                        toggleAvatarActions('default');
                        updateGlobalHeaderAvatar(data.newUrl);
                    } else {
                        alert(data.message);
                    }
                } catch (err) { alert('Error de conexión.'); }
                finally { stopLoading(btn, 'Eliminar'); }
            }
        }
    });
}

function updateGlobalHeaderAvatar(url) {
    const headerImg = document.querySelector('.header-profile-img');
    if (headerImg) headerImg.src = url;
}

function toggleAvatarActions(state) {
    const actionsDefault = document.querySelector('[data-state="profile-picture-actions-default"]'); 
    const actionsPreview = document.querySelector('[data-state="profile-picture-actions-preview"]'); 
    const actionsCustom  = document.querySelector('[data-state="profile-picture-actions-custom"]'); 

    [actionsDefault, actionsPreview, actionsCustom].forEach(el => {
        if(el) { el.classList.add('disabled'); el.classList.remove('active'); }
    });

    if (state === 'default' && actionsDefault) {
        actionsDefault.classList.remove('disabled'); actionsDefault.classList.add('active');
    } else if (state === 'preview' && actionsPreview) {
        actionsPreview.classList.remove('disabled'); actionsPreview.classList.add('active');
    } else if (state === 'custom' && actionsCustom) {
        actionsCustom.classList.remove('disabled'); actionsCustom.classList.add('active');
    }
}

/* --- USERNAME / EMAIL --- */
function initIdentityInteractions() {
    const wrapper = document.querySelector('.component-wrapper');
    if (!wrapper) return;

    wrapper.addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn || !btn.dataset.action) return;

        const action = btn.dataset.action;
        const targetField = btn.dataset.target; 

        if (!targetField) return;

        if (action === 'start-edit') toggleEditState(targetField, true);
        else if (action === 'cancel-edit') toggleEditState(targetField, false);
        else if (action === 'save-field') saveFieldData(targetField, btn);
    });
}

/**
 * AQUÍ ESTÁ EL CAMBIO: toggleEditState
 */
function toggleEditState(fieldId, isEditing) {
    const parent = document.querySelector(`[data-component="${fieldId}-section"]`);
    if (!parent) return;

    const viewState = parent.querySelector(`[data-state="${fieldId}-view-state"]`);
    const editState = parent.querySelector(`[data-state="${fieldId}-edit-state"]`);
    const actionsView = parent.querySelector(`[data-state="${fieldId}-actions-view"]`);
    const actionsEdit = parent.querySelector(`[data-state="${fieldId}-actions-edit"]`);
    const input = parent.querySelector('input');

    if (isEditing) {
        hide(viewState); hide(actionsView);
        show(editState); show(actionsEdit);
        if (input) {
            // Guardamos valor original por si cancela
            input.dataset.originalValue = input.value;
            
            // --- TRUCO DEL CURSOR ---
            // 1. Guardamos el valor actual en una variable
            const val = input.value;
            // 2. Vaciamos el input
            input.value = '';
            // 3. Volvemos a poner el valor (esto empuja el cursor al final)
            input.value = val;
            
            input.focus();
        }
    } else {
        hide(editState); hide(actionsEdit);
        show(viewState); show(actionsView);
        if (input && input.dataset.originalValue) {
            input.value = input.dataset.originalValue;
        }
    }
}

async function saveFieldData(fieldId, btnSave) {
    const parent = document.querySelector(`[data-component="${fieldId}-section"]`);
    const input = parent.querySelector('input');
    const display = parent.querySelector('.text-display-value');

    if (!input || !display) return;
    const newValue = input.value.trim();
    
    if (!newValue) { alert('Campo vacío.'); return; }
    if (newValue === input.dataset.originalValue) { toggleEditState(fieldId, false); return; }

    startLoading(btnSave, 'Guardando...');

    const formData = new FormData();
    formData.append('action', 'settings_update_field');
    formData.append('field', fieldId);
    formData.append('value', newValue);
    formData.append('csrf_token', getCsrfToken());

    try {
        const response = await fetch(window.BASE_PATH, { method: 'POST', body: formData });
        const data = await response.json();

        if (data.status) {
            display.textContent = newValue;
            input.dataset.originalValue = newValue;
            toggleEditState(fieldId, false);
        } else {
            alert(data.message);
        }
    } catch (err) {
        console.error(err);
        alert('Error de conexión');
    } finally {
        stopLoading(btnSave, 'Guardar');
    }
}

function show(el) { if(el) { el.classList.remove('disabled'); el.classList.add('active'); } }
function hide(el) { if(el) { el.classList.remove('active'); el.classList.add('disabled'); } }

function startLoading(btn, loadingText) {
    btn.dataset.originalText = btn.innerText;
    btn.innerText = loadingText;
    btn.disabled = true;
    btn.style.opacity = '0.7';
}
function stopLoading(btn, originalText) {
    btn.innerText = originalText || btn.dataset.originalText;
    btn.disabled = false;
    btn.style.opacity = '1';
}