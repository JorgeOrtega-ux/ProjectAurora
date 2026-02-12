<?php
// includes/sections/app/upload.php

if (!isset($_SESSION['user_id']) || !isset($_SESSION['uuid'])) {
    header("Location: " . $basePath . "login");
    exit;
}

$requestedUuid = $routeParams['uuid'] ?? '';

if ($requestedUuid !== $_SESSION['uuid']) {
    header("Location: " . $basePath . "main");
    exit;
}
?>

<div class="main-content" style="padding: 0; height: 100%; background-color: #ffffff; display: flex; flex-direction: column;">
    
    <div class="content-top" style="
        flex: 0 0 auto;
        padding: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border-color);">
        
        <div class="top-left" style="display: flex; align-items: center; gap: 8px;">
            <span class="component-toolbar-title">Subir videos</span>
        </div>

        <div class="top-right">
            </div>
    </div>

    <div class="content-bottom" style="flex: 1 1 auto; overflow-y: auto; display: flex; align-items: center; justify-content: center; padding: 24px;">
        
        <div class="upload-placeholder-container" style="text-align: center; max-width: 600px; width: 100%;">
            
            <div style="
                width: 136px; 
                height: 136px; 
                background-color: var(--bg-hover-light); 
                border-radius: 50%; 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                margin: 0 auto 24px auto;
                cursor: pointer;
                transition: transform 0.2s ease;">
                
                <span class="material-symbols-rounded" style="font-size: 64px; color: var(--text-tertiary);">upload</span>
            </div>

            <h2 style="font-size: 16px; font-weight: 500; margin: 0 0 8px 0; color: var(--text-primary);">
                Arrastra y suelta archivos de video para subirlos
            </h2>
            <p style="font-size: 14px; color: var(--text-secondary); margin: 0 0 24px 0;">
                Tus videos serán privados hasta que los publiques.
            </p>

            <button class="component-button primary" style="margin: 0 auto; padding: 0 24px; height: 40px; font-weight: 600;">
                SELECCIONAR ARCHIVOS
            </button>

            <div style="margin-top: 48px; font-size: 12px; color: var(--text-tertiary); line-height: 1.5;">
                <p style="margin-bottom: 8px;">
                    Si envías tus videos a ProjectAurora, aceptas las <a href="#" style="color: var(--text-secondary); text-decoration: none; font-weight: 500;">Condiciones del Servicio</a> y los <a href="#" style="color: var(--text-secondary); text-decoration: none; font-weight: 500;">Lineamientos de la Comunidad</a>.
                </p>
                <p>
                    Asegúrate de no infringir los derechos de autor o de privacidad de otras personas. <a href="#" style="color: var(--text-secondary); text-decoration: none; font-weight: 500;">Más información</a>
                </p>
            </div>

        </div>

    </div>

</div>