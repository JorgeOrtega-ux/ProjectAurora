/**
 * ProfileController.js
 * Maneja la lógica de la sección de perfil (Datos y Foto).
 */

import { AuthService } from './api-services.js';

/* --- UTILIDADES --- */
const toggleEditMode = (section, isEditing) => {
    const viewState = section.querySelector('[data-state$="-view-state"]');
    const editState = section.querySelector('[data-state$="-edit-state"]');
    const actionsView = section.querySelector('[data-state$="-actions-view"]');
    const actionsEdit = section.querySelector('[data-state$="-actions-edit"]');

    if (isEditing) {
        if(viewState) { viewState.classList.remove('active'); viewState.classList.add('disabled'); }
        if(actionsView) { actionsView.classList.remove('active'); actionsView.classList.add('disabled'); }
        if(editState) { editState.classList.remove('disabled'); editState.classList.add('active'); }
        if(actionsEdit) { actionsEdit.classList.remove('disabled'); actionsEdit.classList.add('active'); }
        const input = section.querySelector('input');
        if(input) input.focus();
    } else {
        if(editState) { editState.classList.remove('active'); editState.classList.add('disabled'); }
        if(actionsEdit) { actionsEdit.classList.remove('active'); actionsEdit.classList.add('disabled'); }
        if(viewState) { viewState.classList.remove('disabled'); viewState.classList.add('active'); }
        if(actionsView) { actionsView.classList.remove('disabled'); actionsView.classList.add('active'); }
    }
};

/* --- LÓGICA DE ACTUALIZACIÓN DE DATOS (TEXTO) --- */
const handleProfileSave = async (sectionType) => {
    const usernameInput = document.querySelector('[data-element="username-input"]');
    const emailInput = document.querySelector('[data-element="email-input"]');
    if (!usernameInput || !emailInput) return;

    const currentUsername = usernameInput.value.trim();
    const currentEmail = emailInput.value.trim();
    
    // UI Loading
    const btn = document.querySelector(`[data-action="${sectionType}-save-trigger-btn"]`);
    const originalText = btn.innerText;
    btn.disabled = true; btn.innerText = "...";

    try {
        const result = await AuthService.updateProfile(currentUsername, currentEmail);

        if (result.status === 'success') {
            const display = document.querySelector(`[data-element="${sectionType}-display-text"]`);
            if (display) display.innerText = (sectionType === 'username') ? '@' + currentUsername : currentEmail;
            
            const section = document.querySelector(`[data-component="${sectionType}-section"]`);
            toggleEditMode(section, false);
        } else {
            alert(result.message || "Error al actualizar.");
        }
    } catch (error) {
        console.error(error);
        alert("Error de conexión.");
    } finally {
        btn.disabled = false; btn.innerText = originalText;
    }
};

