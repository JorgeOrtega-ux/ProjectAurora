<?php
// includes/sections/app/my-content.php

// 1. Verificación de sesión básica
if (!isset($_SESSION['user_id']) || !isset($_SESSION['uuid'])) {
    header("Location: " . $basePath . "login");
    exit;
}

// 2. Obtener el UUID solicitado desde los parámetros de la ruta
// Nota: $routeParams se define ahora tanto en router.php como en loader.php
$requestedUuid = $routeParams['uuid'] ?? '';

// 3. Verificación Estricta de Propiedad
if ($requestedUuid !== $_SESSION['uuid']) {
    ?>
    <div class="main-content" style="padding: 0; height: 100%; display: flex; align-items: center; justify-content: center; background: #fff;">
        <div style="text-align: center;">
            <span class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 16px; color: var(--danger-color);">lock</span>
            <h2 style="color: var(--text-primary);">Acceso Denegado</h2>
            <p style="color: var(--text-secondary);">No tienes permiso para ver este contenido.</p>
        </div>
    </div>
    <?php
    return;
}
?>

<div class="main-content" style="padding: 0; height: 100%; box-sizing: border-box; background-color: #ffffff; display: flex; flex-direction: column;">
    
    <div class="content-top" style="
        flex: 0 0 auto;
        padding: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border-color);">
        
        <div class="top-left" style="display: flex; align-items: center; gap: 8px;">
            <span class="component-toolbar-title">Mi Contenido</span>
        </div>

        <div class="top-right">
            </div>
    </div>

    <div class="content-bottom" style="
        flex: 1 1 auto;
        overflow-y: auto;
        position: relative;
        padding: 0;">
        
        </div>

</div>