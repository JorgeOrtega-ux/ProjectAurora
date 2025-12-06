<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

// Variables inyectadas desde router.php
$initUuid = $activeContextUuid ?? '';
$initType = $activeContextType ?? 'community';
$initChannel = $activeChannelUuid ?? '';

$isCommunityView = (!empty($initUuid) && $initType === 'community');
$hideOnCommunity = $isCommunityView ? 'style="display: none !important;"' : '';

// [SOLUCIÓN INSTANTÁNEA]
// Verificamos en el servidor si el usuario tiene historial para decidir qué placeholder mostrar
$userId = $_SESSION['user_id'];
$hasHistory = false;

// 1. Verificar si está en alguna comunidad
$stmtComm = $pdo->prepare("SELECT id FROM community_members WHERE user_id = ? LIMIT 1");
$stmtComm->execute([$userId]);
if ($stmtComm->fetchColumn()) {
    $hasHistory = true;
} else {
    // 2. Si no, verificar si tiene mensajes privados
    $stmtFriend = $pdo->prepare("SELECT id FROM friendships WHERE sender_id = ? OR receiver_id = ? LIMIT 1");
    $stmtFriend->execute([$userId, $userId]);
    if ($stmtFriend->fetchColumn()) {
        $hasHistory = true;
    }
}

// Lógica de visibilidad inicial basada en PHP
$showWelcome = empty($initUuid) && !$hasHistory;
$showSelect  = empty($initUuid) && $hasHistory;
?>
<script>
    window.ACTIVE_CHAT_UUID = '<?php echo htmlspecialchars($initUuid); ?>';
    window.ACTIVE_CHAT_TYPE = '<?php echo htmlspecialchars($initType); ?>';
    window.ACTIVE_CHANNEL_UUID = '<?php echo htmlspecialchars($initChannel); ?>';
</script>

