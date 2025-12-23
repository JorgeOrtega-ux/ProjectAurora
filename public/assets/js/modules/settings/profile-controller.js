/**
 * public/assets/js/modules/settings/profile-controller.js
 * Controla la lógica específica de la foto de perfil: Subida, Previsualización y Borrado.
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';

export const ProfileController = {
    
    init: () => {
        const fileInput = document.getElementById('upload-avatar');
        const previewImg = document.getElementById('preview-avatar');
        
        // Si no estamos en la vista de perfil, no hacemos nada
        if (!fileInput || !previewImg) return;

        console.log("ProfileController: Inicializado");

        // Guardar estado inicial (src original) para poder cancelar
        let originalSrc = previewImg.src;

        // --- 1. DETECCIÓN DE CAMBIO DE ARCHIVO (PREVIEW) ---
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                // Validar tipo
                if (!file.type.startsWith('image/')) {
                    Toast.show('Por favor selecciona un archivo de imagen válido.', 'error');
                    return;
                }

                const reader = new FileReader();
                reader.onload = (evt) => {
                    previewImg.src = evt.target.result;
                    // Cambiar UI a modo "Preview" (Botones Guardar/Cancelar)
                    toggleProfileActions('preview');
                };
                reader.readAsDataURL(file);
            }
        });

        // --- 2. DELEGACIÓN DE BOTONES ---
        const container = document.querySelector('[data-component="profile-picture-section"]');
        
        container.addEventListener('click', async (e) => {
            const action = e.target.dataset.action;
            if (!action) return;

            // A) CANCELAR (Vuelve al estado anterior)
            if (action === 'profile-picture-cancel') {
                previewImg.src = originalSrc;
                fileInput.value = ''; // Limpiar input
                
                // Determinar a qué estado volver (si el original era custom o default)
                // Usamos una clase auxiliar o atributo en la imagen para saberlo, o chequeamos la URL
                const isCustom = originalSrc.includes('/custom/');
                toggleProfileActions(isCustom ? 'custom' : 'default');
            }

            // B) GUARDAR (Sube la foto)
            if (action === 'profile-picture-save') {
                const file = fileInput.files[0];
                if (!file) return;

                const btn = e.target;
                const originalText = btn.innerText;
                btn.innerText = 'Guardando...';
                btn.disabled = true;

                const formData = new FormData();
                formData.append('action', 'upload_avatar');
                formData.append('avatar', file);

                const res = await ApiService.post('settings-handler.php', formData);

                btn.innerText = originalText;
                btn.disabled = false;

                if (res.success) {
                    Toast.show('Foto de perfil actualizada', 'success');
                    // Actualizar referencia "original" a la nueva
                    // Nota: recargamos la página o actualizamos el src con la respuesta para asegurar ruta correcta
                    // Como ApiService devuelve JSON, asumimos que index.php manejará el src en recarga, 
                    // pero para SPA, actualizamos visualmente.
                    
                    // IMPORTANTE: Para ver cambios de imagen con el mismo nombre (si fuera el caso) se usa timestamp,
                    // aquí el servidor nos dio nombre nuevo, así que no hay problema de caché.
                    
                    // Si el backend devuelve ruta relativa 'storage/...', necesitamos la base
                    // Aquí usamos el FileReader result que ya está puesto, o recargamos.
                    // Para ser precisos, actualizamos originalSrc.
                    originalSrc = previewImg.src; 
                    
                    toggleProfileActions('custom');
                    fileInput.value = ''; // Limpiar input file
                } else {
                    Toast.show(res.message, 'error');
                }
            }

            // C) ELIMINAR (Borra custom, vuelve a default)
            if (action === 'profile-picture-delete') {
                if(!confirm('¿Estás seguro de querer eliminar tu foto de perfil?')) return;

                const btn = e.target;
                btn.disabled = true;

                const formData = new FormData();
                formData.append('action', 'delete_avatar');

                const res = await ApiService.post('settings-handler.php', formData);

                btn.disabled = false;

                if (res.success) {
                    Toast.show('Foto eliminada. Se ha generado una por defecto.', 'info');
                    // Recargar la imagen. Como es nueva, necesitamos saber la URL
                    // Lo ideal es recargar la página para que PHP regenere el base64 o ruta
                    // O hacer un fetch de la nueva imagen.
                    // Para SPA simple:
                    window.location.reload(); 
                } else {
                    Toast.show(res.message, 'error');
                }
            }

            // D) CAMBIAR (Abre input file)
            if (action === 'profile-picture-change') {
                fileInput.click();
            }
        });
    }
};

/**
 * Cambia la visibilidad de los grupos de botones
 * @param {string} state - 'default', 'preview', 'custom'
 */
function toggleProfileActions(state) {
    const actionsDefault = document.querySelector('[data-state="profile-picture-actions-default"]'); // Btn: Subir foto
    const actionsPreview = document.querySelector('[data-state="profile-picture-actions-preview"]'); // Btns: Guardar, Cancelar
    const actionsCustom  = document.querySelector('[data-state="profile-picture-actions-custom"]');  // Btns: Eliminar, Cambiar

    // Resetear todos a ocultos
    if(actionsDefault) actionsDefault.classList.add('disabled');
    if(actionsDefault) actionsDefault.classList.remove('active');
    
    if(actionsPreview) actionsPreview.classList.add('disabled');
    if(actionsPreview) actionsPreview.classList.remove('active');
    
    if(actionsCustom)  actionsCustom.classList.add('disabled');
    if(actionsCustom)  actionsCustom.classList.remove('active');

    // Activar el solicitado
    if (state === 'default' && actionsDefault) {
        actionsDefault.classList.remove('disabled');
        actionsDefault.classList.add('active');
    } else if (state === 'preview' && actionsPreview) {
        actionsPreview.classList.remove('disabled');
        actionsPreview.classList.add('active');
    } else if (state === 'custom' && actionsCustom) {
        actionsCustom.classList.remove('disabled');
        actionsCustom.classList.add('active');
    }
}