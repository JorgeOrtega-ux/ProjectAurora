// public/assets/js/dialog-config.js

export const DIALOG_CONFIG = {
    'dialog-delete-avatar': {
        title: 'Eliminar foto de perfil',
        body: '¿Estás seguro de que deseas eliminar tu foto de perfil? Se te asignará una nueva generada automáticamente en base a tu nombre.',
        buttons: [
            { id: 'btn-cancel-delete', text: 'Cancelar', action: 'close', class: 'component-button' },
            { id: 'btn-confirm-delete-avatar', text: 'Eliminar', action: 'confirm', class: 'component-button primary', style: 'background-color: #d32f2f; border-color: #d32f2f;' }
        ]
    },
    'dialog-verify-email': {
        title: 'Verificar cambio de correo',
        body: `Hemos enviado un código de seguridad a tu <b>correo actual</b>. Ingrésalo para autorizar el cambio a tu nuevo correo.
               <div class="component-input-wrapper" style="margin-top: 16px;">
                   <input type="text" id="input-email-code" class="component-text-input" placeholder=" " maxlength="6" style="letter-spacing: 4px; text-align: center; font-weight: bold; font-size: 18px;">
                   <label for="input-email-code" class="component-label-floating" style="left: 12px; transform: translateY(-50%); width: 100%; text-align: center;">Código de 6 dígitos</label>
               </div>`,
        buttons: [
            { id: 'btn-cancel-email', text: 'Cancelar', action: 'close', class: 'component-button' },
            { id: 'btn-confirm-email-code', text: 'Verificar y Guardar', action: 'confirm', class: 'component-button primary' }
        ]
    }
};