<div class="section-content active" data-section="main" style="padding: 0; height: 100%;">

    <div class="chat-layout-container">

        <div class="chat-rail d-none" id="chat-rail-panel">
            <div class="rail-group-top">
                <div class="rail-item" data-action="toggle-rail-filter" id="rail-filter-trigger">
                    <div class="rail-avatar filter-avatar">
                        <span class="material-symbols-rounded">filter_list</span>
                    </div>
                </div>
            </div>
            <div class="rail-divider"></div>
            <div class="rail-group-scroll" id="rail-communities-list"></div>
        </div>

        <div class="popover-module rail-filter-popover disabled" id="rail-filter-menu">
            <div class="menu-content">
                <div class="menu-list">
                    <div class="menu-link active" data-action="rail-filter-apply" data-filter="all">
                        <span class="material-symbols-rounded">forum</span>
                        <div class="menu-link-text body-title">Todos</div>
                    </div>
                    <div class="menu-link" data-action="rail-filter-apply" data-filter="unread">
                        <span class="material-symbols-rounded">mark_chat_unread</span>
                        <div class="menu-link-text body-title">No leídos</div>
                    </div>
                    <div class="menu-link" data-action="rail-filter-apply" data-filter="community">
                        <span class="material-symbols-rounded">groups</span>
                        <div class="menu-link-text body-title">Comunidades</div>
                    </div>
                    <div class="menu-link" data-action="rail-filter-apply" data-filter="private">
                        <span class="material-symbols-rounded">person</span>
                        <div class="menu-link-text body-title">DM</div>
                    </div>
                    <div class="menu-link" data-action="rail-filter-apply" data-filter="favorites">
                        <span class="material-symbols-rounded">star</span>
                        <div class="menu-link-text body-title">Favoritos</div>
                    </div>
                    <div class="menu-link" data-action="rail-filter-apply" data-filter="archived">
                        <span class="material-symbols-rounded">archive</span>
                        <div class="menu-link-text body-title">Archivados</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="chat-sidebar" id="chat-sidebar-panel">
            <div class="chat-sidebar-header">
                <h2 class="chat-sidebar-title">
                    <?php if ($isCommunityView): ?>
                        <div style="display:flex; align-items:center; gap:12px; width:100%;">
                            <button class="component-icon-button" id="btn-sidebar-back" style="width:32px; height:32px; border:none;">
                                <span class="material-symbols-rounded">arrow_back</span>
                            </button>
                            <div style="display:flex; align-items:center; gap:8px; overflow:hidden;">
                                <div class="small-spinner" style="width:20px; height:20px; border-width:2px;"></div>
                            </div>
                        </div>
                    <?php else: ?>
                        Chats
                    <?php endif; ?>
                </h2>
                <div class="chat-sidebar-actions" <?php echo $hideOnCommunity; ?>>
                    <button class="component-icon-button" data-nav="explorer" title="Explorar comunidades">
                        <span class="material-symbols-rounded">explore</span>
                    </button>
                    <button class="component-icon-button" data-nav="search" title="Buscar personas">
                        <span class="material-symbols-rounded">person_search</span>
                    </button>
                </div>
            </div>

            <div class="chat-sidebar-search" <?php echo $hideOnCommunity; ?>>
                <div class="sidebar-search-wrapper">
                    <span class="material-symbols-rounded search-icon">search</span>
                    <input type="text" id="sidebar-search-input" placeholder="Buscar..." class="sidebar-search-input">
                </div>
            </div>

            <div class="chat-sidebar-badges" <?php echo $hideOnCommunity; ?>>
                <div class="sidebar-badge active" data-filter="all">Todos</div>
                <div class="sidebar-badge" data-filter="unread">No leídos</div>
                <div class="sidebar-badge" data-filter="community">Grupos</div>
                <div class="sidebar-badge" data-filter="private">DM</div>
                <div class="sidebar-badge" data-filter="favorites">Favoritos</div>
                <div class="sidebar-badge" data-filter="archived">Archivados</div>
            </div>

            <div class="chat-list-wrapper">
                <div id="my-communities-list" class="chat-list">
                    <div class="small-spinner" style="margin: 20px auto;"></div>
                </div>
            </div>
        </div>

        <div class="chat-main-area" id="chat-main-panel">

            <div id="chat-placeholder-welcome" class="chat-placeholder <?php echo $showWelcome ? '' : 'd-none'; ?>">
                <div class="placeholder-content">
                    <span class="material-symbols-rounded placeholder-icon" style="color: #1976d2;">public</span>
                    <h3>¡Bienvenido a Project Aurora!</h3>
                    <p style="max-width: 300px; margin: 0 auto; line-height: 1.5;">
                        Explora comunidades públicas, busca nuevos amigos o revisa tus chats archivados para comenzar.
                    </p>
                    <div style="display: flex; gap: 12px; justify-content: center; margin-top: 24px;">
                        <button class="component-button primary" data-nav="explorer">
                            <span class="material-symbols-rounded">explore</span> Explorar
                        </button>
                        <button class="component-button" data-nav="search">
                            <span class="material-symbols-rounded">person_search</span> Buscar
                        </button>
                    </div>
                </div>
            </div>

            <div id="chat-placeholder-select" class="chat-placeholder <?php echo $showSelect ? '' : 'd-none'; ?>">
                <div class="placeholder-content">
                    <span class="material-symbols-rounded placeholder-icon" style="color: #999;">chat_bubble_outline</span>
                    <h3>Selecciona un chat</h3>
                    <p style="max-width: 300px; margin: 0 auto; line-height: 1.5; color: #888;">
                        Elige una conversación del menú lateral para comenzar a enviar mensajes.
                    </p>
                </div>
            </div>

            <div id="chat-interface" class="chat-interface <?php echo empty($initUuid) ? 'd-none' : ''; ?>">
                <div class="chat-header">
                    <div class="chat-header-left">
                        <button class="component-icon-button mobile-only-btn <?php echo $isCommunityView ? '' : 'd-none'; ?>" id="btn-mobile-sidebar-toggle" style="margin-right: 8px;">
                            <span class="material-symbols-rounded">menu</span>
                        </button>

                        <div class="chat-avatar-container">
                            <img id="chat-header-img" src="" alt="" class="chat-avatar-img">
                        </div>
                        <div class="chat-info" id="chat-header-info-clickable" style="cursor: pointer;">
                            <h3 id="chat-header-title" class="chat-title">Cargando...</h3>
                            <span id="chat-header-status" class="chat-status">...</span>
                        </div>
                    </div>
                    <div class="chat-header-right">
                        <button class="component-icon-button" id="btn-group-info-toggle" data-action="toggle-group-info" title="Información">
                            <span class="material-symbols-rounded">info</span>
                        </button>
                    </div>
                </div>

                <div id="mobile-channels-panel" class="mobile-channels-sidebar d-none">
                    <div class="mobile-channels-header">
                        <h3 style="margin:0; font-size:14px; color:#666; font-weight:700;">CANALES</h3>
                    </div>
                    <div id="mobile-channels-list" class="mobile-channels-list"></div>
                </div>

                <div class="chat-messages-area overflow-y"></div>

                <div class="chat-input-area" style="background: transparent; border: none; padding: 0 16px 16px 16px;">
                    <div class="chat-pill-measure" id="measure" style="visibility: hidden; position: absolute; white-space: nowrap; font-size: 1rem; font-family: inherit;"></div>

                    <input type="file" id="chat-file-input" multiple accept="image/*" style="display: none;">

                    <div class="chat-pill-container" style="width: 100%; max-width: 100%;">
                        <div class="chat-pill-box" id="pill">

                            <div id="reply-preview-container" class="reply-preview-bar d-none">

                                <div class="reply-bar-icon">
                                    <span class="material-symbols-rounded">reply</span>
                                </div>

                                <div class="reply-bar-content">
                                    <span class="reply-bar-title">Respondiendo a <strong id="reply-target-user">...</strong></span>
                                    <span class="reply-bar-text" id="reply-target-text">...</span>
                                </div>

                                <button class="component-icon-button small" id="btn-cancel-reply">
                                    <span class="material-symbols-rounded">close</span>
                                </button>
                            </div>

                            <div id="attachment-preview-area" class="d-none">
                                <div class="preview-grid" id="preview-grid"></div>
                            </div>

                            <div class="chat-pill-controls">
                                <button class="chat-pill-btn" id="btn-attach-file" title="Adjuntar imágenes (Máx 4)">
                                    <svg viewBox="0 0 24 24">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                </button>

                                <input type="text" class="chat-pill-input chat-message-input" id="chat-message-input" placeholder="Escribe un mensaje..." autocomplete="off">

                                <button class="chat-pill-btn chat-pill-send" id="btn-send-message" disabled>
                                    <svg viewBox="0 0 24 24">
                                        <line x1="12" y1="19" x2="12" y2="5"></line>
                                        <polyline points="5 12 12 5 19 12"></polyline>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
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
                    <div id="info-files-grid" class="info-files-grid"></div>
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