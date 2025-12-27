<div id="dialog-overlay" class="dialog-overlay">
    <div class="dialog-container">
        
        <div id="dialog-content-wrapper" class="dialog-card">
            <div class="dialog-pill-container">
                <div class="dialog-drag-handle"></div>
            </div>
            <div class="dialog-spinner"></div>
        </div>

    </div>
</div>

<div id="dialog-templates" style="display: none;">

    <div id="template-alert">
        <div class="dialog-content">
            <h1 class="dialog-title"></h1>
            <p class="dialog-message"></p>
        </div>
        <div class="dialog-actions">
            <button type="button" class="component-button primary btn-accept">Aceptar</button>
        </div>
    </div>

    <div id="template-confirm">
        <div class="dialog-content">
            <h1 class="dialog-title"></h1>
            <p class="dialog-message"></p>
        </div>
        <div class="dialog-actions">
            <button type="button" class="component-button primary btn-confirm">Confirmar</button>
            <button type="button" class="component-button btn-cancel">Cancelar</button>
        </div>
    </div>

    <div id="template-danger">
        <div class="dialog-content">
            <h1 class="dialog-title"></h1>
            <p class="dialog-message"></p>
        </div>
        <div class="dialog-actions">
            <button type="button" class="component-button primary btn-confirm">Eliminar</button>
            <button type="button" class="component-button btn-cancel">Cancelar</button>
        </div>
    </div>

    <div id="template-regen-codes">
        <div class="dialog-content">
            <h1 class="dialog-title">¿Necesitas nuevos códigos de verificación?</h1>
            <p class="dialog-message">Ya generaste códigos de verificación, y todavía deberían funcionar. Si decides generar nuevos códigos, se desactivarán los anteriores.</p>
        </div>
        <div class="dialog-actions">
            <button type="button" class="component-button btn-cancel">Mantener los códigos anteriores activos</button>
            <button type="button" class="component-button primary btn-confirm">Generar nuevos códigos</button>
        </div>
    </div>

    <div id="template-verify-email">
        <div class="dialog-content">
            <h1 class="dialog-title"></h1>
            <p class="dialog-message"></p>
            <div class="component-input-wrapper mt-16">
                <input type="text" id="verify-email-code" class="component-text-input" placeholder="000 000" maxlength="6" style="text-align: center; letter-spacing: 4px; font-size: 18px;">
            </div>
        </div>
        <div class="dialog-actions">
            <button type="button" class="component-button btn-cancel">Cancelar</button>
            <button type="button" class="component-button primary btn-confirm">Verificar</button>
        </div>
    </div>

    <div id="template-loading">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div class="dialog-spinner"></div>
            <h1 class="dialog-title">Cargando...</h1>
        </div>
    </div>

</div>