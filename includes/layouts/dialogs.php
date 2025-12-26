<div id="dialog-overlay" class="dialog-overlay">
    <div class="dialog-container">
        
        <div id="dialog-content-wrapper" class="dialog-card">
            <div class="dialog-spinner"></div>
        </div>

    </div>
</div>

<div id="dialog-templates" style="display: none;">

    <div id="template-alert">
        <div class="dialog-icon-area">
            <span class="material-symbols-rounded dialog-icon">info</span>
        </div>
        <div class="dialog-content">
            <h3 class="dialog-title"></h3>
            <p class="dialog-message"></p>
        </div>
        <div class="dialog-actions">
            <button type="button" class="component-button primary btn-accept">Aceptar</button>
        </div>
    </div>

    <div id="template-confirm">
        <div class="dialog-icon-area">
            <span class="material-symbols-rounded dialog-icon">help</span>
        </div>
        <div class="dialog-content">
            <h3 class="dialog-title"></h3>
            <p class="dialog-message"></p>
        </div>
        <div class="dialog-actions">
            <button type="button" class="component-button btn-cancel">Cancelar</button>
            <button type="button" class="component-button primary btn-confirm">Confirmar</button>
        </div>
    </div>

    <div id="template-danger">
        <div class="dialog-icon-area">
            <span class="material-symbols-rounded dialog-icon">warning</span>
        </div>
        <div class="dialog-content">
            <h3 class="dialog-title"></h3>
            <p class="dialog-message"></p>
        </div>
        <div class="dialog-actions">
            <button type="button" class="component-button btn-cancel">Cancelar</button>
            <button type="button" class="component-button btn-confirm" style="background-color: var(--color-error); color: white; border:none;">Eliminar</button>
        </div>
    </div>

    <div id="template-loading">
        <div style="padding: 20px 0;">
            <div class="dialog-spinner"></div>
            <p class="dialog-message" style="margin-top: 16px;">Cargando...</p>
        </div>
    </div>

</div>