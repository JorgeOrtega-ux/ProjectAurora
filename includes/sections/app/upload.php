<?php
// includes/sections/app/upload.php

if (!isset($_SESSION['user_id']) || !isset($_SESSION['uuid'])) {
    header("Location: " . $basePath . "login");
    exit;
}

$requestedUuid = $routeParams['uuid'] ?? '';

if ($requestedUuid !== $_SESSION['uuid']) {
    ?>
    <div class="component-studio-state-screen">
        <div class="component-studio-state-content">
            <span class="material-symbols-rounded component-studio-state-icon danger">lock</span>
            <h2 class="component-studio-state-title">Acceso Denegado</h2>
            <p class="component-studio-state-text">No tienes permisos para gestionar este canal.</p>
            <div style="margin-top: 24px;">
                <button class="component-button primary" onclick="window.history.back()">Volver</button>
            </div>
        </div>
    </div>
    <?php
    return;
}
?>

<div class="component-studio-layout" data-section="channel-upload">
    
    <div class="component-studio-toolbar">
        <div class="component-studio-toolbar-group">
            <span class="component-toolbar-title">Subir videos</span>
            
            <div id="video-tabs-container" class="studio-tabs-scroll">
                </div>
        </div>

        <div class="component-studio-toolbar-group">
            <div id="global-upload-status" style="font-size: 13px; color: var(--text-secondary); margin-right: 12px; display: none;">
                <span class="spinner-sm" style="width: 16px; height: 16px; border-width: 2px; vertical-align: middle; margin-right: 8px;"></span>
                <span id="global-status-text">Subiendo...</span>
            </div>
            <button class="component-button" onclick="window.history.back()">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
    </div>

    <div class="component-studio-content-area centered" id="studio-main-area">
        
        <div class="component-studio-upload-zone" id="upload-dropzone">
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

        <div class="video-editor-container d-none" id="video-editor-area">
            
            <div class="editor-layout">
                <div class="editor-form">
                    
                    <div class="component-message mb-0 d-none" id="editor-status-alert">
                        </div>

                    <div class="component-form-group">
                        <label class="component-label">Título (obligatorio)</label>
                        <div class="component-input-wrapper">
                            <input type="text" class="component-text-input" id="meta-title" placeholder="Añade un título que describa tu video">
                        </div>
                    </div>

                    <div class="component-form-group">
                        <label class="component-label">Descripción</label>
                        <div class="component-input-wrapper">
                            <textarea class="component-text-input" id="meta-desc" style="height: 120px; resize: none; line-height: 1.5;" placeholder="Cuéntales a los espectadores sobre tu video"></textarea>
                        </div>
                    </div>

                    <div class="component-form-group">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <label class="component-label">Miniatura</label>
                            <button type="button" class="component-button small" id="btn-gen-thumbs" style="font-size:12px; height:28px;">
                                <span class="material-symbols-rounded" style="font-size:16px;">autorenew</span> Generar Automáticas
                            </button>
                        </div>
                        
                        <p class="component-card__description mb-2">Selecciona una imagen o genera miniaturas automáticas.</p>
                        
                        <div class="thumbnail-uploader-wrapper">
                            <div class="thumbnail-box" id="thumbnail-dropzone">
                                <img src="" id="thumbnail-preview" class="thumbnail-img d-none">
                                <div class="thumbnail-placeholder">
                                    <span class="material-symbols-rounded">add_photo_alternate</span>
                                    <span style="font-size: 12px; margin-top: 4px;">Subir miniatura</span>
                                </div>
                                <div class="thumbnail-overlay">
                                    <span class="material-symbols-rounded">edit</span>
                                </div>
                                <div class="thumbnail-loading d-none">
                                    <div class="spinner-sm"></div>
                                </div>
                            </div>
                            <input type="file" id="input-thumbnail" accept="image/png, image/jpeg, image/webp" hidden>
                            
                            <div class="thumbnail-info">
                                <p>Recomendado: 1280x720 px.</p>
                                <p>Formatos: JPG, PNG, WEBP. Máx 2MB.</p>
                            </div>
                        </div>

                        <div id="generated-thumbs-grid" class="generated-thumbs-container d-none">
                            </div>
                    </div>
                </div>

                <div class="editor-preview-column">
                    <div class="video-preview-card">
                        <div class="preview-player-placeholder">
                            <span class="material-symbols-rounded" style="font-size: 48px; color: var(--text-tertiary);">play_circle</span>
                            <p style="margin-top: 8px; font-size: 12px; color: var(--text-secondary);">Vista previa</p>
                        </div>
                        <div class="preview-meta">
                            <div class="preview-link-row">
                                <span class="material-symbols-rounded">link</span>
                                <span class="video-link-text">Enlace del video</span>
                            </div>
                            <span class="video-filename" id="meta-filename">...</span>
                        </div>
                    </div>

                    <div class="editor-actions">
                        <input type="hidden" id="active-video-uuid">
                        
                        <button class="component-button w-100" id="btn-save-draft">
                            Guardar como borrador
                        </button>
                        
                        <button class="component-button primary w-100" id="btn-publish" disabled>
                            PUBLICAR
                        </button>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>