<?php
// includes/sections/app/components/upload-editor.php
?>
<div class="video-editor-container <?php echo $isEditMode ? '' : 'd-none'; ?>" id="video-editor-area">
    
    <div class="component-message mb-4 <?php echo $isEditMode ? 'component-message--info' : 'd-none'; ?>" id="editor-status-alert">
        <?php if ($isEditMode): ?>
            <span class="spinner-sm"></span> Cargando datos del video...
        <?php endif; ?>
    </div>

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

                <hr class="component-divider">

                <div class="component-group-item" data-component="meta-section">
                    <div class="component-card__content">
                        <div class="component-card__text w-100">
                            <h2 class="component-card__title">Detalles y Reparto</h2>
                            
                            <div class="active w-100" data-state="view">
                                <div style="display: flex; flex-direction: column; gap: 12px;">
                                    <div>
                                        <span style="font-size: 12px; color: var(--text-tertiary); text-transform: uppercase; font-weight: 600;">Categorías</span>
                                        <div class="tags-view-container" id="display-categories">
                                            <span style="color: var(--text-secondary); font-size: 13px;">Ninguna</span>
                                        </div>
                                    </div>
                                    <div>
                                        <span style="font-size: 12px; color: var(--text-tertiary); text-transform: uppercase; font-weight: 600;">Elenco</span>
                                        <div class="tags-view-container" id="display-cast">
                                            <span style="color: var(--text-secondary); font-size: 13px;">Ninguno</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="disabled w-100" data-state="edit">
                                <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 16px;">
                                    
                                    <div class="meta-field-group">
                                        <label class="field-label">Categorías</label>
                                        <div class="tag-input-wrapper" id="category-wrapper">
                                            <div class="tags-collection" id="category-tags-collection"></div>
                                            <input type="text" class="tag-input-field" id="meta-category-input" placeholder="Añadir categoría (Ej: Acción, Vlog)..." autocomplete="off">
                                            <div class="suggestions-dropdown d-none" id="category-suggestions"></div>
                                        </div>
                                        <p class="field-help">Presiona Enter para añadir una categoría nueva.</p>
                                    </div>

                                    <div class="meta-field-group">
                                        <label class="field-label">Elenco / Reparto</label>
                                        <div class="tag-input-wrapper" id="actor-wrapper">
                                            <div class="tags-collection" id="actor-tags-collection"></div>
                                            <input type="text" class="tag-input-field" id="meta-actor-input" placeholder="Buscar actores o actrices..." autocomplete="off">
                                            <div class="suggestions-dropdown d-none" id="actor-suggestions"></div>
                                        </div>
                                        <p class="field-help">Escribe para buscar en la base de datos.</p>
                                    </div>

                                </div>

                                <div class="component-card__actions disabled m-0" data-state="actions-edit">
                                    <button type="button" class="component-button" data-action="cancel-edit" data-target="meta">Cancelar</button>
                                    <button type="button" class="component-button primary" data-action="save-field" data-target="meta">Guardar</button>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="component-card__actions actions-right active" data-state="actions-view">
                        <button type="button" class="component-button" data-action="start-edit" data-target="meta">Editar</button>
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