/* --- LÓGICA DE FOTO DE PERFIL --- */
const setupProfilePictureLogic = (pfpSection) => {
    const fileInput = pfpSection.querySelector('[data-element="profile-picture-upload-input"]');
    const previewImg = pfpSection.querySelector('[data-element="profile-picture-preview-image"]');
    const actionsDefault = pfpSection.querySelector('[data-state="profile-picture-actions-default"]');
    const actionsPreview = pfpSection.querySelector('[data-state="profile-picture-actions-preview"]');
    const btnDelete = pfpSection.querySelector('[data-action="profile-picture-remove-trigger"]');
    const btnUploadText = pfpSection.querySelector('[data-element="upload-btn-text"]');
    
    // Guardar src original para cancelar
    let originalSrc = previewImg.src;

    // 1. Trigger Input
    const triggerUpload = () => fileInput.click();
    
    // 2. Al seleccionar archivo (Previsualización)
    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (evt) => {
                previewImg.src = evt.target.result; // Mostrar preview local
                // Cambiar botones a modo "Guardar/Cancelar"
                actionsDefault.classList.remove('active'); actionsDefault.classList.add('disabled');
                actionsPreview.classList.remove('disabled'); actionsPreview.classList.add('active');
            };
            reader.readAsDataURL(file);
        }
    });

    // 3. Cancelar subida
    pfpSection.addEventListener('click', (e) => {
        if (e.target.closest('[data-action="profile-picture-cancel-trigger"]')) {
            previewImg.src = originalSrc; // Restaurar
            fileInput.value = ''; // Limpiar input
            actionsPreview.classList.remove('active'); actionsPreview.classList.add('disabled');
            actionsDefault.classList.remove('disabled'); actionsDefault.classList.add('active');
        }

        // 4. Guardar subida (API)
        if (e.target.closest('[data-action="profile-picture-save-trigger-btn"]')) {
            const file = fileInput.files[0];
            if(!file) return;

            const btn = e.target.closest('button');
            const txt = btn.innerText;
            btn.innerText = "Guardando..."; btn.disabled = true;

            AuthService.uploadProfilePicture(file).then(res => {
                if(res.status === 'success') {
                    // Actualizar referencia original con la nueva URL (con timestamp)
                    originalSrc = res.data.url;
                    previewImg.src = originalSrc;
                    
                    // Actualizar header global
                    const headerImg = document.querySelector('.profile-img');
                    if(headerImg) headerImg.src = originalSrc;

                    // Actualizar estado UI (Mostrar botón Eliminar, cambiar texto a "Cambiar")
                    pfpSection.dataset.hasCustom = "true";
                    btnDelete.style.display = "flex";
                    btnUploadText.innerText = "Cambiar";

                    // Volver a default
                    actionsPreview.classList.remove('active'); actionsPreview.classList.add('disabled');
                    actionsDefault.classList.remove('disabled'); actionsDefault.classList.add('active');
                    fileInput.value = '';
                } else {
                    alert(res.message);
                }
            }).catch(err => {
                console.error(err);
                alert("Error de red");
            }).finally(() => {
                btn.innerText = txt; btn.disabled = false;
            });
        }

        // 5. Eliminar foto (API)
        if (e.target.closest('[data-action="profile-picture-remove-trigger"]')) {
            if(!confirm("¿Eliminar foto de perfil actual?")) return;

            // Bloquear UI momentáneamente
            btnDelete.disabled = true;

            AuthService.deleteProfilePicture().then(res => {
                if(res.status === 'success') {
                    // La URL que viene es la de DEFAULT
                    originalSrc = res.data.url;
                    previewImg.src = originalSrc;
                    
                    const headerImg = document.querySelector('.profile-img');
                    if(headerImg) headerImg.src = originalSrc;

                    // Actualizar estado UI
                    // Al ser falso, ocultamos botón eliminar y ponemos "Subir foto"
                    pfpSection.dataset.hasCustom = "false";
                    btnDelete.style.display = "none";
                    btnUploadText.innerText = "Subir foto";
                } else {
                    alert(res.message);
                }
            }).finally(() => {
                btnDelete.disabled = false;
            });
        }
        
        // Delegación para trigger
        if (e.target.closest('[data-action="trigger-profile-picture-upload"]') || 
            e.target.closest('[data-action="profile-picture-upload-trigger"]')) {
            triggerUpload();
        }
    });
};

const setupProfileListeners = () => {
    document.addEventListener('click', (e) => {
        const target = e.target;

        // Delegación genérica para editar textos (username/email)
        if (target.matches('[data-action$="-edit-trigger"]')) {
            const type = target.dataset.action.split('-')[0]; // username o email
            const section = target.closest(`[data-component="${type}-section"]`);
            if(section) {
                const input = section.querySelector('input');
                input.dataset.original = input.value;
                toggleEditMode(section, true);
            }
        }

        if (target.matches('[data-action$="-cancel-trigger"]')) {
            const type = target.dataset.action.split('-')[0];
            // Ignorar el de pfp que se maneja aparte
            if(type !== 'profile') {
                const section = target.closest(`[data-component="${type}-section"]`);
                const input = section.querySelector('input');
                input.value = input.dataset.original || input.value;
                toggleEditMode(section, false);
            }
        }

        if (target.matches('[data-action$="-save-trigger-btn"]')) {
            const type = target.dataset.action.split('-')[0];
            if(type !== 'profile') handleProfileSave(type);
        }
    });

    // Iniciar lógica específica de PFP si existe la sección
    const pfpSection = document.querySelector('[data-component="profile-picture-section"]');
    if (pfpSection) setupProfilePictureLogic(pfpSection);
};

export const initProfileController = () => {
    console.log('ProfileController: Inicializado.');
    setupProfileListeners();
};