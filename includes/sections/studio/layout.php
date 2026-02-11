<?php
// includes/sections/studio/layout.php

// Variables esperadas: $studioView, $targetUuid (provienen del loader/router)

$myUuid = $_SESSION['uuid'] ?? '';
$isOwner = ($myUuid === $targetUuid);

// URLs visuales (barra de direcciones)
$urlDashboard = "s/channel/panel-control/" . $targetUuid;
$urlContent   = "s/channel/manage-content/" . $targetUuid;

?>

<div class="component-wrapper component-wrapper--full component-wrapper--flex" style="padding: 0; gap: 0; flex-direction: row; height: 100%; overflow: hidden;">

    <?php if (!$isOwner): ?>
        <div class="component-layout-centered" style="width: 100%; text-align: center;">
            <div class="component-card component-card--compact">
                <div style="margin-bottom: 16px; color: var(--color-error);">
                    <span class="material-symbols-rounded" style="font-size: 48px;">no_accounts</span>
                </div>
                <h1 class="component-page-title"><?php echo $i18n->t('studio.error_owner_title'); ?></h1>
                <p class="component-page-description"><?php echo $i18n->t('studio.error_owner_desc'); ?></p>
                <div class="mt-24">
                    <a href="<?php echo $basePath; ?>main" class="component-button primary w-100" data-nav="main" style="justify-content: center; text-decoration: none;">
                        <?php echo $i18n->t('menu.back_home'); ?>
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="studio-sidebar" style="width: 240px; border-right: 1px solid var(--border-light); background: var(--bg-surface); padding: 16px; display: flex; flex-direction: column; gap: 8px;">
            
            <a href="<?php echo $basePath . $urlDashboard; ?>" 
               class="menu-link <?php echo ($studioView === 'panel-control') ? 'active' : ''; ?>" 
               data-nav="<?php echo $urlDashboard; ?>"
               data-fetch="studio/parts/dashboard"
               data-target="#studio-content-area"
               style="text-decoration: none;">
                <div class="menu-link-icon">
                    <span class="material-symbols-rounded">dashboard</span>
                </div>
                <div class="menu-link-text"><?php echo $i18n->t('studio.menu_dashboard'); ?></div>
            </a>

            <a href="<?php echo $basePath . $urlContent; ?>" 
               class="menu-link <?php echo ($studioView === 'manage-content') ? 'active' : ''; ?>" 
               data-nav="<?php echo $urlContent; ?>"
               data-fetch="studio/parts/content"
               data-target="#studio-content-area"
               style="text-decoration: none;">
                <div class="menu-link-icon">
                    <span class="material-symbols-rounded">video_library</span>
                </div>
                <div class="menu-link-text"><?php echo $i18n->t('studio.menu_content'); ?></div>
            </a>

        </div>

        <div class="studio-content" id="studio-content-area" style="flex: 1; overflow-y: auto; padding: 24px; background: var(--bg-app);">
            <?php 
                // Carga inicial (Server Side Rendering) para F5 o acceso directo
                if ($studioView === 'panel-control') {
                    include __DIR__ . '/dashboard.php';
                } elseif ($studioView === 'manage-content') {
                    include __DIR__ . '/content.php';
                }
            ?>
        </div>

    <?php endif; ?>

</div>

<style>
    @media (max-width: 768px) {
        .component-wrapper--flex { flex-direction: column !important; }
        .studio-sidebar {
            width: 100% !important;
            height: auto !important;
            border-right: none !important;
            border-bottom: 1px solid var(--border-light);
            flex-direction: row !important;
            overflow-x: auto;
            padding: 8px !important;
        }
        .menu-link { flex: 0 0 auto !important; width: auto !important; padding-right: 16px; }
    }
</style>