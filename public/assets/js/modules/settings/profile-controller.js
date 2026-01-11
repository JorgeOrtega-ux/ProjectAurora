/**
 * public/assets/js/modules/settings/profile-controller.js
 * Controlador para la sección "Tu Perfil" (Project Aurora)
 */

export const ProfileController = {
    
    init: () => {
        console.log("ProfileController: Inicializado");
        initAvatarInteractions(); // Lógica de foto (UI)
        initIdentityInteractions(); // Lógica de Username/Email (UI)
    }
};

/**
 * 1. Lógica para la Foto de Perfil (Visual)
 */
function initAvatarInteractions() {
    const container = document.querySelector('[data-component="profile-picture-section"]');
    const fileInput = document.getElementById('upload-avatar');
    const previewImg = document.getElementById('preview-avatar');
    
    // Botones disparadores
    const triggerBtn = document.getElementById('btn-trigger-upload'); // Icono cámara
    const initBtn = document.getElementById('btn-upload-init');       // Botón "Subir foto"
    const changeBtn = document.querySelector('[data-action="profile-picture-change"]'); // Botón "Cambiar"

    if (!container || !fileInput || !previewImg) return;

    // Guardar src original para poder cancelar
    let originalSrc = previewImg.src;

    // Abrir selector de archivos
    const openFileSelector = () => fileInput.click();
    
    if(triggerBtn) triggerBtn.addEventListener('click', openFileSelector);
    if(initBtn) initBtn.addEventListener('click', openFileSelector);
    if(changeBtn) changeBtn.addEventListener('click', openFileSelector);

    // Cuando se selecciona un archivo
    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            // Previsualizar imagen
            const reader = new FileReader();
            reader.onload = (evt) => {
                previewImg.src = evt.target.result;
                toggleAvatarActions('preview'); // Mostrar Guardar/Cancelar
            };
            reader.readAsDataURL(file);
        }
    });

    // Manejo de clicks en acciones (Guardar/Cancelar/Borrar)
    container.addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn || !btn.dataset.action) return;
        const action = btn.dataset.action;

        if (action === 'profile-picture-cancel') {
            // Revertir cambio
            previewImg.src = originalSrc;
            fileInput.value = ''; 
            
            // Volver al estado anterior (depende de si tenía foto custom o no)
            // Por defecto asumimos que si cancela vuelve a lo que estaba.
            // Aquí simplificamos: si la original era default, vamos a default.
            const isCustom = originalSrc.includes('custom') || originalSrc.includes('blob:'); // Simplificación
            toggleAvatarActions('default'); 
            // NOTA: En backend real, deberías chequear si el usuario tiene avatar para saber si volver a 'custom' o 'default'
        }

        if (action === 'profile-picture-save') {
            // AQUI IRÍA LA LLAMADA AL BACKEND
            startLoading(btn, 'Guardando...');
            
            // Simulación de éxito tras 1 segundo
            setTimeout(() => {
                stopLoading(btn, 'Guardar');
                originalSrc = previewImg.src; // Confirmar nuevo src
                toggleAvatarActions('custom'); // Mostrar acciones de "Borrar/Cambiar"
                alert('Foto actualizada (Simulación)');
            }, 1000);
        }

        if (action === 'profile-picture-delete') {
            if(confirm('¿Estás seguro de eliminar tu foto?')) {
                previewImg.src = 'public/assets/img/avatars/default.png'; // Reset a default
                originalSrc = previewImg.src;
                toggleAvatarActions('default');
            }
        }
    });
}

