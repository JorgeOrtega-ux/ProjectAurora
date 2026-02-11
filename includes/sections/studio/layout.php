<?php
// includes/sections/studio/layout.php

// Variables esperadas: $studioView, $targetUuid (provienen del loader/router)

$myUuid = $_SESSION['uuid'] ?? '';
$isOwner = ($myUuid === $targetUuid);

// URLs visuales (barra de direcciones)
$urlDashboard = "s/channel/panel-control/" . $targetUuid;
$urlContent   = "s/channel/manage-content/" . $targetUuid;
$urlUpload    = "s/channel/upload/" . $targetUuid;

?>

<div class="component-wrapper component-wrapper--full component-wrapper--flex component-studio-wrapper">

    <?php if (!$isOwner): ?>
        <div class="component-layout-centered" style="width: 100%; text-align: center;">
            <div class="component-card component-card--compact">
                <div style="margin-bottom: 16px; color: var(--color-error);">
                    <span class="material-symbols-rounded" style="font-size: 48px;">no_accounts</span>
                </div>
                <h1 class="component-page-title"><?php echo $i18n->t('studio.error_owner_title'); ?></h1>
                <p class="component-page-description"><?php echo $i18n->t('studio.error_owner_desc'); ?></p>
                <div class="mt-24">
                    <div class="component-button primary w-100" data-nav="main" style="justify-content: center;">
                        <?php echo $i18n->t('menu.back_home'); ?>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        
        <div class="component-studio-sidebar">
            
            <div class="menu-content">
                <div class="menu-content-top">
                    <div class="menu-list">
                        
                        <div class="menu-link <?php echo ($studioView === 'panel-control') ? 'active' : ''; ?>" 
                             data-nav="<?php echo $urlDashboard; ?>"
                             data-fetch="studio/parts/dashboard"
                             data-target="#studio-content-area">
                            <div class="menu-link-icon">
                                <span class="material-symbols-rounded">dashboard</span>
                            </div>
                            <div class="menu-link-text"><?php echo $i18n->t('studio.menu_dashboard'); ?></div>
                        </div>

                        <div class="menu-link <?php echo ($studioView === 'manage-content') ? 'active' : ''; ?>" 
                             data-nav="<?php echo $urlContent; ?>"
                             data-fetch="studio/parts/content"
                             data-target="#studio-content-area">
                            <div class="menu-link-icon">
                                <span class="material-symbols-rounded">video_library</span>
                            </div>
                            <div class="menu-link-text"><?php echo $i18n->t('studio.menu_content'); ?></div>
                        </div>

                        <div class="menu-link <?php echo ($studioView === 'upload') ? 'active' : ''; ?>" 
                             data-nav="<?php echo $urlUpload; ?>"
                             data-target="#studio-content-area">
                            <div class="menu-link-icon">
                                <span class="material-symbols-rounded">upload</span>
                            </div>
                            <div class="menu-link-text">Subir video</div>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <div class="component-studio-content" id="studio-content-area">
            <?php 
                // Carga inicial (Server Side Rendering) para F5 o acceso directo
                if ($studioView === 'panel-control') {
                    include __DIR__ . '/dashboard.php';
                } elseif ($studioView === 'manage-content') {
                    include __DIR__ . '/content.php';
                } elseif ($studioView === 'upload') {
                    include __DIR__ . '/upload.php';
                }
            ?>
        </div>

    <?php endif; ?>

</div>