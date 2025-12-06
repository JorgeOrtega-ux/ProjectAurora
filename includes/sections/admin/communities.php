<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user_role'] ?? 'user';

if (!in_array($role, ['founder', 'administrator'])) {
    include __DIR__ . '/../system/404.php'; 
    exit;
}

$basePath = isset($GLOBALS['basePath']) ? $GLOBALS['basePath'] : '/ProjectAurora/';
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">

<div class="section-content active" data-section="admin/communities">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin" 
                     data-i18n-tooltip="global.back" 
                     data-tooltip="<?php echo translation('global.back'); ?>">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span class="toolbar-title-actions">Gestión de Comunidades</span>
            </div>
            <div class="component-toolbar__right">
                <button class="component-icon-button" id="btn-view-requests" 
                        data-tooltip="Solicitudes de Unión"
                        style="margin-right: 8px; position: relative;">
                    <span class="material-symbols-rounded">person_add</span>
                    <span id="requests-badge" style="display:none; position:absolute; top:5px; right:5px; width:8px; height:8px; background:red; border-radius:50%;"></span>
                </button>

                <button class="component-icon-button" data-nav="admin/community-edit" 
                        data-i18n-tooltip="admin.communities.create" 
                        data-tooltip="Nueva Comunidad">
                    <span class="material-symbols-rounded">add_circle</span>
                </button>
            </div>
        </div>
        
        <div class="component-toolbar search-toolbar-panel" style="margin-top: 8px;">
            <div class="search-container full-width-search">
                <span class="material-symbols-rounded search-icon">search</span>
                <input type="text" id="admin-communities-search" class="search-input" placeholder="Buscar por nombre o código..." autocomplete="off"> 
            </div>
        </div>
    </div>

    <div class="component-wrapper section-with-toolbar" style="padding-top: 140px !important;">

        <div class="component-header-card">
            <h1 class="component-page-title">Comunidades</h1>
            <p class="component-page-description">Administra los grupos, su privacidad y accesos.</p>
        </div>

        <div id="communities-list-container" class="capsule-list-container mt-16">
            <div class="small-spinner" style="margin: 20px auto;"></div>
        </div>

    </div>

    <div id="requests-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div class="modal-content" style="background: var(--bg-card); width: 90%; max-width: 800px; border-radius: 16px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); max-height: 85vh; display: flex; flex-direction: column;">
            
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; font-size: 1.25rem;">Solicitudes de Ingreso</h2>
                <button id="close-requests-modal" style="background: none; border: none; cursor: pointer; color: var(--text-secondary);">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>
            
            <div class="modal-body" style="flex: 1; overflow-y: auto;">
                <div id="requests-list-container">
                    <div class="small-spinner" style="margin: 20px auto;"></div>
                </div>
            </div>

        </div>
    </div>

</div>