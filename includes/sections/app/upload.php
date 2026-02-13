<?php
// includes/sections/app/upload.php

if (!isset($_SESSION['user_id']) || !isset($_SESSION['uuid'])) {
    header("Location: " . $basePath . "login");
    exit;
}

// --- LÓGICA PHP ---
$requestedVideoId = $routeParams['uuid'] ?? '';

// Fallback logic
if (empty($requestedVideoId) && isset($_GET['section'])) {
    $parts = explode('/', $_GET['section']);
    if (count($parts) > 2 && $parts[1] === 'upload') {
        $requestedVideoId = end($parts);
    }
}

if ($requestedVideoId === $_SESSION['uuid']) {
    $requestedVideoId = '';
}

$isEditMode = !empty($requestedVideoId);
// ------------------
?>

<div class="component-studio-layout" data-section="channel-upload">
    
    <input type="hidden" id="initial-video-id" value="<?php echo htmlspecialchars($requestedVideoId); ?>">

    <div class="component-studio-toolbar">
        <div class="component-studio-toolbar-group">
            <span class="component-toolbar-title">Subir videos</span>
            <div id="video-tabs-container" class="studio-tabs-scroll"></div>
            
            <button class="component-button square d-none" id="btn-add-more" title="Añadir otro video" style="margin-left: 8px; border-radius: 50%; width: 32px; height: 32px; min-width: 32px;">
                <span class="material-symbols-rounded" style="font-size: 18px;">add</span>
            </button>
        </div>

        <div class="component-studio-toolbar-group <?php echo $isEditMode ? '' : 'd-none'; ?>" id="action-buttons-group">
            <div id="global-upload-status" style="font-size: 13px; color: var(--text-secondary); margin-right: 12px; display: none;">
                <span class="spinner-sm" style="width: 16px; height: 16px; border-width: 2px; vertical-align: middle; margin-right: 8px;"></span>
                <span id="global-status-text">Subiendo...</span>
            </div>

            <button class="component-button component-button--danger-ghost" id="btn-delete-video" title="Eliminar borrador">
                <span class="material-symbols-rounded">delete</span>
            </button>
            <button class="component-button" id="btn-save-draft">Guardar Borrador</button>
            <button class="component-button primary" id="btn-publish" disabled>PUBLICAR</button>
        </div>
    </div>

    <div class="component-studio-content-area centered" id="studio-main-area">
        
        <?php 
            // INCLUIMOS LOS COMPONENTES
            // Ambos se cargan, pero su visibilidad (d-none) se controla dentro de ellos o vía JS
            
            require __DIR__ . '/components/upload-dropzone.php'; 
            
            require __DIR__ . '/components/upload-editor.php'; 
        ?>

    </div>
</div>