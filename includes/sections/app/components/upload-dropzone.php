<?php
// Recibimos $isEditMode desde el padre
?>
<div class="component-studio-upload-zone <?php echo $isEditMode ? 'd-none' : ''; ?>" id="upload-dropzone">
    <input type="file" id="input-video-files" accept="video/mp4,video/x-m4v,video/*" multiple hidden>
    
    <div class="component-studio-upload-circle" id="btn-trigger-files">
        <span class="material-symbols-rounded component-studio-upload-icon">upload</span>
    </div>

    <h2 class="component-studio-upload-title">
        Arrastra y suelta archivos de video para subirlos
    </h2>
    <p class="component-studio-upload-desc">
        Tus videos serán privados hasta que los publiques.
    </p>

    <button class="component-button primary" id="btn-select-files" style="padding: 0 24px; font-weight: 600;">
        SELECCIONAR ARCHIVOS
    </button>

    <div class="component-studio-upload-legal">
        <p>Si envías tus videos a ProjectAurora, aceptas las <a href="#" class="component-studio-link">Condiciones del Servicio</a>.</p>
        <p>Asegúrate de no infringir derechos de autor o privacidad.</p>
    </div>
</div>