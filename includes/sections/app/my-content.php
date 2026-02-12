<?php
// includes/sections/app/my-content.php

if (!isset($_SESSION['user_id']) || !isset($_SESSION['uuid'])) {
    header("Location: " . $basePath . "login");
    exit;
}

$requestedUuid = $routeParams['uuid'] ?? '';

if ($requestedUuid !== $_SESSION['uuid']) {
    ?>
    <div class="component-studio-state-screen">
        <div class="component-studio-state-content">
            <span class="material-symbols-rounded component-studio-state-icon danger">lock</span>
            <h2 class="component-studio-state-title">Acceso Denegado</h2>
            <p class="component-studio-state-text">No tienes permiso para ver este contenido.</p>
        </div>
    </div>
    <?php
    return;
}
?>

<div class="component-studio-layout">
    
    <div class="component-studio-toolbar">
        <div class="component-studio-toolbar-group">
            <span class="component-toolbar-title">Contenido del canal</span>
        </div>

        <div class="component-studio-toolbar-group">
            <button class="component-button square" 
                    title="Subir videos"
                    data-nav="s/channel/upload/<?php echo $requestedUuid; ?>">
                <span class="material-symbols-rounded">upload</span>
            </button>
        </div>
    </div>

    <div class="component-studio-content-area">
        
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
                        <td colspan="7">
                            <div class="component-studio-empty-wrapper">
                                <span class="material-symbols-rounded component-studio-empty-icon">video_library</span>
                                <span>No se ha encontrado contenido disponible.</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>

</div>