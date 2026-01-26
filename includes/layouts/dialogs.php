<div id="dialog-overlay" class="component-overlay">
    <div class="component-dialog-wrapper">
        
        <div id="dialog-content-wrapper" class="component-dialog">
            <div class="component-dialog-drag-zone">
                <div class="component-dialog-drag-handle"></div>
            </div>
            <div class="component-spinner-large"></div>
        </div>

    </div>
</div>

<div id="dialog-templates" style="display: none;">

    <div id="template-alert">
        <div class="component-dialog-body">
            <h1 class="component-dialog-title"></h1>
            <p class="component-dialog-message"></p>
        </div>
        <div class="component-dialog-footer">
            <button type="button" class="component-button primary btn-accept">Aceptar</button>
        </div>
    </div>

    <div id="template-confirm">
        <div class="component-dialog-body">
            <h1 class="component-dialog-title"></h1>
            <p class="component-dialog-message"></p>
        </div>
        <div class="component-dialog-footer">
            <button type="button" class="component-button primary btn-confirm">Confirmar</button>
            <button type="button" class="component-button btn-cancel">Cancelar</button>
        </div>
    </div>

    <div id="template-danger">
        <div class="component-dialog-body">
            <h1 class="component-dialog-title"></h1>
            <p class="component-dialog-message"></p>
        </div>
        <div class="component-dialog-footer">
            <button type="button" class="component-button primary btn-confirm">Eliminar</button>
            <button type="button" class="component-button btn-cancel">Cancelar</button>
        </div>
    </div>

    <div id="template-regen-codes">
        <div class="component-dialog-body">
            <h1 class="component-dialog-title">¿Necesitas nuevos códigos de verificación?</h1>
            <p class="component-dialog-message">Ya generaste códigos de verificación, y todavía deberían funcionar. Si decides generar nuevos códigos, se desactivarán los anteriores.</p>
        </div>
        <div class="component-dialog-footer">
            <button type="button" class="component-button btn-cancel">Mantener los códigos anteriores activos</button>
            <button type="button" class="component-button primary btn-confirm">Generar nuevos códigos</button>
        </div>
    </div>

    <div id="template-verify-email">
        <div class="component-dialog-body">
            <h1 class="component-dialog-title"></h1>
            <p class="component-dialog-message"></p>
            <div class="component-input-wrapper mt-16">
                <input type="text" id="verify-email-code" class="component-text-input" placeholder="000 000" maxlength="6" style="text-align: center; letter-spacing: 4px; font-size: 18px;">
            </div>
            <p style="text-align: center; font-size: 13px; margin-top: 8px;">
                <a href="#" id="btn-dialog-resend" style="text-decoration: none; color: var(--action-primary); font-weight: 500;">Reenviar código</a> 
                <span id="dialog-resend-timer" style="color: var(--text-secondary);"></span>
            </p>
        </div>
        <div class="component-dialog-footer">
            <button type="button" class="component-button btn-cancel">Cancelar</button>
            <button type="button" class="component-button primary btn-confirm">Verificar</button>
        </div>
    </div>

    <div id="template-loading">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div class="component-spinner-large"></div>
            <h1 class="component-dialog-title">Cargando...</h1>
        </div>
    </div>

</div>