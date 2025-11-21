<?php
// includes/sections/system/status-page.php

$status = $_GET['status'] ?? 'suspended';

// Configuración por defecto (Suspendido)
$icon = "block";
$themeClass = "status-theme-suspended"; // Clase para color rojo
$titleKey = "status.suspended_title";
$msgKey = "status.suspended_msg";

// Configuración para Eliminado
if ($status === 'deleted') {
    $titleKey = "status.deleted_title";
    $msgKey = "status.deleted_msg";
    $icon = "delete_forever";
    $themeClass = "status-theme-deleted"; // Clase para color gris
}
?>

<style>
    /* Contenedor específico de la página de estado */
    .status-page-container {
        text-align: center;
        padding-top: 0;
    }

    /* Wrapper del icono para espaciado */
    .status-icon-wrapper {
        margin-bottom: 20px;
    }

    /* Icono principal */
    .status-icon {
        font-size: 80px;
    }

    /* Título principal */
    .status-title {
        margin-bottom: 15px;
        font-size: 28px;
    }

    /* Texto descriptivo */
    .status-message {
        color: #555;
        line-height: 1.6;
        font-size: 16px;
        margin-bottom: 40px;
    }

    /* Enlace de volver */
    .status-back-link {
        color: #888;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: color 0.2s ease;
    }

    .status-back-link:hover {
        color: #333;
    }

    .status-back-icon {
        font-size: 16px;
    }

    /* --- TEMAS DE COLOR DINÁMICOS --- */
    
    /* Tema Rojo (Suspendido) */
    .status-theme-suspended {
        color: #d32f2f;
    }

    /* Tema Gris (Eliminado) */
    .status-theme-deleted {
        color: #616161;
    }
</style>

<div class="section-content active" data-section="status-page">
    <div class="section-center-wrapper">
        
        <div class="form-container status-page-container">
            
            <div class="status-icon-wrapper">
                <span class="material-symbols-rounded status-icon <?php echo $themeClass; ?>">
                    <?php echo $icon; ?>
                </span>
            </div>

            <h1 class="status-title <?php echo $themeClass; ?>" data-i18n="<?php echo $titleKey; ?>">
                <?php echo trans($titleKey); ?>
            </h1>
            
            <p class="status-message" data-i18n="<?php echo $msgKey; ?>">
                <?php echo trans($msgKey); ?>
            </p>
            
            <div>
                <a href="<?php echo isset($basePath) ? $basePath : '/ProjectAurora/'; ?>login" class="status-back-link">
                    <span class="material-symbols-rounded status-back-icon">arrow_back</span> 
                    <span data-i18n="global.back_home"><?php echo trans('global.back_home'); ?></span>
                </a>
            </div>

        </div>

    </div>
</div>