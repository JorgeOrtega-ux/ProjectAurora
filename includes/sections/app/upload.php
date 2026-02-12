<?php
// includes/sections/app/upload.php

if (!isset($_SESSION['user_id']) || !isset($_SESSION['uuid'])) {
    header("Location: " . $basePath . "login");
    exit;
}

$requestedUuid = $routeParams['uuid'] ?? '';

// Verificación de seguridad: solo el dueño puede subir videos a su canal
if ($requestedUuid !== $_SESSION['uuid']) {
    // Redirigir a main o mostrar pantalla de acceso denegado
    header("Location: " . $basePath . "main");
    exit;
}
?>

<div class="component-studio-layout">
    
    <div class="component-studio-toolbar">
        <div class="component-studio-toolbar-group">
            <span class="component-toolbar-title">Subir videos</span>
        </div>
        <div class="component-studio-toolbar-group">
            </div>
    </div>

    <div class="component-studio-content-area centered">
        
        <div class="component-studio-upload-zone">
            
            <div class="component-studio-upload-circle" role="button" tabindex="0">
                <span class="material-symbols-rounded component-studio-upload-icon">upload</span>
            </div>

            <h2 class="component-studio-upload-title">
                Arrastra y suelta archivos de video para subirlos
            </h2>
            <p class="component-studio-upload-desc">
                Tus videos serán privados hasta que los publiques.
            </p>

            <button class="component-button primary" style="padding: 0 24px; font-weight: 600;">
                SELECCIONAR ARCHIVOS
            </button>

            <div class="component-studio-upload-legal">
                <p>
                    Si envías tus videos a ProjectAurora, aceptas las <a href="#" class="component-studio-link">Condiciones del Servicio</a> y los <a href="#" class="component-studio-link">Lineamientos de la Comunidad</a>.
                </p>
                <p>
                    Asegúrate de no infringir los derechos de autor o de privacidad de otras personas. <a href="#" class="component-studio-link">Más información</a>
                </p>
            </div>

        </div>

    </div>

</div>