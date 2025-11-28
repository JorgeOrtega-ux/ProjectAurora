<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

// $activeCommunityUuid viene del router.php si la URL es /c/{uuid}
$initUuid = $activeCommunityUuid ?? '';
?>
<script>
    // Variable global para que el módulo JS sepa qué comunidad abrir al inicio
    window.ACTIVE_COMMUNITY_UUID = '<?php echo htmlspecialchars($initUuid); ?>';
</script>

<div class="section-content active" data-section="main" style="padding: 0; height: 100%;">
    
    <div class="chat-layout-container">
        
        <div class="chat-sidebar" id="chat-sidebar-panel">
            <div class="chat-sidebar-header">
                <h2 class="chat-sidebar-title">Chats</h2>
                <div class="chat-sidebar-actions">
                    <button class="component-icon-button" data-nav="explorer" title="Explorar">
                        <span class="material-symbols-rounded">explore</span>
                    </button>
                    <button class="component-icon-button" data-nav="join-community" title="Unirse con código">
                        <span class="material-symbols-rounded">add</span>
                    </button>
                </div>
            </div>
            
            <div class="chat-list-wrapper">
                <div id="my-communities-list" class="chat-list">
                    <div class="small-spinner" style="margin: 20px auto;"></div>
                </div>
            </div>
        </div>

        <div class="chat-main-area" id="chat-main-panel">
            
            <div id="chat-placeholder" class="chat-placeholder <?php echo empty($initUuid) ? '' : 'd-none'; ?>">
                <div class="placeholder-content">
                    <span class="material-symbols-rounded placeholder-icon">forum</span>
                    <h3>Project Aurora</h3>
                    <p>Selecciona una comunidad para comenzar a chatear.</p>
                </div>
            </div>

            <div id="chat-interface" class="chat-interface <?php echo empty($initUuid) ? 'd-none' : ''; ?>">
                
                <div class="chat-header">
                    <div class="chat-header-left">
                        <button class="component-icon-button mobile-back-btn" id="btn-back-to-list">
                            <span class="material-symbols-rounded">arrow_back</span>
                        </button>
                        
                        <div class="chat-avatar-container">
                            <img id="chat-header-img" src="" alt="" class="chat-avatar-img">
                        </div>
                        
                        <div class="chat-info" data-action="toggle-group-info" style="cursor: pointer;">
                            <h3 id="chat-header-title" class="chat-title">Cargando...</h3>
                            <span id="chat-header-status" class="chat-status">haz clic para ver info</span>
                        </div>
                    </div>
                    
                    <div class="chat-header-right">
                        <button class="component-icon-button" data-action="toggle-group-info" title="Información del grupo">
                            <span class="material-symbols-rounded">info</span>
                        </button>
                    </div>
                </div>

                <div class="chat-messages-area">
                </div>

                <div id="attachment-preview-area" class="d-none">
                    <div class="preview-grid" id="preview-grid">
                        </div>
                </div>

                <div id="reply-preview-container" class="reply-preview-bar d-none">
                    <div class="reply-bar-content">
                        <span class="reply-bar-title">Respondiendo a <strong id="reply-target-user">...</strong></span>
                        <span class="reply-bar-text" id="reply-target-text">...</span>
                    </div>
                    <button class="component-icon-button small" id="btn-cancel-reply">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>

                <div class="chat-input-area">
                    <input type="file" id="chat-file-input" multiple accept="image/*" style="display: none;">
                    
                    <button class="component-icon-button" id="btn-attach-file" title="Adjuntar imágenes (Máx 4)">
                        <span class="material-symbols-rounded">attach_file</span>
                    </button>

                    <input type="text" class="chat-message-input" placeholder="Escribe un mensaje...">
                    <button class="component-icon-button" id="btn-send-message">
                        <span class="material-symbols-rounded">send</span>
                    </button>
                </div>

            </div>

        </div>

        <div class="chat-info-sidebar d-none" id="chat-info-panel">
            <div class="info-sidebar-header">
                <span class="info-sidebar-title">Info. del grupo</span>
                <button class="component-icon-button" data-action="close-group-info">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            
            <div class="info-sidebar-content">
                <div class="info-group-profile">
                    <img id="info-group-img" src="" alt="Grupo" class="info-group-avatar">
                    <h2 id="info-group-name" class="info-group-name">...</h2>
                    <p id="info-group-desc" class="info-group-desc">...</p>
                </div>

                <hr class="component-divider">

                <div class="info-section">
                    <h3 class="info-section-title">Miembros <span id="info-member-count" style="font-weight:400; color:#666;">(0)</span></h3>
                    <div id="info-members-list" class="info-members-list">
                        <div class="small-spinner"></div>
                    </div>
                </div>

                <hr class="component-divider">

                <div class="info-section">
                    <h3 class="info-section-title">Archivos recientes</h3>
                    <div id="info-files-grid" class="info-files-grid">
                        </div>
                </div>
            </div>
        </div>

    </div>

    <div id="media-viewer-overlay" class="media-viewer d-none">
        <div class="media-viewer-header">
            <div class="media-viewer-user">
                <img id="viewer-user-avatar" src="" alt="">
                <div class="media-viewer-meta">
                    <span id="viewer-user-name">Usuario</span>
                    <span id="viewer-date" style="font-size:12px; color:#ccc;">Fecha</span>
                </div>
            </div>
            
            <div class="media-viewer-controls">
                <span id="viewer-counter" class="viewer-counter">1 / 1</span>
                <button class="component-icon-button viewer-btn" data-action="close-viewer">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
        </div>

        <div class="media-viewer-content">
            <button class="viewer-nav-btn prev" data-action="viewer-prev">
                <span class="material-symbols-rounded">chevron_left</span>
            </button>
            
            <img id="viewer-main-image" src="" alt="Vista previa">
            
            <button class="viewer-nav-btn next" data-action="viewer-next">
                <span class="material-symbols-rounded">chevron_right</span>
            </button>
        </div>
    </div>

</div>