/**
 * public/assets/js/core/dialog-definitions.js
 * Repositorio centralizado de configuraciones para diálogos.
 */

import { I18n } from './i18n-manager.js';

export const DialogDefinitions = {
    
    Profile: {
        DELETE_AVATAR: {
            title: '¿Eliminar foto de perfil?',
            get message() { return I18n.t('js.profile.confirm_delete'); },
            type: 'danger',
            confirmText: 'Sí, eliminar',
            cancelText: 'Cancelar'
        }
    },

    Devices: {
        REVOKE_ALL: {
            title: '¿Cerrar todas las sesiones?',
            get message() { return I18n.t('js.devices.confirm_revoke_all'); },
            type: 'danger',
            confirmText: 'Cerrar todas',
            cancelText: 'Cancelar'
        }
    },

    Account: {
        DELETE: {
            title: '¿Eliminar cuenta permanentemente?',
            get message() { return I18n.t('js.delete.confirm_final'); },
            type: 'danger',
            confirmText: 'SÍ, ELIMINAR',
            cancelText: 'Cancelar'
        }
    },

    TwoFactor: {
        DISABLE: {
            title: '¿Desactivar 2FA?',
            get message() { return I18n.t('js.2fa.confirm_disable'); },
            type: 'danger',
            confirmText: 'Desactivar',
            cancelText: 'Cancelar'
        },
        // Este diálogo usa un template especial HTML (template-regen-codes)
        // por lo que no necesita título/mensaje aquí, ya están en el HTML.
        REGENERATE: {
            type: 'regen-codes'
        }
    }
};