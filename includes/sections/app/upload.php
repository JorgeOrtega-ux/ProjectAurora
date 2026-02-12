<?php
// includes/sections/app/upload.php

if (!isset($_SESSION['user_id']) || !isset($_SESSION['uuid'])) {
    header("Location: " . $basePath . "login");
    exit;
}

// 1. Obtener el UUID del video de la URL (si existe)
$requestedVideoId = $routeParams['uuid'] ?? '';

// Fallback: Si el router no lo detectó, intentamos sacarlo de la URL actual
if (empty($requestedVideoId) && isset($_GET['section'])) {
    $parts = explode('/', $_GET['section']);
    // Si la URL es channel/upload/UUID, el UUID es la última parte
    if (count($parts) > 2 && $parts[1] === 'upload') {
        $requestedVideoId = end($parts);
    }
}

// NOTA: Se eliminó el bloque "if ($requestedUuid !== $_SESSION['uuid'])" porque
// comparaba incorrectamente el ID del video con el ID del usuario.
?>

<div class="component-studio-layout" data-section="channel-upload">
    
    <input type="hidden" id="initial-video-id" value="<?php echo htmlspecialchars($requestedVideoId); ?>">

    <div class="component-studio-toolbar">
        <div class="component-studio-toolbar-group">
            <span class="component-toolbar-title">Subir videos</span>
            
            <div id="video-tabs-container" class="studio-tabs-scroll">
                </div>

            <button class="component-button square d-none" 
                    id="btn-add-more" 
                    title="Añadir otro video" 
                    style="margin-left: 8px; border-radius: 50%; width: 32px; height: 32px; min-width: 32px;">
                <span class="material-symbols-rounded" style="font-size: 18px;">add</span>
            </button>
        </div>

        <div class="component-studio-toolbar-group">
            <div id="global-upload-status" style="font-size: 13px; color: var(--text-secondary); margin-right: 12px; display: none;">
                <span class="spinner-sm" style="width: 16px; height: 16px; border-width: 2px; vertical-align: middle; margin-right: 8px;"></span>
                <span id="global-status-text">Subiendo...</span>
            </div>

            <button class="component-button component-button--danger-ghost" id="btn-delete-video" title="Eliminar borrador">
                <span class="material-symbols-rounded">delete</span>
            </button>
            
            <button class="component-button" id="btn-save-draft">
                Guardar Borrador
            </button>
            
            <button class="component-button primary" id="btn-publish" disabled>
                PUBLICAR
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
            
            <div class="component-message mb-4 d-none" id="editor-status-alert"></div>

            <div class="editor-layout">
                
                <div class="editor-form">
                    
                    <div class="component-card component-card--grouped">
                        
                        <div class="component-group-item" data-component="title-section">
                            <div class="component-card__content">
                                <div class="component-card__text">
                                    <h2 class="component-card__title">Título (obligatorio)</h2>
                                    
                                    <div class="active" data-state="view">
                                        <span class="text-display-value" id="display-title" style="font-weight: 500;">...</span>
                                    </div>
                                    
                                    <div class="disabled w-100 input-group-responsive" data-state="edit">
                                        <div class="component-input-wrapper flex-1">
                                            <input type="text" class="component-text-input" id="meta-title" placeholder="Añade un título que describa tu video">
                                        </div>
                                        <div class="component-card__actions disabled m-0" data-state="actions-edit">
                                            <button type="button" class="component-button" data-action="cancel-edit" data-target="title">Cancelar</button>
                                            <button type="button" class="component-button primary" data-action="save-field" data-target="title">Guardar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="component-card__actions actions-right active" data-state="actions-view">
                                <button type="button" class="component-button" data-action="start-edit" data-target="title">Editar</button>
                            </div>
                        </div>

                        <hr class="component-divider">

                        <div class="component-group-item" data-component="desc-section">
                            <div class="component-card__content">
                                <div class="component-card__text">
                                    <h2 class="component-card__title">Descripción</h2>
                                    
                                    <div class="active" data-state="view">
                                        <span class="text-display-value" id="display-desc" style="color: var(--text-secondary); display: block; max-height: 100px; overflow: hidden; text-overflow: ellipsis;">Sin descripción</span>
                                    </div>
                                    
                                    <div class="disabled w-100 input-group-responsive" data-state="edit">
                                        <div class="component-input-wrapper flex-1">
                                            <textarea class="component-text-input" id="meta-desc" style="height: 120px; resize: none; line-height: 1.5; padding-top: 10px;" placeholder="Cuéntales a los espectadores sobre tu video"></textarea>
                                        </div>
                                        <div class="component-card__actions disabled m-0" data-state="actions-edit">
                                            <button type="button" class="component-button" data-action="cancel-edit" data-target="desc">Cancelar</button>
                                            <button type="button" class="component-button primary" data-action="save-field" data-target="desc">Guardar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="component-card__actions actions-right active" data-state="actions-view">
                                <button type="button" class="component-button" data-action="start-edit" data-target="desc">Editar</button>
                            </div>
                        </div>

                    </div>

                </div>

                <div class="editor-preview-column">
                    <div class="video-preview-card">
                        
                        <div class="preview-player-placeholder" id="preview-player-container">
                            <span class="material-symbols-rounded" style="font-size: 48px; color: var(--text-tertiary);">play_circle</span>
                            <p style="margin-top: 8px; font-size: 12px; color: var(--text-secondary);">Vista previa</p>
                            
                            <img src="" id="thumbnail-preview" class="preview-thumbnail-img d-none" alt="Miniatura del video">
                            
                            <div class="thumbnail-loading d-none">
                                <div class="spinner-sm"></div>
                            </div>
                        </div>

                        <div class="preview-meta">
                            <div class="preview-link-row">
                                <span class="material-symbols-rounded">link</span>
                                <span class="video-link-text">Enlace del video</span>
                            </div>
                            <span class="video-filename" id="meta-filename">...</span>
                        </div>

                        <div style="padding: 0 16px 16px 16px; display: flex; gap: 8px;">
                            <input type="file" id="input-thumbnail" accept="image/png, image/jpeg, image/webp" hidden>
                            
                            <button class="component-button w-100" id="btn-trigger-thumb-upload">
                                <span class="material-symbols-rounded">add_photo_alternate</span>
                                Subir miniatura
                            </button>
                            
                            <button class="component-button square" id="btn-gen-thumbs" title="Generar automáticas">
                                <span class="material-symbols-rounded">autorenew</span>
                            </button>
                        </div>
                    </div>

                    <div id="generated-thumbs-grid" class="generated-thumbs-container d-none"></div>
                    
                    <input type="hidden" id="active-video-uuid">
                </div>

            </div>

        </div>

    </div>
</div>