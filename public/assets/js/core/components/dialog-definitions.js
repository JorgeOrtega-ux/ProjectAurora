import { I18nManager } from '../utils/i18n-manager.js';

const DialogTemplates = {
    default: (data) => `
        <div class="component-dialog-body">
            <h1 class="component-dialog-title" data-element="title">${data.title || ''}</h1>
            <p class="component-dialog-message" data-element="message">${data.message || ''}</p>
            <div data-element="content-area"></div>
        </div>
        <div class="component-dialog-footer">
            <button type="button" class="component-button btn-cancel" data-action="cancel">${data.cancelText || 'Cancelar'}</button>
            <button type="button" class="component-button primary btn-confirm" data-action="confirm">${data.confirmText || 'Confirmar'}</button>
        </div>
    `,
  'verify-email': (data) => `
        <div class="component-dialog-body">
            <h1 class="component-dialog-title" data-element="title">${data.title || ''}</h1>
            <p class="component-dialog-message" data-element="message">${data.message || ''}</p>
            
            <div class="component-input-wrapper mt-16">
                <input type="text" id="verify-email-code" class="component-text-input" 
                       placeholder="Ingresa el código" 
                       maxlength="6" 
                       autocomplete="off">
            </div>
            
            <p style="text-align: right; font-size: 12px;">
                <span data-action="resend-code" id="btn-dialog-resend" class="component-link-action" role="button" tabindex="0" style="color: var(--action-primary);">
                    Reenviar código de verificación <span data-element="resend-timer" id="dialog-resend-timer" style="margin-left: 4px;"></span>
                </span>
            </p>
        </div>
        <div class="component-dialog-footer">
            <button type="button" class="component-button btn-cancel" data-action="cancel">Cancelar</button>
            <button type="button" class="component-button primary btn-confirm" data-action="confirm">Verificar</button>
        </div>
    `,
    loading: (data) => `
        <div style="display: flex; align-items: center; gap: 16px; padding: 10px 0;">
            <div class="component-spinner-large"></div>
            <h1 class="component-dialog-title" style="font-size: 18px; margin:0;">${data.title || 'Cargando...'}</h1>
        </div>
    `
};

const DialogDefinitions = {
    Profile: {
        DELETE_AVATAR: {
            title: '¿Eliminar foto de perfil?',
            get message() { return I18nManager.t('js.profile.confirm_delete'); },
            type: 'danger',
            confirmText: 'Sí, eliminar',
            cancelText: 'Cancelar'
        },
        VERIFY_EMAIL: {
            get title() { return I18nManager.t('settings.profile.verify_email_title'); },
            get message() { return I18nManager.t('settings.profile.verify_email_msg'); },
            type: 'verify-email', 
            confirmText: 'Verificar',
            cancelText: 'Cancelar'
        }
    },
    Devices: {
        REVOKE_ALL: {
            title: '¿Cerrar todas las sesiones?',
            get message() { return I18nManager.t('js.devices.confirm_revoke_all'); },
            type: 'danger',
            confirmText: 'Cerrar todas',
            cancelText: 'Cancelar'
        },
        REVOKE_ONE: {
            title: '¿Cerrar sesión?',
            get message() { return I18nManager.t('js.devices.confirm_revoke_one'); },
            type: 'danger',
            confirmText: 'Cerrar sesión',
            cancelText: 'Cancelar'
        }
    },
    Account: {
        DELETE: {
            title: '¿Eliminar cuenta permanentemente?',
            get message() { return I18nManager.t('js.delete.confirm_final'); },
            type: 'danger',
            confirmText: 'SÍ, ELIMINAR',
            cancelText: 'Cancelar'
        }
    },
    TwoFactor: {
        DISABLE: {
            title: '¿Desactivar 2FA?',
            get message() { return I18nManager.t('js.2fa.confirm_disable'); },
            type: 'danger',
            confirmText: 'Desactivar',
            cancelText: 'Cancelar'
        },
        REGENERATE: {
            title: '¿Generar nuevos códigos?',
            message: 'Ya generaste códigos anteriormente. Si generas nuevos, los anteriores dejarán de funcionar.',
            type: 'default',
            confirmText: 'Generar nuevos',
            cancelText: 'Mantener actuales'
        }
    }
};

export { DialogTemplates, DialogDefinitions };