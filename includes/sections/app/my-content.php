<?php
// includes/sections/app/my-content.php

if (!isset($_SESSION['user_id']) || !isset($_SESSION['uuid'])) {
    header("Location: " . $basePath . "login");
    exit;
}

$requestedUuid = $routeParams['uuid'] ?? '';

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

<div class="main-content" style="padding: 0; height: 100%; background-color: #ffffff; display: flex; flex-direction: column;">
    
    <div class="content-top" style="
        flex: 0 0 auto;
        padding: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border-color);">
        
        <div class="top-left" style="display: flex; align-items: center; gap: 8px;">
            <span class="component-toolbar-title">Contenido del canal</span>
        </div>

        <div class="top-right">
            <button class="component-button square" 
                    title="Subir videos"
                    data-nav="<?php echo $basePath; ?>s/channel/upload/<?php echo $requestedUuid; ?>">
                <span class="material-symbols-rounded">upload</span>
            </button>
        </div>
    </div>

    <div class="content-bottom" style="flex: 1 1 auto; overflow-y: auto; padding: 24px;">
        
        <div class="component-table-wrapper">
            <table class="component-table">
                <thead>
                    <tr>
                        <th>Video</th>
                        <th>Visibilidad</th>
                        <th>Restricciones</th>
                        <th>Fecha</th>
                        <th class="text-right">Vistas</th>
                        <th class="text-right">Comentarios</th>
                        <th class="text-right">"Me gusta" (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" style="padding: 48px; text-align: center; color: var(--text-secondary);">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                                <span class="material-symbols-rounded" style="font-size: 40px; color: var(--border-color-hover);">video_library</span>
                                <span>No se ha encontrado contenido disponible.</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>

</div>