function toggleAvatarActions(state) {
    // Selectores de los 3 grupos de botones
    const actionsDefault = document.querySelector('[data-state="profile-picture-actions-default"]'); 
    const actionsPreview = document.querySelector('[data-state="profile-picture-actions-preview"]'); 
    const actionsCustom  = document.querySelector('[data-state="profile-picture-actions-custom"]'); 

    // Ocultar todos primero (clase disabled)
    [actionsDefault, actionsPreview, actionsCustom].forEach(el => {
        if(el) { 
            el.classList.add('disabled'); 
            el.classList.remove('active'); 
        }
    });

    // Mostrar el solicitado
    if (state === 'default' && actionsDefault) {
        actionsDefault.classList.remove('disabled'); actionsDefault.classList.add('active');
    } else if (state === 'preview' && actionsPreview) {
        actionsPreview.classList.remove('disabled'); actionsPreview.classList.add('active');
    } else if (state === 'custom' && actionsCustom) {
        actionsCustom.classList.remove('disabled'); actionsCustom.classList.add('active');
    }
}

/**
 * 2. Lógica para Username y Email (Editar/Cancelar/Guardar)
 */
function initIdentityInteractions() {
    // Usamos delegación de eventos en el wrapper principal
    const wrapper = document.querySelector('.component-wrapper');
    if (!wrapper) return;

    wrapper.addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn || !btn.dataset.action) return;

        const action = btn.dataset.action;
        const targetField = btn.dataset.target; // 'username' o 'email'

        if (!targetField) return; // Si el botón no es de un campo, ignorar

        if (action === 'start-edit') {
            toggleEditState(targetField, true);
        } 
        else if (action === 'cancel-edit') {
            toggleEditState(targetField, false);
        } 
        else if (action === 'save-field') {
            saveFieldData(targetField, btn);
        }
    });
}

/**
 * Alterna entre modo vista (texto) y modo edición (input)
 */
function toggleEditState(fieldId, isEditing) {
    const parent = document.querySelector(`[data-component="${fieldId}-section"]`);
    if (!parent) return;

    // Referencias a los contenedores de estado
    const viewState = parent.querySelector(`[data-state="${fieldId}-view-state"]`);
    const editState = parent.querySelector(`[data-state="${fieldId}-edit-state"]`);
    
    // Referencias a los botones
    const actionsView = parent.querySelector(`[data-state="${fieldId}-actions-view"]`);
    const actionsEdit = parent.querySelector(`[data-state="${fieldId}-actions-edit"]`);
    
    const input = parent.querySelector('input');

    if (isEditing) {
        // ACTIVAR EDICIÓN
        hide(viewState);
        hide(actionsView);
        
        show(editState);
        show(actionsEdit);
        
        // Guardar valor original para restaurar si cancela
        if (input) {
            input.dataset.originalValue = input.value;
            input.focus();
        }
    } else {
        // CANCELAR / VOLVER A VISTA
        hide(editState);
        hide(actionsEdit);
        
        show(viewState);
        show(actionsView);
        
        // Restaurar valor original en el input
        if (input && input.dataset.originalValue) {
            input.value = input.dataset.originalValue;
        }
    }
}

/**
 * Simula el guardado de datos
 */
function saveFieldData(fieldId, btnSave) {
    const parent = document.querySelector(`[data-component="${fieldId}-section"]`);
    const input = parent.querySelector('input');
    const display = parent.querySelector('.text-display-value');

    if (!input || !display) return;

    const newValue = input.value.trim();
    
    // Validar que no esté vacío
    if (!newValue) {
        alert('El campo no puede estar vacío');
        return;
    }

    // UI Loading
    startLoading(btnSave, 'Guardando...');

    // Simulación de petición API
    setTimeout(() => {
        // ÉXITO
        stopLoading(btnSave, 'Guardar');
        
        // Actualizar texto en pantalla
        display.textContent = newValue;
        
        // Actualizar valor original (para que al cancelar la próxima vez, vuelva a este nuevo valor)
        input.dataset.originalValue = newValue;
        
        // Cerrar modo edición
        toggleEditState(fieldId, false);
        
        console.log(`Guardado exitoso para ${fieldId}: ${newValue}`);
    }, 800);
}

// --- Utilidades UI ---

function show(el) {
    if(el) {
        el.classList.remove('disabled');
        el.classList.add('active');
    }
}

function hide(el) {
    if(el) {
        el.classList.remove('active');
        el.classList.add('disabled');
    }
}

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