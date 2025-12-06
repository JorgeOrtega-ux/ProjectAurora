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

<div class="section-content active" data-section="admin/community-requests">
    
    <div class="toolbar-stack">
        <div class="component-toolbar">
            <div class="component-toolbar__group">
                <div class="component-icon-button" data-nav="admin/communities" 
                     data-i18n-tooltip="global.back" 
                     data-tooltip="Volver a Comunidades">
                    <span class="material-symbols-rounded">arrow_back</span>
                </div>
                <div class="component-toolbar__separator"></div>
                <span class="toolbar-title-actions">Solicitudes de Unión</span>
            </div>
            
            <div class="component-toolbar__right">
                <button class="component-icon-button" onclick="window.location.reload()" data-tooltip="Recargar">
                    <span class="material-symbols-rounded">refresh</span>
                </button>
            </div>
        </div>
    </div>

    <div class="component-wrapper section-with-toolbar" style="padding-top: 140px !important;">

        <div class="component-header-card">
            <h1 class="component-page-title">Solicitudes Pendientes</h1>
            <p class="component-page-description">Gestiona las peticiones de usuarios para unirse a comunidades privadas.</p>
        </div>

        <div id="requests-list-container" class="capsule-list-container mt-16">
            <div class="small-spinner" style="margin: 20px auto;"></div>
        </div>

    </div>
</div>