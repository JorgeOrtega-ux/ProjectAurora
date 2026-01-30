/**
 * public/assets/js/core/dialog-definitions.js
 * Definiciones de plantillas HTML y configuraciones de diálogos.
 */

export const DialogTemplates = {
    BASE: `
        <div class="component-dialog-drag-zone" data-action="drag-handle">
            <div class="component-dialog-drag-handle"></div>
        </div>
        <div class="component-dialog-body">
            <h1 class="component-dialog-title" data-element="title"></h1>
            <p class="component-dialog-message" data-element="message"></p>
            <div data-element="content-area"></div>
        </div>
        <div class="component-dialog-footer" data-element="footer"></div>
    `,
    
    // Contenidos específicos para inyectar en content-area
    VERIFY_EMAIL: `
        <div class="component-input-wrapper mt-16">
            <input type="text" data-element="input-code" class="component-text-input" placeholder="000 000" maxlength="6" style="text-align: center; letter-spacing: 4px; font-size: 18px;" autocomplete="off">
        </div>
        <p style="text-align: center; font-size: 13px; margin-top: 8px;">
            <a href="#" data-action="resend-code" style="text-decoration: none; color: var(--action-primary); font-weight: 500;">Reenviar código</a> 
            <span data-element="resend-timer" style="color: var(--text-secondary);"></span>
        </p>
    `,

    LOADING: `
        <div style="display: flex; align-items: center; gap: 16px;">
            <div class="component-spinner-large"></div>
            <h1 class="component-dialog-title" data-element="title">Cargando...</h1>
        </div>
    `
};

export const DialogDefinitions = {
    // Configuraciones predefinidas
    Profile: {
        DELETE_AVATAR: { type: 'danger', confirmText: 'Sí, eliminar', cancelText: 'Cancelar' },
        VERIFY_EMAIL: { type: 'verify-email', confirmText: 'Verificar', cancelText: 'Cancelar' }
    },
    Devices: {
        REVOKE_ALL: { type: 'danger', confirmText: 'Cerrar todas', cancelText: 'Cancelar' },
        REVOKE_ONE: { type: 'danger', confirmText: 'Cerrar sesión', cancelText: 'Cancelar' }
    },
    Account: {
        DELETE: { type: 'danger', confirmText: 'SÍ, ELIMINAR', cancelText: 'Cancelar' }
    },
    TwoFactor: {
        DISABLE: { type: 'danger', confirmText: 'Desactivar', cancelText: 'Cancelar' },
        REGENERATE: { type: 'default', confirmText: 'Generar nuevos', cancelText: 'Mantener actuales' }
    }
};